<?php

/**
 * Created by Peter.
 * User: root
 * Date: 10/9/15
 * Time: 1:16 PM
 */
class XWPFTableRow
{
    private $row;
    private $mainStyleSheet;

    /**
     * @param $row
     * @throws Exception
     */
    function __construct($row){
        if (java_instanceof($row, java('org.apache.poi.xwpf.usermodel.XWPFTableRow'))) {
            $this->row = $row;
        } else{
            throw new Exception("[XWPFTableRow::new XWPFTableRow] Fail on create row");
        }
    }

    /**
     * @param $stylesheet
     */
    private function setStyleSheet($stylesheet){
        $this->mainStyleSheet = $stylesheet;
    }

    /**
     * @return mixed
     */
    public function getCtRow() {
        $ctRow = java_values($this->row->getCtRow()->ToString());
        return $ctRow;
    }

    private function getHeight(){
        $height = java_values($this->row->getHeight());
        return $height;
    }

    public function getTableCells(){
        $cells = java_values($this->row->getTableCells());
        return $cells;
    }

    public function getTableICells(){
        $tableICells =  java_values($this->row->getTableICells());
        return $tableICells;
    }

    public function isCantSplitRow(){
        $isCantSplitRow = java_values($this->row->isCantSplitRow());
        return $isCantSplitRow;
    }

    public function isRepeatHeader(){
        $isRepeatHeader = java_values($this->row->isRepeatHeader());
        return $isRepeatHeader;
    }


    public function getRowCnfStyle(){
        $rowXml = $this->getXMLRowObject();
        var_dump($rowXml);
        $wtrPr = $rowXml->xpath("wtrPr");
        //var_dump($rowXml);
        if(is_null($rowXml)){
           // var_dump($this->getCtRow());
        }
        if(!is_null($wtrPr)){
            //$cnfStyle = $wtrPr[0]->xpath('wcnfStyle');
            //var_dump($cnfStyle);


//            if(!is_null($cnfStyle)){
//                //var_dump($cnfStyle);
//                var_dump($cnfStyle[0]['wval']);
//            }
        }
//        var_dump($cnfStyle);
//        $properties = (!empty($cnfStyle)) ? (string)$cnfStyle['wval'] : null;
//        return $properties;


//        $cnfStyleProperties = array();
//        if(!empty($cnfStyle)) {
//            $cnfStyleProperties = array(
//                'tableLookVal' => (string)$cnfStyle['wval'],
//                'firstRow' => (string)$cnfStyle['wfirstRow'],
//                'lastRow' => (string)$cnfStyle['wlastRow'],
//                'firstColumn' => (string)$cnfStyle['wfirstColumn'],
//                'lastColumn' => (string)$cnfStyle['wlastColumn'],
//                'oddVBand' => (string)$cnfStyle['woddVBand'],
//                'evenVBand' => (string)$cnfStyle['wevenVBand'],
//                'oddHBand' => (string)$cnfStyle['woddHBand'],
//                'evenHBand' => (string)$cnfStyle['woddHBand'],
//                'firstRowFirstColumn' => (string)$cnfStyle['wfirstRowFirstColumn'],
//                'firstRowLastColumn' => (string)$cnfStyle['wfirstRowLastColumn'],
//                'lastRowFirstColumn' => (string)$cnfStyle['wlastRowFirstColumn'],
//                'lastRowLastColumn' => (string)$cnfStyle['wlastRowLastColumn']
//            );
//        }
//        return $cnfStyleProperties;
    }

    /**
     * @return StyleClass
     */
    public function processRowStyle(){
        $rowStyle =  new StyleClass();
        $rowXml = $this->getXMLRowObject();

        $height = $rowXml->xpath('wtrPr/wtrHeight');
        $height = (isset($height[0]['wval'])) ? round(intval($height[0]['wval']) / 15.1) . 'px' : 'auto';

        $rowStyle->setAttribute('height',$height);

        return $rowStyle;
    }

    public function getCell($pos) {
        $cell = java_values($this->row->getCell($pos));
        if(is_object($cell)) {
            return $cell;
        }else{
            return null;
        }
    }

    /**
     * @return SimpleXMLElement
     */
    public function getXMLRowObject()
    {
        $rowXmlStr = $this->getCtRow();
        $tmpRowXmlStr = str_replace('w:', 'w', $rowXmlStr);
        $rowXml = new SimpleXMLElement($tmpRowXmlStr);
        return $rowXml;
    }

}