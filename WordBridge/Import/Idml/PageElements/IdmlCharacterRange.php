<?php

/**
 * @package /app/Import/Idml/PageElements/IdmlCharacterRange.php
 * 
 * @class   IdmlCharacterRange
 * 
 * @description Parser for Story <CharacterRange>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',                'Import/Idml/PageElements');
App::uses('IdmlContent',                'Import/Idml/PageElements');
App::uses('IdmlBrContent',              'Import/Idml/PageElements');
App::uses('IdmlImage',                  'Import/Idml/PageElements');
App::uses('IdmlRectangle',              'Import/Idml/PageElements');
App::uses('IdmlTextVariableInstance',   'Import/Idml/PageElements');

/**
 * Character range.
 */
class IdmlCharacterRange extends IdmlElement
{
    /**
     * Line height unit.
     * @var string
     */
    public $lineHeightUnit = 'unit';

    /**
     * Line height value.
     * @var int
     */
    public $lineHeightValue = null;

    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /**
     * Applied font.
     * @var string
     */
    public $appliedFont = '';

    /**
     * paragraphBreakType tracks the CSR's ParagraphBreakType, used in columnization
     */
    public $paragraphBreakType;

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
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->paragraphBreakType = (isset($this->contextualStyle->idmlKeyValues['ParagraphBreakType'])) ?
            $this->contextualStyle->idmlKeyValues['ParagraphBreakType'] : '';

        $this->parseChildren($node);
    }

    /**
     * Parses the children elements to find out if any have been positioned on the character range
     * Inline positioned elements require the parent character range to have position:relative
     * @return bool
     */
    public function hasInlinePositionedChild()
    {
        foreach ($this->childrenElements as $child)
        {
            if (isset($child->contextualStyle->idmlKeyValues['AnchoredObjectSetting->AnchoredPosition']) &&
                $child->contextualStyle->idmlKeyValues['AnchoredObjectSetting->AnchoredPosition'] == 'InlinePosition')
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse children.
     * @param DOMElement $parentNode
     */
    protected function parseChildren($parentNode)
    {
        foreach($parentNode->childNodes as $childNode)
        {
            if ($childNode->nodeType == XML_ELEMENT_NODE && $childNode->nodeName == 'Properties')
            {
                $this->parseProperties($childNode);
            }
        }
        parent::parseChildren($parentNode);
    }

    /**
     * Parse properties for character style.
     * 
     * @param DOMElement $propertiesNode
     */
    private function parseProperties(DOMElement $propertiesNode)
    {
        $leadingNodes = $propertiesNode->getElementsByTagName('Leading');
        if ($leadingNodes->length == 1)
        {
            $leadingNode = $leadingNodes->item(0);
            $this->lineHeightUnit = $leadingNode->hasAttribute('type') ? $leadingNode->getAttribute('type') : 'unit';
            $this->lineHeightUnit = IdmlParserHelper::convertUnitIntoCssUnit($this->lineHeightUnit);
            $this->lineHeightValue = IdmlParserHelper::getTextContent($leadingNode);
        }

        $appliedFontNodes = $propertiesNode->getElementsByTagName('AppliedFont');
        if ($appliedFontNodes->length == 1)
        {
            $appliedFontNode = $appliedFontNodes->item(0);
            $this->appliedFont = IdmlParserHelper::getTextContent($appliedFontNode);
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
        $visitor->visitCharacterRange($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitCharacterRangeEnd($this, $depth);
    }
}
