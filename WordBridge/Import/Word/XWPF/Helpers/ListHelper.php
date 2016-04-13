<?php

/**
 * Class ListHelper
 * This class contains helpful options to convert lists and retrieve styling
 */
class ListHelper
{
    /**
     * Extracts numbering information
     * @param   object  Paragraph
     * @return  string|boolean  Numbering
     */
    public static function paragraphExtractNumbering($paragraph)
    {
        // Prepare paragraph XML
        $paragraph_xml = java_values($paragraph->getCTP()->toString());
        $paragraph_xml = str_replace('w:', 'w', $paragraph_xml);

        // Get level
        $xml = new SimpleXMLElement($paragraph_xml);
        $lvl = $xml->xpath("wpPr/wnumPr/wilvl");

        // Check if there is numbering on level
        if (!is_array($lvl) || count($lvl) == 0) {
            return false;
        }

        // Get numbering ID
        $lvl = $lvl[0]['wval'] . '';
        $numId = $xml->xpath("wpPr/wnumPr/wnumId");
        $numId = $numId[0]['wval'] . '';

        // Set numbering data
        $data['lvl'] = $lvl;
        $data['numId'] = $numId;

        // Check numbering ID and return data
        if ($numId >= '1') {
            return $data;
        }

        // No numbering found
        return false;
    }

    public static function isTocNumbering($paragraph){

        //if(isset($this->listItemIterator) && $paragraph->hasBookmark()) $this->listItemIterator = 1;

        $ctp = java_values($paragraph->getCTP()->toString());
        $hasBookmark = (strpos($ctp, '<w:bookmarkStart') !== false) ? true : false;
        return $hasBookmark;
    }

    /**
     * @param $abstractNum
     * @param $numberingInfo
     * @return array
     */
    public static function extractListProperties($abstractNum, $numberingInfo)
    {
        if (is_object($abstractNum)) {
            $numXml = ListHelper::extractCTAbstractNum($abstractNum);
            $level = $numXml->xpath('wlvl');
            $ilvl = $numXml->xpath('wlvl/wnumFmt');
            $ilvlSymbol = $numXml->xpath('wlvl/wlvlText');
            $listSymbol = $ilvlSymbol[$numberingInfo['lvl']]["wval"];
            $listIndentation =ListHelper::calculateListIndentation($numXml, $numberingInfo);
            if (is_array($ilvl)) {
                $listType = $ilvl[$numberingInfo['lvl']]["wval"];
                $listTypeStyle = ListHelper::getListType($listType, $listSymbol);
            }
            $listProperties = array('type' => $listTypeStyle, 'indentation' => $listIndentation);
        } else {
            $listProperties = array('type' => '', 'indentation' => '');
        }

        return $listProperties;
    }

    /**
     * Get list indentation from the list
     * @param $numXml
     * @param $numberingInfo
     * @return int
     */
    public static function calculateListIndentation($numXml, $numberingInfo)
    {
        $ipind = $numXml->xpath('wlvl/wpPr/wind');

        if (count($ipind) > 0) {
            try {
                $hanging = $ipind[$numberingInfo['lvl']]['whanging'];
                $wleft = $ipind[$numberingInfo['lvl']]['wleft'];
            } catch (Exception $exception) {
                $hanging = 1;
                $wleft = 1;
                //CakeLog::debug('[ListHelper::calculateListIndentation] No indentation found in xml');
            }

            $listIndentation = ((intval($wleft) / intval($hanging)) / 4) * 100;

            return intval($listIndentation);
        }
        return null;
    }


    /**
     * Get list type
     * @param $type
     * @param $listSymbol
     * @return string
     */
    public static function getListType($type, $listSymbol)
    {
        switch ($type) {
            case 'bullet':
                $listType = self::selectBulletType($listSymbol);
                break;
            case 'decimal':
                $listType = 'decimal';
                break;
            case 'upperRoman':
                $listType = 'upper-roman';
                break;
            case 'lowerRoman':
                $listType = 'lower-roman';
                break;
            case 'upperLetter':
                $listType = 'upper-alpha';
                break;
            case 'lowerLetter':
                $listType = 'lower-alpha';
                break;
            case 'decimalZero':
                $listType = 'decimal-leading-zero';
                break;
            default : $listType = '';
        }

        return $listType;
    }

    /**
     * @param $listSymbol
     * @return string
     */
    private static function selectBulletType($listSymbol)
    {
        switch ($listSymbol) {
            case 'o' :
                $bulletType = "circle";
                break;
            default :
                $bulletType = '';
                break;
        }
        return $bulletType;
    }

    /**
     * @param $abstractNum
     * @return mixed
     */
    public static function getCTAbstractNum($abstractNum){
        $stringNumbering = java_values($abstractNum->getCTAbstractNum()->ToString());
        return $stringNumbering;
    }

    public static function setListNumbering($sectionNumbering, $paragraphHTMLElement)
    {
        $sectionContainer = new HTMLElement(HTMLElement::SPAN);
        $sectionContainer->setInnerText($sectionNumbering);
        if($paragraphHTMLElement->getTagName() == 'header') {
            $elements = $paragraphHTMLElement->getLastElement()->getInnerElements();
            array_unshift($elements, $sectionContainer);
            $paragraphHTMLElement->getLastElement()->setInnerElementsArray($elements);
        }
    }


    /**
     * @param $abstractNum
     * @return SimpleXMLElement
     */
    public static function extractCTAbstractNum($abstractNum)
    {
        $stringNumbering = self::getCTAbstractNum($abstractNum);
        $numberingXml = str_replace('w:', 'w', $stringNumbering);
        $numXml = new SimpleXMLElement($numberingXml);
        return $numXml;
    }

}