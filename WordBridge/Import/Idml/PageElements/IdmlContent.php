<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlContent.php
 *
 * @class   IdmlContent
 *
 * @description Parser for InDesign <Content>, which contains text nodes and processing instructions.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',                'Import/Idml/PageElements');
App::uses('IdmlText',                   'Import/Idml/PageElements');
App::uses('IdmlTab',                    'Import/Idml/PageElements');
App::uses('IdmlProcessingInstruction',  'Import/Idml/PageElements');


/**
 * This is real content of element. Contains text and processing instructions.
 */
class IdmlContent extends IdmlElement
{
    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /**
     * Textual content.
     * @var string
     */
    public $content = '';

    /**
     * Constructor.
     * @param IdmlStory $story
     */
    public function __construct(IdmlStory $story = null)
    {
        parent::__construct();

        $this->story = $story;
    }

    /**
     * Parse from DOM node.
     *
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->parseChildren($node);
    }

    /**
     * Merge current content with content from passed node.
     * When code was added to process chilren, merge and parse became the same function.
     *
     * @param DOMElement $node
     */
    public function merge(DOMElement $node)
    {
        $this->parseChildren($node);
    }

    /**
     * Parse content node's child nodes.
     * All child nodes should be either text or processing instructions.
     *
     * @param DOMElement $parentNode
     */
    public function parseChildren($parentNode)
    {
        foreach($parentNode->childNodes as $childNode)
        {
            switch($childNode->nodeType)
            {
                case XML_TEXT_NODE:

                    $this->deconstructTextNode($childNode->nodeValue);
                    break;

                case XML_PI_NODE:

                    $idmlPINode = new IdmlProcessingInstruction($childNode->nodeValue, $this);

                    /*
                    For now, skip these Processing Instructions

                    "<?ACE 3?>",     // End Nested Style
                    "<?ACE 7?>",     // Indent Here Tab
                    "<?ACE 8?>",     // Right Indent Tab
                    "<?ACE 19?>",    // Section Marker
                    */
                    break;

                default:
                    break;
            }
        }
    }

    /**
     * Break up the text content into IdmlTab objects and IdmlText objects
     * @param string $textNodeContent
     */
    protected function deconstructTextNode($textNodeContent)
    {
        $paragraph = $this->getParagraph();

        while (strlen($textNodeContent) > 0)
        {
            $text = $this->getNextTextFragment($textNodeContent);

            $textLen = strlen($text);

            if ($textLen > 0)
            {
                $textNode = new IdmlText($text, $this);

                $textNodeContent = substr($textNodeContent, $textLen);
            }

            if (substr($textNodeContent, 0, 1) == "\x09")
            {
                $tabNode = new IdmlTab($this, $paragraph->tabIndex++);

                $textNodeContent = substr($textNodeContent, 1);
            }
        }
    }

    protected function getNextTextFragment($textNodeContent)
    {
        $tabPos = strpos($textNodeContent, "\x09");

        if ($tabPos === false)
        {
            return $textNodeContent;
        }
        else
        {
            return substr($textNodeContent, 0, $tabPos);
        }
    }

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitContent($this, $depth);

        // Produce the text and IDML processing instruction nodes
        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitContentEnd($this, $depth);
    }
    
    /**
     * checks if the content element has any text 
     * @return boolean
     */
    public function hasContent()
    {
        foreach ($this->childrenElements as $childNode)
        {
            if (get_class($childNode) == 'IdmlText' && $childNode->hasContent())
            {
                return true;
            }

            if (get_class($childNode) == 'IdmlTab')
            {
                return true;
            }
        }

        return false;
    }
}
