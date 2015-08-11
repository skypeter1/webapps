<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlXmlElement.php
 *
 * @class   IdmlXmlElement
 *
 * @description Parser for Story <XmlElement>, which we use for PXE markup tagging
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',    'Import/Idml/PageElements');
App::uses('IdmlStory',      'Import/Idml');


class IdmlXmlElement extends IdmlElement
{
    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;
    public $markupTag = '';
    public $xmlContent= '';

    /**
     * hash of XML attributes, index by name
     * @var array 
     */
    public $xmlAttributes = array();    
    
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
     * Parse children.
     * @param DOMElement $parentNode
     */
    protected function parseAttributes($parentNode)
    {
        foreach ($parentNode->childNodes as $childNode)
        {
            if($childNode->nodeType == XML_ELEMENT_NODE &&
               $childNode->nodeName == 'XMLAttribute')
            {
                $attrName = $childNode->getAttribute('Name');
                $attrValue = $childNode->getAttribute('Value');
                $this->xmlAttributes[$attrName]=$attrValue;
            }
        }
    }    

    /**
     * Parse from DOM node.
      *
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->markupTag = $node->hasAttribute('MarkupTag') ? $node->getAttribute('MarkupTag') : '';

        $this->idAttribute = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;
        $this->xmlContent = $node->hasAttribute(IdmlAttributes::XmlContent) ? $node->getAttribute(IdmlAttributes::XmlContent) : null;
        //don't parse children of this tag if they are marked to be hidden from Chaucer (CHAUC-2866)
        if($this->markupTag != 'XMLTag/ChaucerHidden') 
        {
            $this->parseChildren($node);
            $this->parseAttributes($node);
        }       
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {      
        $visitor->visitXmlElement($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitXmlElementEnd($this, $depth);
    }
}
