<?php

/**
 * @package /app/Import/Idml/IdmlStory.php
 *  
 * @class   IdmlStory
 * 
 * @description This class is the parser for IDML stories (which are XML files). 
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlAssembler',          'Import/Idml');
App::uses('IdmlParserHelper',       'Import/Idml');
App::uses('IdmlParagraphRange',     'Import/Idml/PageElements');
App::uses('IdmlElementFactory',     'Import/Idml/PageElements');


class IdmlStory
{
    
    public $parentElement = null;  // needed by IdmlElements::parents() in order to stop the tree traversal at this object.
    
    /**
     * Is story loaded.
     * @var boolean
     */
    public $isLoaded = false;

    /**
     * @var string The InDesign unique identifier for this story.
     */
    public $UID;

    /**
     * Is vertical story.
     * @var boolean
     */
    public $isVertical = false;

    /**
     * Is right to left direction.
     * @var boolean
     */
    public $isRtl = false;

    /**
     * Is this story set to track changes.
     * @var boolean
     */
    public $trackChanges = false;

    /**
     * Array of all children elements.
     * @var array
     */
    public $childrenElements = array();


    /**
     * @var IdmlTextFrame $idmlTextFrame is the <TextFrame> into which this object has been placed.
     */
    public $idmlTextFrame;

    /**
     * Current page where this story is. Init function will set this.
     * @var IdmlPage
     */
    public $page;
    
    /**
     *
     * @var IdmlStyleInfo - a class to hold all the idml and css information for this object
     */
    public $styleInfo = null;

    /**
     * Full path to the story file.
     * @var string
     */
    public $filename;
    
    /**
     * the following variables are needed to implement "sections"
     * which are the creation/saving of new output/html pages using idml "section" markup tags to indicate page breaks
     * @var bool
     */
    public $wasProduced = false;
    /**
     *
     * @var bool 
     */
    public $inProductionBreakHierarchy = false;


    /** The constructor
     * @param string $storyUID is the InDesign unique identifier for the story
     */
    public function __construct($storyUID = '')
    {
        $this->UID = $storyUID;
        $this->idmlTextFrame = null;
        $this->appliedObjectStyle = '';
        $this->styleInfo = array();
    }

    /**
     * Initialize story with a page.
     * @param IdmlPage $page
     */
    public function init(IdmlPage $page)
    {
        $this->page = $page;
    }

    /**
     * Parent IdmlObject.
     * @return IdmlTextFrame
     */
    public function parentIdmlObject()
    {
        return $this->idmlTextFrame;
    }
  
    /**
     * Load story from file name.
     * 
     * @param string $filename Needs absolute path to story xml file. IF empty it will use internal variable.
     */
    public function load($filename = '')
    {
        if ($this->isLoaded) 
        {
            return;
        }

        if (empty($filename))
        {
            $filename = $this->filename;
        }

        if (!file_exists($filename))
        {
            IdmlAssembler::getProgressUpdater()->setWarning("Story with file {$filename} is not found.");
            return;
        }

        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $storyContent = file_get_contents($filename);
        $ok = $domDocument->loadXML(mb_convert_encoding($storyContent, 'UTF-8'));
        if ($ok === false)
        {
            IdmlAssembler::getProgressUpdater()->setWarning("Could not load xml for story {$filename}");
        }

        $storyNodes = $domDocument->getElementsByTagName('Story');
        $storyNode = $storyNodes->item(1);
        $this->parse($storyNode);
        $this->isLoaded = true;
    }

    /**
     * Parse DOM Document.
     * @param DOMDocument $document
     */
    public function parse(DOMElement $storyNode)
    {
        // Load some common properties.
        if ($storyNode->hasAttribute(IdmlAttributes::Self))
        {
            // The storyUID is normally provided in the constructor, but during unit tests it may be an empty string.
            // This assignment is only needed during unit tests
            if ($this->UID == '')
            {
                $this->UID = $storyNode->getAttribute(IdmlAttributes::Self);
            }
        }

        if ($storyNode->hasAttribute(IdmlAttributes::TrackChanges))
        {
            $this->trackChanges = $storyNode->getAttribute(IdmlAttributes::TrackChanges) == 'false' ? false : true;
        }

        $storyPreferenceNode = $storyNode->getElementsByTagName('StoryPreference');
        $storyPreferenceNode = $storyPreferenceNode->item(0);

        if ($storyPreferenceNode->hasAttribute(IdmlAttributes::StoryOrientation))
        {
            $this->isVertical = $storyPreferenceNode->getAttribute(IdmlAttributes::StoryOrientation) == 'Horizontal' ? false : true;
        }

        if ($storyPreferenceNode->hasAttribute(IdmlAttributes::StoryDirection))
        {
            $this->isRtl = $storyPreferenceNode->getAttribute(IdmlAttributes::StoryDirection) == 'LeftToRightDirection' ? false : true;
        }

        $this->parseChildren($storyNode);
    }

    /**
     * Parse children.
     * 
     * @param DOMNode $parentNode
     */
    private function parseChildren($parentNode)
    {
        //same logic/code as for IdmlElement method - more comprehensive and easier to maintain
        foreach ($parentNode->childNodes as $childNode) {
            if (IdmlParserHelper::isParsableChildIdmlObjectNode($childNode))
            {
                if (Configure::read("dev.idmlHtmlDebugOutput") == true)
                {
                    CakeLog::debug(sprintf("[IdmlStory::parseChildren] Parse Story %8s %30s --> %sKb", $this->UID, $childNode->nodeName, round(memory_get_usage(true)/1024)));
                }

                $parsableObject = IdmlElementFactory::createFromNode($childNode);
                //we must set the parent before parsing since children need parent
                $parsableObject->parentElement = $this;
                $parsableObject->parse($childNode);
                $this->childrenElements[] = $parsableObject;
            }
        }
    }
    
    /* This accept function is called by an idmlTextFrame.
     * 
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth)
    {
        $visitor->visitStory($this, $depth);
        
        foreach ($this->childrenElements as $element)
        {
            $element->accept($visitor, $depth+1);
        }

        $visitor->visitStoryEnd($this, $depth);
    }
    
}

?>
