<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFParagraph.php';

/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/12/15
 * Time: 11:50 AM
 */
class XWPFTableCell
{
    private $cell;
    private $mainStyleSheet;
    private $xmlCell;

    function __construct($cell, $mainStyleSheet)
    {
        if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
            $this->cell = $cell;
        } else {
            throw new Exception("[XWPFTableCell::new XWPFTableCell] Cell cannot be null");
        }
        $this->mainStyleSheet = $mainStyleSheet;
        $this->xmlCell = $this->getXMLCellObject();
    }

    public function getPart()
    {
        $part = java_values($this->cell->getPart());
        return $part;
    }

    public function getBodyElements()
    {
        $bodyElements = java_values($this->cell->getBodyElements());
        return $bodyElements;
    }

    public function getColor()
    {
        $color = java_values($this->cell->getColor());
        return $color;
    }

    public function getCTTc()
    {
        $cctc = java_values($this->cell->getCTTc()->ToString());
        return $cctc;
    }

    public function getParagraphArray($pos)
    {
        $paragraph = java_values($this->cell->getParagraphArray($pos));
        return $paragraph;
    }

    public function getParentTableRow()
    {
        $tableRow = java_values($this->cell->getTableRow());
        return $tableRow;
    }

    public function getPartType()
    {
        $partType = java_values($this->cell->getPartType());
        return $partType;
    }

    public function getText()
    {
        $text = java_values($this->cell->getText());
        $cellText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $cellText;
    }

    public function getParagraphs()
    {
        $paragraphs = java_values($this->cell->getParagraphs());
        return $paragraphs;
    }

    public function getTables()
    {
        $tables = java_values($this->cell->getTables());
        return $tables;
    }

    public function getTextRecursively()
    {
        $recursiveText = java_values($this->cell->getTextRecursively());
        return $recursiveText;
    }

    public function getXWPFDocument()
    {
        $xwpfDocument = java_values($this->cell->getXWPFDocument());
        return $xwpfDocument;
    }

    public function getXWPFDocumentToString()
    {
        $xwpfDocumentString = java_values($this->getXWPFDocument()->ToString());
        return $xwpfDocumentString;
    }

    public function getXMLCellObject()
    {
        $xml = $this->getCTTc();
        $cellCTTc = str_replace('w:', 'w', $xml);
        $cellXML = new SimpleXMLElement($cellCTTc);
        return $cellXML;
    }

    private function getBorderProperties($tcBorders)
    {
        $borders = array();
        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $borderKeys = array(
                "bottom" => "wbottom",
                "top" => "wtop",
                "right" => "wright",
                "left" => "wleft",
                "insideH" => "winsideH",
                "insideV" => "winsideV"
            );
            foreach ($borderKeys as $key => $borderKey) {
                if (array_key_exists($borderKey, $tcBorders)) {
                    $val = $tcBorders->xpath($borderKey)[0]["wval"];
                    $size = $tcBorders->xpath($borderKey)[0]["wsz"];
                    $space = $tcBorders->xpath($borderKey)[0]["wspace"];
                    $color = $tcBorders->xpath($borderKey)[0]["wcolor"];

                    $borders[$key]['val'] = (is_object($val)) ? (string)$val : "";
                    $borders[$key]['size'] = (is_object($size)) ? (string)round($size / 4) : "";
                    $borders[$key]['space'] = (is_object($space)) ? (string)$space : "";
                    $borders[$key]['color'] = (is_object($color)) ? (string)$color : "";
                    if ($borders[$key]['color'] == "auto") $borders[$key]['color'] = "000000";
                }
            }
        }

        return $borders;
    }

    public function getCellWidht()
    {
        $cellWidht = array();
        $widht = round($this->xmlCell->xpath("wtcPr/wtcW")[0]["ww"] / 15.1);
        $type = $this->xmlCell->xpath("wtcPr/wtcW")[0]["wtype"];
        if (!is_null($widht)) $cellWidht['value'] = (int)$widht;
        if (!is_null($type)) $cellWidht['type'] = (string)$type;

        return $cellWidht;
    }

    private function getColspan()
    {
        $gridspan = $this->xmlCell->xpath('*/wgridSpan');
        $colspan = ($gridspan) ? ((string)$gridspan[0]['wval']) : "";

        return $colspan;
    }

    private function getRowspan()
    {
        $wvmerge = $this->xmlCell->xpath('*/wvMerge');
        $rowspan = ($wvmerge) ? ((string)$wvmerge[0]['wval']) : "";
        return $rowspan;
    }


    /**
     * @return StyleClass
     */
    public function processTableCellStyle()
    {
        $cellClass = new StyleClass();

        $cell_width = $this->getCellWidht();
        if (!empty($cell_width)) $cellClass->setAttribute('width', $cell_width['value'] . 'px');

        $color = $this->getColor();
        if (!is_null($color) && $color != "auto") $cellClass->setAttribute("background-color", "#" . "$color");

        $result = $this->getXMLCellObject()->xpath("wtcPr/wtcBorders");
        $tcBorders = (count($result) > 0) ? $result[0] : array();
        $cellBorders = $this->getBorderProperties($tcBorders);

        //Assign border styles
        if (!is_null($cellBorders)) {
            $tableBorders = array('left', 'right', 'top', 'bottom');
            foreach ($cellBorders as $key => $border) {
                if (in_array($key, $tableBorders)) {
                    $cellClass->setAttribute("border-" . $key,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }

        return $cellClass;
    }

    /**
     * @return array
     */
    private function getTextOrientationRules()
    {
        $wtcPr = $this->getXMLCellObject()->xpath("wtcPr/wtextDirection");
        $textOrientation = (count($wtcPr) > 0) ? (string)$wtcPr[0]["wval"] : "";
        switch ($textOrientation) {
            case 'tbRl':
                $textOrientationRules = array('transform' => 'rotate(90deg)', 'float' => 'right');
                break;
            case 'btLr':
                $textOrientationRules = array('transform' => 'rotate(270deg)', 'float' => 'left');
                break;
            default:
                $textOrientationRules = array();
                break;
        }
        return $textOrientationRules;
    }

    /**
     * @return StyleClass
     */
    private function extractParagraphStyles()
    {
        $paragraphStyle = new StyleClass();
        $textOrientation = $this->getTextOrientationRules();
        if (!empty($textOrientation)) {
            $paragraphStyle->setAttribute("transform", $textOrientation['transform']);
            $paragraphStyle->setAttribute("float", $textOrientation['float']);
            $paragraphStyle->setAttribute("display", "inline-block");
        }

        return $paragraphStyle;
    }


    public function parseTableCell()
    {
        $cellContainer = new HTMLElement(HTMLElement::TD);
        $paragraphs = $this->getParagraphs();

        foreach ($paragraphs as $javaParagraph) {
            $paragraph = new XWPFParagraph($javaParagraph, $this->mainStyleSheet);
            $paragraphContainer = $paragraph->parseParagraph();
            $styleClass = $paragraph->processParagraphStyle();
            $paragraphStyle = $this->extractParagraphStyles();

            // Merge inherited styles
            if ($paragraphStyle->hasAttributes()) $styleClass = $styleClass->mergeStyleClass($paragraphStyle);
            $className = $this->mainStyleSheet->getClassName($styleClass);
            $paragraphContainer->setClass('textframe horizontal common_style1 ' . $className);

            // Add id attribute to container for this paragraph
            if (!empty($paragraph->getId())) $paragraphContainer->setAttribute('id', 'div_' . $paragraph->getId());
            $cellContainer->addInnerElement($paragraphContainer);
        }

        //Set Attributes
        $colspan = $this->getColspan();
        if (!empty($colspan)) $cellContainer->setAttribute('colspan', $colspan);

        //TODO Find values for rowspan
//        $rowspan = $this->getRowspan();
//        if($rowspan == "restart") $cellContainer->setAttribute('rowspan', 2);

        return $cellContainer;
    }

}