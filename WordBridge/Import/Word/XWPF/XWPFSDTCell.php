<?php

//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/HTMLElement.php';
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
    function __construct($cell, $xml){
        $sdtCell = java_cast($cell, 'org.apache.poi.xwpf.usermodel.XWPFSDTCell');
        $this->sdtCell = $sdtCell;
        $this->rowXml = $xml;
        if(!is_object($this->sdtCell)){
            throw new Exception("[XWPFSDTCell::new XWPFSDTCell(Java cell)] Fail on create STDCell");
        }
    }

    /**
     * @return mixed
     */
    public function getContent(){
        $content = java_values($this->sdtCell->getContent());
        return $content;
    }

    /**
     * @return array|null|object
     */
    private function getSDTCell(){
        return $this->sdtCell;
    }

    /**
     * @return mixed
     */
    private function getDocumentString(){
        $parentXml = $this->rowXml;
        return $parentXml;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getXML(){
        $parentXml = $this->getDocumentString();
        $parentXml = str_replace('w:', 'w', $parentXml);
        $xml = new SimpleXMLElement($parentXml);
        return $xml;
    }

    /**
     * @return HTMLElement
     */
    public function parseSDTCell(){

        $stdTableContainer = new HTMLElement(HTMLElement::TABLE);
        $STDTableRows = $this->getSTDRows();

        $stdRowContainerHeader = new HTMLElement(HTMLElement::TR);
        $stdRowContainer = new HTMLElement(HTMLElement::TR);

            foreach($STDTableRows as $stdRow){

                //Parse Header TOP of STD Table content
                $stdCellContainer = new HTMLElement(HTMLElement::TD);
                $wsdt = $stdRow->xpath('wsdtPr/walias');
                $styles =  $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
                $stdCellContainer->setAttribute('style','background-color:#'.$styles);

                $textBox = "";
                foreach($wsdt as $walias){
                    $charText = $walias[0]['wval'];
                    $textBox .= (string)$charText;
                }
                $stdCellContainer->setInnerText($textBox);

                //Add cell to the row
                $stdRowContainerHeader->addInnerElement($stdCellContainer);

                /****** WSDTCONTENTS ********/
                $stdCellInfoContainer = new HTMLElement(HTMLElement::TD);
                $wsdtContentParagraphs = $stdRow->xpath('wsdtContent/wtc/wtbl/wtr/wsdt/wsdtPr/walias');

                foreach($wsdtContentParagraphs as $sdtParagraph) {
                    $stdParagraphContainer = new HTMLElement(HTMLElement::P);
                    $backgroundColor =  $stdRow->xpath('wsdtContent/wtc/wtcPr/wshd')[0]['wfill'];
                    $stdCellInfoContainer->setAttribute('style','background-color:#'.$backgroundColor);

                    //Setting text to the cell
                    $paragraphContent = (string)$sdtParagraph[0]['wval'];
                    $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($paragraphContent, ENT_COMPAT | ENT_XHTML));
                    $stdParagraphContainer->setInnerText($text);
                    $stdCellInfoContainer->addInnerElement($stdParagraphContainer);
                }

                //Add cell to the row
                $stdRowContainer->addInnerElement($stdCellInfoContainer);

                /******WSDT Contents******/

                $stdCellContentContainer = new HTMLElement(HTMLElement::TD);
                $wsdtContentTable = $stdRow->xpath('wsdtContent/wtc/wtbl')[0];

                foreach($wsdtContentTable as $sdtParagraph) {
                    $paragraphChars = $sdtParagraph->xpath('wsdt/wsdtContent/wtc/wp/wr/wt');

                    if (!empty($paragraphChars)) {

                        $stdParagraphContainer = new HTMLElement(HTMLElement::P);
                        $textBoxContent = '';
                        foreach ($paragraphChars as $char) {
                            $textBoxContent .= (string)$char;
                        }
                        $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($textBoxContent, ENT_COMPAT | ENT_XHTML));
                        $stdParagraphContainer->setInnerText($text);
                        $stdCellContentContainer->addInnerElement($stdParagraphContainer);
                    }
                }
                //Add cell to the row
                $stdRowContainer->addInnerElement($stdCellContentContainer);

            }
            //Add rows to the table
            if(is_object($stdRowContainerHeader)) {
                $stdTableContainer->addInnerElement($stdRowContainerHeader);
            }
            $stdTableContainer->addInnerElement($stdRowContainer);

        return $stdTableContainer;
    }

    public function processSTDStyles($stdRow){
        $backgroundColor =  $stdRow->xpath('wsdtContent/wtc/wtcPr');
        $styles = array();
       // $styles['backgroundColor'] =
        return $styles;
    }

    /**
     * @param $stdRow
     * @return mixed
     */
    public function getSTDTableCells($stdRow){
        $wsdtContent = $stdRow->xpath('wsdtContent/wtc/wtbl/wtr/wsdt');
        return $wsdtContent;
    }

    /**
     * @return SimpleXMLElement[]
     */
    public function getSTDRows(){
        $tableXML = $this->getXML();
        $rows = $tableXML->xpath('wsdt');
        return $rows;
    }

}