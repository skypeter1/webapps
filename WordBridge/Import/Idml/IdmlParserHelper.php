<?php

/**
 * @package /app/Import/Idml/IdmlParserHelper.php
 *
 * @class   IdmlParserHelper
 *
 * @description Helper functions used while parsing. Just common stuff here.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlParserHelper
{
    /**
     * Parse boundary.
     * @param DOMNode $element
     * @return IdmlBoundary
     */
    public static function parseBoundary(DOMNode $element)
    {
         $points = array();
         $xpath = new DOMXPath($element->ownerDocument);

         // Get four <PathPointType> tags which define the objects corners (or more than four,
         // for complex polygons, which we treat as rectangles anyway)
         $corners = $xpath->query('Properties/PathGeometry/GeometryPathType/PathPointArray//PathPointType', $element);
         foreach($corners as $corner)
         {
             $attributes = $corner->attributes;
             $anchor = $attributes->getNamedItem('Anchor')->value;
             $parts = explode(' ', $anchor);
             assert(count($parts) == 2);
             $points[] = array('x' => (float)$parts[0], 'y' => (float)$parts[1]);
         }
         
         return  IdmlBoundary::createFromCornerPoints($points);
    }

    /**
     * Get text content of the node. Most of the time the node will only contain a single XML_TEXT_NODE,
     * but this method will also deal with any Processing Instruction nodes that can also be embedded in the <Content>
     * (These are rare.)
     *
     * @param DOMNode $node
     * 
     * @return string
     */
    public static function getTextContent(DOMNode $node)
    {
        $text = '';

        foreach($node->childNodes as $childNode)
        {
            switch($childNode->nodeType)
            {
                case XML_TEXT_NODE:
                    $text .= $childNode->nodeValue;
                    break;

                case XML_PI_NODE:

                    if ($childNode->nodeValue == '18')     // Auto Page Number
                    {
                        $text .= '<?ACE 18 ?>';
                    }
                    /*
                    Skip these Processing Instructions

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
        return $text;
    }

     /**
     * Get CDATA content of the node. Will search for child that is of type text.
     *
     * @param DOMNode $node
     *
     * @return string
     */
    public static function getCData(DOMNode $node)
    {
        foreach($node->childNodes as $childNode)
        {
            if ($childNode->nodeType == XML_CDATA_SECTION_NODE)
            {
                return $childNode->nodeValue;
            }
        }

        return '';
    }

    /**
     * Converts idml units into CSS units. Returns pt by default.
     * 
     * @example input: 'unit' output: 'pt';
     * @param string $unitName
     * 
     * @return string
     */
    public static function convertUnitIntoCssUnit($unitName)
    {
        switch($unitName)
        {
            case 'unit': return 'pt';
            case 'pixel': return 'px';
            default:
                return 'pt';
        }
    }

    /**
     * Conert IDML justification string into html text-align valid value.
     * Input: 'CenterAlign' Output: 'center'
     *
     * @param string $justification
     * @param string $pagePosition Usually 'left' or 'right' - where is page from binding.
     *
     * @return string
     */
    public static function convertJustification($justification, $pagePosition)
    {
        switch($justification)
        {
            case 'CenterAlign':         return 'center';
            case 'RightAlign':          return 'right';
            case 'LeftAlign':           return 'left';
            case 'AwayFromBindingSide': return $pagePosition == 'left' ? 'left' : 'right';
            case 'ToBindingSide':       return $pagePosition == 'left' ? 'right' : 'left';
            case 'LeftJustified':       return 'left';
            case 'CenterJustified':     return 'center';
            case 'RightJustified':      return 'right';
            case 'FullyJustified':      return 'justify';
            default:                    return 'left';
        }
    }
    
    /**
     * note: this gets ALL attributes and properties which then can be filtered by whomever calls it
     * we might want to filter in this method to save memory usage if it becomes an issue
     * 
     * @param DOMNode $element
     * @param array $attributeNames (array of names of attributes to filter results
     * @return array of all attributes and properties for this element
     */
    public static function getAllDomNodeAttributesAndProperties(DOMNode $element, &$attributeNames = null) 
    {
        if($attributeNames && is_array($attributeNames)) 
        {
            $filterNames = true;
        }
        else
        {
            $filterNames = false;
        }
        $properties = array();
        if($element->hasAttributes()) 
        {
            foreach($element->attributes as $attr) 
            {
                if($attr->nodeType == XML_TEXT_NODE)
                {
                    continue;
                }
                if(!$filterNames || ($filterNames && in_array($attr->nodeName, $attributeNames)))
                {
                    if(!array_key_exists($attr->nodeName, $properties))
                    {
                        $properties[$attr->nodeName] = $attr->nodeValue;
                    }
                    else
                    {
                        //this should not happen: we will have a collision of property values so log this potential problem?
                        CakeLog::debug("[IdmlParserHelper::getAllDomNodeAttributesAndProperties] Duplicate element attribute name found: ".
                                $attr->nodeName." ".__FILE__." Line ".__LINE__);
                    }
                }
            }
        }
        if($element->hasChildNodes() && ($element->getElementsByTagName('Properties')->length > 0))
        {
            /** @var $proplist DOMNode*/
            $proplist = $element->getElementsByTagName('Properties')->item(0);
            foreach($proplist->childNodes as $prop) 
            {
                if($prop->nodeType == XML_TEXT_NODE)
                {
                    continue;
                }
                if(!$filterNames || ($filterNames && in_array($prop->nodeName, $attributeNames)))
                {
                    if(!array_key_exists($prop->nodeName, $properties))
                    {
                        $properties[$prop->nodeName] = $prop->nodeValue;
                    }
                    else
                    {
                        CakeLog::debug("[IdmlParserHelper::getAllDomNodeAttributesAndProperties] Duplicate element property name found: ".
                                $prop->nodeName." ".__FILE__." Line ".__LINE__);
                    }
                }
            } 
        }
        return $properties;
    }
    
    /**
     * This method is used in the *affirmative* to decide which elements contain "author content"
     * This method is used in the *negative* to decide which elements are not stylistic, dimensional, or positional
     * @param DOMNode $node
     */
    public static function isParsableChildIdmlObjectNode(DOMNode $node)
    {
        if($node->nodeType == XML_ELEMENT_NODE)
        {
            $name = $node->nodeName;
            switch($name)
            {
                case 'Rectangle':
                case 'TextFrame':
                case 'Element':
                case 'CharacterStyleRange':
                case 'ParagraphStyleRange':
                case 'Image':
                case 'PDF':
                case 'EPS':
                case 'Group':
                case 'Story':
                case 'Change':
                case 'Content':
                case 'Br':
                case 'XMLElement':
                case 'Table':
                case 'Row':
                case 'Column':
                case 'Cell':
                case 'HyperlinkTextSource':
                case 'HyperlinkTextDestination':
                case 'Sound':
                case 'Movie':
                case 'TextVariableInstance':
                case 'GraphicLine':
                case 'Polygon':
                case 'Oval':
                    return true;
                default:
                    return false;
            }
            
        }
    }
    /**
     * I think this is trying to determine if the passed in node is an idml <XMLElement> (which holds a MarkupTag attribute)
     * i.e. - is this an attribute of "MarkupTag" that wraps other elements of interest?
     * @param DOMNode $node
     * @return boolean
     */
    public static function isIdmlTagNode(DOMNode $node)
    {
        if($node->nodeType == XML_ELEMENT_NODE && $node->nodeName == "XMLElement")
        {
            if($node->hasAttributes())
            {
                foreach($node->attributes as $attr)
                {
                    if($attr->nodeType == XML_TEXT_NODE)
                    {
                        continue;
                    }
                    if($attr->nodeName=="MarkupTag")
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    public static function getMarkupTagFromNode(DomNode $node)
    {
        if(self::isIdmlTagNode($node))
        {
            foreach($node->attributes as $attr)
            {
                if($attr->nodeType == XML_TEXT_NODE)
                {
                    continue;
                }
                if($attr->nodeName=="MarkupTag")
                {
                    $tag =  $attr->value;
                    $tag = str_replace("XMLTag/", "", $tag);
                    return $tag;
                }
            }
        }
        return "";
    }
    
    public static function getIdmlObjectType($idmlObject)
    {
        if($idmlObject instanceof IdmlPage)
        {
            return 'Page';
        }
        if($idmlObject instanceof IdmlTextFrame)
        {
            return 'TextFrame';
        }
        if($idmlObject instanceof IdmlCharacterRange)
        {
            return 'CharacterRange';
        }
        if($idmlObject instanceof IdmlParagraphRange)
        {
            return 'ParagraphRange';
        }
        if($idmlObject instanceof IdmlImage)
        {
            return 'Image';
        }
        if($idmlObject instanceof IdmlRectangle)
        {
            return 'Rectangle';
        }
        if($idmlObject instanceof IdmlGroup)
        {
            return 'Group';
        }
        if($idmlObject instanceof IdmlStory)
        {
            return 'Story';
        }
        if($idmlObject instanceof IdmlChange)
        {
            return 'Change';
        }
        if($idmlObject instanceof IdmlContent)
        {
            return 'Content';
        }
        if($idmlObject instanceof IdmlBrContent)
        {
            return 'Br';
        }
        if($idmlObject instanceof IdmlTable)
        {
            return 'Table';
        }
        if($idmlObject instanceof IdmlTableRow)
        {
            return 'TableRow';
        }
        if($idmlObject instanceof IdmlTableColumn)
        {
            return 'TableColumn';
        }
        if($idmlObject instanceof IdmlTableCell)
        {
            return 'TableCell';
        }
        if($idmlObject instanceof IdmlHyperlink)
        {
            return 'HyperlinkTextSource';
        }
        if($idmlObject instanceof IdmlHyperlinkDestination)
        {
            return 'HyperlinkTextDestination';
        }
        if($idmlObject instanceof IdmlXmlElement)
        {
            return 'XmlElement';
        }
        if($idmlObject instanceof IdmlElement) //THIS MUST ALWAYS BE LAST IN LIST (because all page elements are IdmlElements)
        {
            return 'Element';
        }
        return 'Unknown Idml Object Type';
    }
    
}
