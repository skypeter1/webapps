<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/STDCellDropDownList.php';

/**
 * @author Peter Arboleda.
 * User: Peter
 * Date: 10/12/15
 * Time: 2:19 PM
 */
class XWPFSDTCell
{
    private $sdtCell;
    private $rowXml;
    private $mainStyleSheet;

    /**
     * @param $cell
     * @param $xml
     * @throws Exception
     */
    function __construct($cell, $xml)
    {
        $sdtCell = java_cast($cell, 'org.apache.poi.xwpf.usermodel.XWPFSDTCell');
        $this->sdtCell = $sdtCell;
        $this->rowXml = $xml;
        if (!is_object($this->sdtCell)) {
            throw new Exception("[XWPFSDTCell::new XWPFSDTCell(Java cell)] Fail on create STDCell");
        }
    }

    public function setMainStyleSheet($styleSheet)
    {
        $this->mainStyleSheet = $styleSheet;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        $content = java_values($this->sdtCell->getContent());
        return $content;
    }

    /**
     * @return array|null|object
     */
    private function getSDTCell()
    {
        return $this->sdtCell;
    }

    /**
     * @return mixed
     */
    private function getDocumentString()
    {
        $parentXml = $this->rowXml;
        return $parentXml;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getXML()
    {
        $parentXml = $this->getDocumentString();
        $parentXml = str_replace('w:', 'w', $parentXml);
        $xml = new SimpleXMLElement($parentXml);
        return $xml;
    }

    public function parseHeaderTableCell($stdRow)
    {

        $stdCellContainer = new HTMLElement(HTMLElement::TD);
        $tdStyle = new StyleClass();
        $wsdt = $stdRow->xpath('wsdtPr/walias');

        $st = $stdRow->xpath('wsdtContent/wtc/wtcPr/wtcBorders')[0];
        $borders = $this->getBorderProperties($st);
        if (!is_null($borders) and is_array($borders)) {
            if (array_key_exists('bottom', $borders)) {
                $tdStyle->setAttribute("border-bottom", "1px " . HWPFWrapper::getBorder($borders['bottom']['val']) . " #" . $borders['bottom']['color']);
            }
        }

        $backgroundColor = $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
        $tdStyle->setAttribute('background-color', "#" . $backgroundColor);

        $textBox = "";
        foreach ($wsdt as $walias) {
            $charText = $walias[0]['wval'];
            $textBox .= (string)$charText;
        }
        $stdCellContainer->setInnerText($textBox);

        //Add class to style sheet
        if (isset($this->mainStyleSheet)) {
            $cnm = $this->mainStyleSheet->getClassName($tdStyle);
            $stdCellContainer->setClass($cnm);
        }

        $stdCellContainer->setAttribute('colspan', '2');

        return $stdCellContainer;
    }

    /**
     * @return HTMLElement
     */
    public function parseSDTCell()
    {
        $stdTableContainer = new HTMLElement(HTMLElement::TABLE);
        $STDTableRows = $this->getSTDRows();

        $stdRowContainerHeaderFirstRow = new HTMLElement(HTMLElement::TR);
        $stdRowContainerHeader = new HTMLElement(HTMLElement::TR);
        $stdRowContainer = new HTMLElement(HTMLElement::TR);

        foreach ($STDTableRows as $stdRow) {

            /****** SDT Header TOP ******/
            $stdCellContainer = $this->parseHeaderTableCell($stdRow);
            //Add cell to the row
            $stdRowContainerHeader->addInnerElement($stdCellContainer);

            /****** SDT ********/
//            $wsdtTableLook = $this->selectTableLook($stdRow);
//            var_dump($wsdtTableLook);
//
//            switch($wsdtTableLook){
//                case 'normal':
//                    $stdCellInfoContainer = $this->parseTableHeaderContent($stdRow);
//                    break;
//                case 'firstRow':
//                    $stdRowContainerHeaderFirstRow = $this->parseTableHeaderContent($stdRow);
//                    //Add cell to the row
//                    $stdRowContainerHeaderFirstRow->addInnerElement($stdRowContainerHeaderFirstRow);
//                    break;
//            }
            $stdCellInfoContainer = $this->parseSDT($stdRow);
            if (is_array($stdCellInfoContainer)) {
                var_dump(count($stdCellInfoContainer));
                $rowID = count($stdCellInfoContainer);
                if ($rowID < count($stdCellInfoContainer)) {
                    echo $rowID;
                    $rowID++;
                    //var_dump($stdCellInfoContainer[$rowID]);
                    if (is_a($stdCellInfoContainer[$rowID], 'HTMLElement')) {
                       // var_dump($stdCellInfoContainer[$rowID]);
                        //var_dump($rowID);
                       // echo $rowID;
                        $stdRowContainerHeaderFirstRow->addInnerElement($stdCellInfoContainer[$rowID]);

                    }
                }
            } else {
                //Add cell to the row
                $stdRowContainer->addInnerElement($stdCellInfoContainer);
            }

            /****** SDT Contents******/
            $stdCellContentContainer = $this->parseSDTContents($stdRow);
            //Add cell to the row
            $stdRowContainer->addInnerElement($stdCellContentContainer);
        }

        //Add rows to the table
        if (is_object($stdRowContainerHeader)) {
            $stdTableContainer->addInnerElement($stdRowContainerHeader);
        }
        if (is_object($stdRowContainerHeaderFirstRow) and isset($stdRowContainerHeaderFirstRow)) {
            $stdTableContainer->addInnerElement($stdRowContainerHeaderFirstRow);
        }
        $stdTableContainer->addInnerElement($stdRowContainer);

        return $stdTableContainer;
    }

    public function selectTableLook($stdRow)
    {

        $wtblLook = $stdRow->xpath('wsdtContent/wtc/wtbl/wtblPr/wtblLook')[0];
        $conditionalFormatting = null;

        if (!is_null($wtblLook)) {
            $wval = (string)$wtblLook['wval'];
            $wfirstRow = (string)$wtblLook['wfirstRow'];
            $wlastRow = (string)$wtblLook['wlastRow'];
            $wfirstColumn = (string)$wtblLook['wfirstColumn'];
            $wlastColumn = (string)$wtblLook['wlastColumn'];
            $wnoHBand = (string)$wtblLook['wnoHBand'];
            $wnoVBand = (string)$wtblLook['wnoVBand'];
            if ($wfirstRow == 1 and $wfirstColumn == 1 and $wnoVBand == 1) $conditionalFormatting = 'firstRow';
            if ($wfirstRow == 0 and $wfirstColumn == 0 and $wnoVBand == 0) $conditionalFormatting = 'normal';
        }
        return $conditionalFormatting;
    }

    /**
     * @param $stdRow
     * @return HTMLElement
     */
    private function parseSDTContents($stdRow)
    {

        $stdCellContentContainer = new HTMLElement(HTMLElement::TD);
        $wsdtContentTable = $stdRow->xpath('wsdtContent/wtc/wtbl')[0];

        $conditionalFormatting = $this->selectTableLook($stdRow);

        switch ($conditionalFormatting) {
//            case 'firstRow':
//                $stdCellContentContainer = $this->parseSTDParagraphsFirstRow($wsdtContentTable, $stdCellContentContainer);
//                break;
            case 'normal':
                $stdCellContentContainer = $this->parseSTDParagraphs($wsdtContentTable, $stdCellContentContainer);
                break;
        }

        return $stdCellContentContainer;
    }

    public function parseSTDParagraphsFirstRow($wsdtContentTable, $stdCellContentContainer)
    {

        foreach ($wsdtContentTable as $key => $sdtParagraph) {
            $sdtFields = $sdtParagraph->xpath('wsdt/wsdtPr');

            if (!empty($sdtFields)) {
                $choiceContainer = $this->parseChoiceFields($sdtParagraph);
            }
            $stdParagraphContainer = new HTMLElement(HTMLElement::P);
            $paragraphChars = $sdtParagraph->xpath('wsdt/wsdtContent/wtc/wp/wr/wt');

            if (isset($choiceContainer) and !is_null($choiceContainer)) {
                $stdCellContentContainer->addInnerElement($choiceContainer);
                $stdParagraphContainer->setAttribute('style', 'display:none');
            }

            if (!empty($paragraphChars)) {
                $textBoxContent = '';
                foreach ($paragraphChars as $char) {
                    $textBoxContent .= (string)$char;
                }
                $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($textBoxContent, ENT_COMPAT | ENT_XHTML));
                $stdParagraphContainer->setInnerText($text);
                $stdCellContentContainer->addInnerElement($stdParagraphContainer);
            }
        }

        return $stdCellContentContainer;
    }

