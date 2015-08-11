<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTextFrame.php
 * 
 * @class   IdmlTextFrame
 * 
 * @description Parser for <TextFrame> which normally is found in a <Spread>, but which can also be embedded in a <Story>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',        'Import/Idml/PageElements');
App::uses('IdmlAssembler',      'Import/Idml');
App::uses('IdmlPage',           'Import/Idml');
App::uses('IdmlStory',          'Import/Idml');
App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');


/**
 * Idml Text Frame.
 */
class IdmlTextFrame extends IdmlElement
{
    /** @var string $textFrameUID is the InDesign unique identifier of this TextFrame */
    public $UID;
    
    /** @var string $parentStoryUID is the InDesign unique identifier of the story that is flowed within this text frame */
    public $parentStoryUID;
    
    /** @var IdmlStory $idmlStory is the story that is within this text frame.
     * This will be null when the text frame is the second or subsequent text frame
     * of a threaded story. */
    public $story;

    /** @var IdmlTransformation $transformation */
    public $transformation;
    
    /**
     * The bounding rectangle of the text frame.
     * @var IdmlBoundary
     */
    public $boundary;
    
    /** @var string $contentType the value of 'TextType' is the only one that was handle for now.
     * If this is not a 'TextType' the object does not need to be further processed. */
    public $contentType;

    /**
     * UID of previous text frame or null.
     * @var string
     */
    public $prevTextFrame;

    /**
     * UID of next text frame or null.
     * @var string
     */
    public $nextTextFrame;

    /**
     * $textColumnCount and $columnWidth are used for the special case of a text frame divided into columns
     */
    public $textColumnCount;
    public $columnWidth;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->UID = '';
        $this->parentStoryUID = '';
        $this->contentType = '';
        $this->visible = true;
        $this->story = null;
        $this->transformation = new IdmlTransformation();
        $this->boundary = IdmlBoundary::createDefault();
        $this->properties = array();
    }


    /*
     * Text frames that have no story should be ignored when visiting IdmlPages
     */
    public function hasStory()
    {
        return ($this->story != null) ? true : false;
    }


    /**
     * Parse the portion of a spread.xml file that contains a <TextFrame>
     *
     * @param DOMElement $node is a single <TextFrame> node within the IDML document
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);
        
        $attributes = $node->attributes;
        $this->UID = $attributes->getNamedItem('Self')->value;                                         // like 'uf9'
        $this->parentStoryUID = $attributes->getNamedItem('ParentStory')->value;                       // like 'ue5'
        $this->contentType = $attributes->getNamedItem('ContentType')->value;                          // like 'TextType'
        $this->visible = ($attributes->getNamedItem('Visible')->value == 'false') ? false : true ;     // like 'true'

        $this->setTransform($node);

        $this->boundary = IdmlParserHelper::parseBoundary($node);
        $this->prevTextFrame = ($attributes->getNamedItem(IdmlAttributes::PreviousTextFrame)->value == 'n') ?
            null : $attributes->getNamedItem(IdmlAttributes::PreviousTextFrame)->value;
         
        $this->nextTextFrame = ($attributes->getNamedItem(IdmlAttributes::NextTextFrame)->value == 'n') ?
            null : $attributes->getNamedItem(IdmlAttributes::NextTextFrame)->value;

        $this->textColumnCount = (isset($this->contextualStyle->idmlKeyValues['TextFramePreference->TextColumnCount'])) ?
            (int) $this->contextualStyle->idmlKeyValues['TextFramePreference->TextColumnCount'] : null;
        $this->columnWidth = (isset($this->contextualStyle->idmlKeyValues['TextFramePreference->TextColumnFixedWidth'])) ?
            $this->contextualStyle->idmlKeyValues['TextFramePreference->TextColumnFixedWidth'] : null;

        // If we *do_not_have* a previous text frame then it is either a single text frame or the first of a series of
        // linked ones. In case of linked ones we output only the first one.
        if (!$this->prevTextFrame)
        {
            $this->loadParentStory();
        }

        // but, if we *have* a previous text frame, then we still have InDesign "threads" which is a no-no for fixed layout books
        else
        {
            if (IdmlAssembler::getInstance()->isFixedLayout())
            {
                CakeLog::debug("[IdmlTextFrame::parse] Encountered an InDesign threaded frame in a fixed layout book (story {$this->parentStoryUID}). Run Chaucer FixedLayoutPreflight.jsx script within InDesign and re-export the IDML.");
            }
        }
    }


    /**
     *  This function is called by the spread, after it has determined which page the text frame is on.
     *  It is entirely legitimate to exit without assigning anything to $this->idmlStory in which case this becomes an
     *  empty TextFrame.
     */
    protected function loadParentStory()
    {
        // Lookup and parse the story into memory
        $package = IdmlAssembler::getInstance()->getCurrentPackage();
        if ($package)
        {
            $story = $package->loadStory($this->parentStoryUID);

            // Sanity check
            $storyClass = get_class($story);
            if (substr($storyClass, 0, 14) == 'Mock_IdmlStory') $storyClass = 'IdmlStory';
            if ($story == null || $storyClass != 'IdmlStory')
            {
                $this->story = null;
                return;
            }

            // Finally, now that we are sure this story has not been placed already (in some other text frame),
            // keep a reference to it.
            $this->story = $story;
            $story->idmlTextFrame = $this;
        }
    }
    
    
    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        // The idmlStory will be null for text frames that contain a story that was already placed in another text frame.
        // Do not process those text frames.
        if ($this->story && $this->visible)
        {
            if ($this->isEmbedded())
            {
                $visitor->visitEmbeddedTextFrame($this, $depth);
            }

            else
            {
                $visitor->visitTextFrame($this, $depth);
                $this->story->accept($visitor, $depth+1);
                $visitor->visitTextFrameEnd($this, $depth);
            }

        }
    }

    /**
     * Parses the node tree of the story to determine whether or not it has displayable content.
     * Calls itself recursively to visit all descendants in the tree.
     * Without displayable content, the HTML producer can assume that the story's text frame is simply a rectangular shape.
     * @param IdmlStory|IdmlElement $element
     * @return bool
     */
    public function hasContent($element)
    {
        $contentNodes = array(
            'IdmlBrContent',
            'IdmlContent',
            'IdmlGraphicLine',
            'IdmlImage',
            'IdmlMovie',
            'IdmlOval',
            'IdmlPolygon',
            'IdmlProcessingInstruction',
            'IdmlRectangle',
            'IdmlSound',
            'IdmlTab',
            'IdmlTable',
            'IdmlText',
            'IdmlTextVariableInstance',
        );

        foreach ($element->childrenElements as $childNode)
        {
            if (in_array(get_class($childNode), $contentNodes))
            {
                return true;
            }

            if ($this->hasContent($childNode))
            {
                return true;
            }
        }

        return false;
    }
}

?>
