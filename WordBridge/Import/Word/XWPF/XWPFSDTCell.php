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

    /**
     * @return HTMLElement
     */
    public function parseSDTCell()
    {
        $stdTableContainer = new HTMLElement(HTMLElement::TABLE);
        $STDTableRows = $this->getSTDRows();

        $stdRowContainerHeader = new HTMLElement(HTMLElement::TR);
        $stdRowContainer = new HTMLElement(HTMLElement::TR);

        foreach ($STDTableRows as $stdRow) {

            //Parse Header TOP of STD Table content
            $stdCellContainer = new HTMLElement(HTMLElement::TD);
            $wsdt = $stdRow->xpath('wsdtPr/walias');
            $styles = $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
            $stdCellContainer->setAttribute('style', 'background-color:#' . $styles);

            $textBox = "";
            foreach ($wsdt as $walias) {
                $charText = $walias[0]['wval'];
                $textBox .= (string)$charText;
            }
            $stdCellContainer->setInnerText($textBox);

            //Add cell to the row
            $stdRowContainerHeader->addInnerElement($stdCellContainer);

            /****** SDT ********/
            $stdCellInfoContainer = $this->parseSDT($stdRow);
            //Add cell to the row
            $stdRowContainer->addInnerElement($stdCellInfoContainer);

            /****** SDT Contents******/
            $stdCellContentContainer = $this->parseSDTContents($stdRow);
            //Add cell to the row
            $stdRowContainer->addInnerElement($stdCellContentContainer);
        }

        //Add rows to the table
        if (is_object($stdRowContainerHeader)) {
            $stdTableContainer->addInnerElement($stdRowContainerHeader);
        }
        $stdTableContainer->addInnerElement($stdRowContainer);

        return $stdTableContainer;
    }

    /**
     * @param $stdRow
     * @return HTMLElement
     */
    private function parseSDTContents($stdRow){

        $stdCellContentContainer = new HTMLElement(HTMLElement::TD);
        $wsdtContentTable = $stdRow->xpath('wsdtContent/wtc/wtbl')[0];

        foreach ($wsdtContentTable as $key => $sdtParagraph) {
            $sdtFields = $sdtParagraph->xpath('wsdt/wsdtPr');

            if(!empty($sdtFields)){
                $choiceContainer = $this->parseChoiceFields($sdtParagraph);
            }
            $stdParagraphContainer = new HTMLElement(HTMLElement::P);
            $paragraphChars = $sdtParagraph->xpath('wsdt/wsdtContent/wtc/wp/wr/wt');
            //$choiceContainer = $this->parseChoiceFields($sdtParagraph);

            if (!is_null($choiceContainer)) {
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
    private function parseSDT($stdRow){

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