    public function parseSTDParagraphs($wsdtContentTable, $stdCellContentContainer)
    {

        foreach ($wsdtContentTable as $key => $sdtParagraph) {
            $sdtFields = $sdtParagraph->xpath('wsdt/wsdtPr');

            if (!empty($sdtFields)) {
                $choiceContainer = $this->parseChoiceFields($sdtParagraph);
            }
            $stdParagraphContainer = new HTMLElement(HTMLElement::P);
            $paragraphChars = $sdtParagraph->xpath('wsdt/wsdtContent/wtc/wp/wr/wt');
            //$choiceContainer = $this->parseChoiceFields($sdtParagraph);

            if (isset($choiceContainer) and !is_null($choiceContainer)) {
                $stdCellContentContainer->addInnerElement($choiceContainer);
                $stdParagraphContainer->setAttribute('style', 'display:none');
            }

            if (!empty($paragraphChars)) {
                $textBoxContent = '';
                foreach ($paragraphChars as $char) {
                    $textBoxContent .= (string)$char;
                }
                $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($textBoxContent, ENT_COMPAT | ENT_XHTML));
                $stdParagraphContainer->setInnerText($text);
                $stdCellContentContainer->addInnerElement($stdParagraphContainer);
            }
        }

        return $stdCellContentContainer;
    }

    /**
     * @param $stdRow
     * @return HTMLElement
     */
    private function parseSDT($stdRow)
    {
        $wsdtTableLook = $this->selectTableLook($stdRow);
        switch ($wsdtTableLook) {
            case 'normal':
                $stdCellInfoContainer = $this->parseTableHeaderContent($stdRow);
                break;
            case 'firstRow':
                $stdCellInfoContainer = $this->parseBodySDT($stdRow);
                break;
        }

        return $stdCellInfoContainer;
    }

    public function parseBodySDT($stdRow)
    {

        $cells = array();
        //$stdCellInfoContainer = new HTMLElement(HTMLElement::TD);
        $wsdtContentParagraphs = $stdRow->xpath('wsdtContent/wtc/wtbl/wtr/wsdt/wsdtPr/walias');

        foreach ($wsdtContentParagraphs as $sdtParagraph) {
            $stdCellInfoContainer = new HTMLElement(HTMLElement::TD);
            //var_dump($sdtParagraph);
            //Paragraph Conversion
            $stdParagraphContainer = new HTMLElement(HTMLElement::P);
            $backgroundColor = $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
            $stdCellInfoContainer->setAttribute('style', 'background-color:#' . $backgroundColor);

            //Setting text to the cell
            $paragraphContent = (string)$sdtParagraph[0]['wval'];
            $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($paragraphContent, ENT_COMPAT | ENT_XHTML));
            $stdParagraphContainer->setInnerText($text);
            $stdCellInfoContainer->addInnerElement($stdParagraphContainer);
            $cells[] = $stdCellInfoContainer;
        }
        //var_dump($cells);
        return $cells;
    }


    public function parseTableHeaderContent($stdRow)
    {

        $stdCellInfoContainer = new HTMLElement(HTMLElement::TD);
        $wsdtContentParagraphs = $stdRow->xpath('wsdtContent/wtc/wtbl/wtr/wsdt/wsdtPr/walias');

        foreach ($wsdtContentParagraphs as $sdtParagraph) {
            //Paragraph Conversion
            $stdParagraphContainer = new HTMLElement(HTMLElement::P);
            $backgroundColor = $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
            $stdCellInfoContainer->setAttribute('style', 'background-color:#' . $backgroundColor);

            //Setting text to the cell
            $paragraphContent = (string)$sdtParagraph[0]['wval'];
            $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($paragraphContent, ENT_COMPAT | ENT_XHTML));
            $stdParagraphContainer->setInnerText($text);
            $stdCellInfoContainer->addInnerElement($stdParagraphContainer);
        }

        return $stdCellInfoContainer;
    }


    public function getBorderProperties($tcBorders)
    {
        $borders = array();
        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $bottomVal = $tcBorders->xpath("wbottom")[0]["wval"];
            $bottomSize = $tcBorders->xpath("wbottom")[0]["wsz"];
            $bottomSpace = $tcBorders->xpath("wbottom")[0]["wspace"];
            $bottomColor = $tcBorders->xpath("wbottom")[0]["wcolor"];

            $borders['bottom']['val'] = (is_object($bottomVal)) ? (string)$bottomVal : "";
            $borders['bottom']['size'] = (is_object($bottomSize)) ? (string)$bottomSize : "";
            $borders['bottom']['space'] = (is_object($bottomSpace)) ? (string)$bottomSpace : "";
            $borders['bottom']['color'] = (is_object($bottomColor)) ? (string)$bottomColor : "";
//            $topVal = $tcBorders->xpath("wtop")[0]["wval"];
//            $topSize = $tcBorders->xpath("wtop")[0]["wsz"];
//            $topSpace = $tcBorders->xpath("wtop")[0]["wspace"];
//            $topColor = $tcBorders->xpath("wtop")[0]["wcolor"];
//
//            $borders['top']['val'] = (is_object($topVal)) ? (string)$topVal : "";
//            $borders['top']['size'] = (is_object($topSize)) ? (string)$topSize : "";
//            $borders['top']['space'] = (is_object($topSpace)) ? (string)$topSpace : "";
//            $borders['top']['color'] = (is_object($topColor)) ? (string)$topColor : "";
//
//            $rightVal = $tcBorders->xpath("wright")[0]["wval"];
//            $rightSize = $tcBorders->xpath("wright")[0]["wsz"];
//            $rightSpace = $tcBorders->xpath("wright")[0]["wspace"];
//            $rightColor = $tcBorders->xpath("wright")[0]["wcolor"];
//
//            $borders['right']['val'] = (is_object($rightVal)) ? (string)$rightVal : "";
//            $borders['right']['size'] = (is_object($rightSize)) ? (string)$rightSize : "";
//            $borders['right']['space'] = (is_object($rightSpace)) ? (string)$rightSpace : "";
//            $borders['right']['color'] = (is_object($rightColor)) ? (string)$rightColor : "";
//
//            $leftVal = $tcBorders->xpath("wleft")[0]["wval"];
//            $leftSize = $tcBorders->xpath("wleft")[0]["wsz"];
//            $leftSpace = $tcBorders->xpath("wleft")[0]["wspace"];
//            $leftColor = $tcBorders->xpath("wleft")[0]["wcolor"];
//
//            $borders['left']['val'] = (is_object($leftVal)) ? (string)$leftVal : "";
//            $borders['left']['size'] = (is_object($leftSize)) ? (string)$leftSize : "";
//            $borders['left']['space'] = (is_object($leftSpace)) ? (string)$leftSpace : "";
//            $borders['left']['color'] = (is_object($leftColor)) ? (string)$leftColor : "";
        }

        return $borders;
    }

    /**
     * @param $rowXml
     * @return HTMLElement|null
     */
    public function parseChoiceFields($rowXml)
    {
        $choiceContainer = null;
        $dropDown = $rowXml->xpath('wsdt/wsdtPr')[0];

        if (count($dropDown) > 0) {
            foreach ($dropDown as $key => $drop) {
                if ($key == "wdropDownList") {
                    $dropDownList = new STDCellDropDownList($drop);
                    $choiceContainer = $dropDownList->parseDropDownList();
                }
            }
        }

        return $choiceContainer;
    }

    public function processSTDStyles($stdRow)
    {
        $backgroundColor = $stdRow->xpath('wsdt/wtc/wtcPr');
        $styles = array();
        // $styles['backgroundColor'] =
        return $styles;
    }

    /**
     * @param $stdRow
     * @return mixed
     */
    public function getSTDTableCells($stdRow)
    {
        $wsdtContent = $stdRow->xpath('wsdtContent/wtc/wtbl/wtr/wsdt');
        return $wsdtContent;
    }

    /**
     * @return SimpleXMLElement[]
     */
    public function getSTDRows()
    {
        $tableXML = $this->getXML();
        $rows = $tableXML->xpath('wsdt');
        return $rows;
    }

}