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

    function __construct($cell, $mainStyleSheet)
    {
        if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
            $this->cell = $cell;
        } else {
            throw new Exception("[XWPFTableCell::new XWPFTableCell] Cell cannot be null");
        }
        $this->mainStyleSheet = $mainStyleSheet;
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

    public function getBorderProperties()
    {
        $borders = array();
        $tcBorders = $this->getXMLCellObject()->xpath("wtcPr/wtcBorders")[0];

        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $bottomVal = $tcBorders->xpath("wbottom")[0]["wval"];
            $bottomSize = $tcBorders->xpath("wbottom")[0]["wsz"];
            $bottomSpace = $tcBorders->xpath("wbottom")[0]["wspace"];
            $bottomColor = $tcBorders->xpath("wbottom")[0]["wcolor"];

            $borders['bottom']['val'] = (is_object($bottomVal)) ? (string)$bottomVal : "";
            $borders['bottom']['size'] = (is_object($bottomSize)) ? (string)round($bottomSize / 4) : "";
            $borders['bottom']['space'] = (is_object($bottomSpace)) ? (string)$bottomSpace : "";
            $borders['bottom']['color'] = (is_object($bottomColor)) ? (string)$bottomColor : "";

            $topVal = $tcBorders->xpath("wtop")[0]["wval"];
            $topSize = $tcBorders->xpath("wtop")[0]["wsz"];
            $topSpace = $tcBorders->xpath("wtop")[0]["wspace"];
            $topColor = $tcBorders->xpath("wtop")[0]["wcolor"];

            $borders['top']['val'] = (is_object($topVal)) ? (string)$topVal : "";
            $borders['top']['size'] = (is_object($topSize)) ? (string)$topSize : "";
            $borders['top']['space'] = (is_object($topSpace)) ? (string)$topSpace : "";
            $borders['top']['color'] = (is_object($topColor)) ? (string)$topColor : "";

            $rightVal = $tcBorders->xpath("wright")[0]["wval"];
            $rightSize = $tcBorders->xpath("wright")[0]["wsz"];
            $rightSpace = $tcBorders->xpath("wright")[0]["wspace"];
            $rightColor = $tcBorders->xpath("wright")[0]["wcolor"];

            $borders['right']['val'] = (is_object($rightVal)) ? (string)$rightVal : "";
            $borders['right']['size'] = (is_object($rightSize)) ? (string)$rightSize : "";
            $borders['right']['space'] = (is_object($rightSpace)) ? (string)$rightSpace : "";
            $borders['right']['color'] = (is_object($rightColor)) ? (string)$rightColor : "";

            $leftVal = $tcBorders->xpath("wleft")[0]["wval"];
            $leftSize = $tcBorders->xpath("wleft")[0]["wsz"];
            $leftSpace = $tcBorders->xpath("wleft")[0]["wspace"];
            $leftColor = $tcBorders->xpath("wleft")[0]["wcolor"];

            $borders['left']['val'] = (is_object($leftVal)) ? (string)$leftVal : "";
            $borders['left']['size'] = (is_object($leftSize)) ? (string)$leftSize : "";
            $borders['left']['space'] = (is_object($leftSpace)) ? (string)$leftSpace : "";
            $borders['left']['color'] = (is_object($leftColor)) ? (string)$leftColor : "";
        }

        return $borders;
    }

    public function getCellWidht()
    {
        $cellWidht = array();
        $cellXML = $this->getXMLCellObject();
        $widht = round($cellXML->xpath("wtcPr/wtcW")[0]["ww"] / 15.1);
        $type = $cellXML->xpath("wtcPr/wtcW")[0]["wtype"];
        if (!is_null($widht)) $cellWidht['value'] = (int)$widht;
        if (!is_null($type)) $cellWidht['type'] = (string)$type;

        return $cellWidht;
    }

    private function getColspan()
    {
        $cellXML = $this->getXMLCellObject();
        $gridspan = $cellXML->xpath('*/wgridSpan');
        $colspan = ($gridspan) ?((string)$gridspan[0]['wval']) : "";

        return $colspan;
    }

    private function getRowspan()
    {
        $cellXML = $this->getXMLCellObject();
        $wvmerge = $cellXML->xpath('*/wvMerge');
        $rowspan = ($wvmerge) ?((string)$wvmerge[0]['wval']) : "";
        return $rowspan;
    }


    public function processTableCellStyle()
    {
        $cellClass = new StyleClass();

        $cell_width = $this->getCellWidht();
        if(!empty($cell_width)) $cellClass->setAttribute('width', $cell_width['value'] . 'px');

        $color = $this->getColor();
        if(!is_null($color)) $cellClass->setAttribute("background-color", "#" . "$color");

        $xml = $this->getCTTc();
        //var_dump($xml);
        //$borders = $this->getBorderProperties();
        //$cellClass->setAttribute('border-bottom', $borders['bottom']['size']);

        return $cellClass;
    }


    public function parseTableCell()
    {
        $cellContainer = new HTMLElement(HTMLElement::TD);
        $paragraphs = $this->getParagraphs();

        foreach ($paragraphs as $javaParagraph) {
            $paragraph = new XWPFParagraph($javaParagraph, $this->mainStyleSheet);
            $paragraphContainer = $paragraph->parseParagraph();
            $cellContainer->addInnerElement($paragraphContainer);
        }

        //Set Attributes
        $colspan = $this->getColspan();
        if(!empty($colspan)) $cellContainer->setAttribute('colspan', $colspan);

        //TODO Find values for rowspan
//        $rowspan = $this->getRowspan();
//        if($rowspan == "restart") $cellContainer->setAttribute('rowspan', 2);

        return $cellContainer;
    }

}