<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableRow.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableCell.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFSDTCell.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFStyle.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/Helpers/TableStyleHelper.php';

/**
 * @author Peter Arboleda
 * Date: 10/9/15
 * Time: 10:41 AM
 */

class XWPFTable
{
    private $javaTable;
    private $tableKey;
    private $mainStyleSheet;
    private $element;

    /**
     * @param $element
     * @param $key
     * @param $mainStyleSheet
     * @throws Exception
     */
    function __construct($element, $key, $mainStyleSheet)
    {
        try {
            $table = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFTable');
        } catch (Exception $ex) {
            $ex->getMessage();
            return null;
        }
        $this->javaTable = $table;
        $this->tableKey = $key;
        $this->element = $element;
        $this->mainStyleSheet = $mainStyleSheet;
    }

    /**
     * @return $xmlTable
     */
    public function getCTTbl()
    {
        try {
            $xmlTable = java_values($this->javaTable->getCTTbl()->ToString());
        } catch (Exception $ex) {
            $ex->getMessage();
            $xmlTable = null;
        }
        return $xmlTable;
    }

    /**
     * @param $propertyPath
     * @return array|SimpleXMLElement
     */
    private function getProperty($propertyPath){
        $result = $this->getXMLObject()->xpath($propertyPath);
        $property = (count($result)>0) ? $result[0] : array();
        return $property;
    }

    /**
     * @return SimpleXMLElement
     */
    private function getXMLObject()
    {
        $styleXML = $this->getCTTbl();
        $styleXML = str_replace('w:', 'w', $styleXML);
        $xml = new SimpleXMLElement($styleXML);
        return $xml;
    }

    /**
     * @return $styleId
     */
    public function getStyleID()
    {
        try {
            $styleId = java_values($this->javaTable->getStyleID());
        } catch (Exception $ex) {
            $ex->getMessage();
            $styleId = null;
        }
        return $styleId;
    }

    /**
     * @return $width
     */
    public function getWidth()
    {
        try {
            $width = java_values($this->javaTable->getWidth());
        } catch (Exception $ex) {
            $width = null;
        }
        return $width;
    }

    /**
     * @return $rows
     */
    public function getRows()
    {
        try {
            $rows = java_values($this->javaTable->getRows()->toArray());
        } catch (Exception $ex) {
            $rows = null;
        }
        return $rows;
    }

    /**
     * @return mixed
     */
    public function getDocumentStyles(){
        $styles = java_values($this->javaTable->getBody()->getXWPFDocument()->getStyles());
        return $styles;
    }

    /**
     * @param $number
     * @return string
     */
    private function checkOddOrEvenNumber($number){
        $type = ($number % 2 == 0) ? "even" : "odd";
        return $type;
    }

    /**
     * @return HTMLElement|null
     * @throws Exception
     */
    public function parseTable()
    {
        if (is_object($this->javaTable)) {

            $container = new HTMLElement(HTMLElement::TABLE);
            $styleClass = $this->processTableStyles();
            $tableStyleClass = (!empty($styleClass)) ? $styleClass['table'] : $styleClass;

            if(is_a($tableStyleClass,'StyleClass')){
                $tableClassName = $this->mainStyleSheet->getClassName($tableStyleClass);
                $container->setClass($tableClassName);
            }

            $rows = $this->getRows();
            $numberOfRows = count($rows) - 1;

            foreach ($rows as $rowNumber => $row) {

                $conditionalTableRowStyleClass = $this->processConditionalFormat($rowNumber, $styleClass, $numberOfRows);
                $rowContainer = new HTMLElement(HTMLElement::TR);
                $xwpfRow = new XWPFTableRow($row);
                $rowStyle = $xwpfRow->processRowStyle();
                $rowClassName = $this->mainStyleSheet->getClassName($rowStyle);
                $rowContainer->setClass($rowClassName);

                $cells = $xwpfRow->getTableICells();

                foreach ($cells as $cellNumber => $cell) {

                    if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
                        $cellContainer = $this->processXWPFTableCell($cell, $styleClass, $conditionalTableRowStyleClass);
                        $rowContainer->addInnerElement($cellContainer);
                    }

                    if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFSDTCell'))) {
                        $rowXml = java_values($row->getCtRow()->ToString());
                        $xwpfSdtCell = new XWPFSDTCell($cell, $rowXml);
                        $xwpfSdtCell->setMainStyleSheet($this->mainStyleSheet);
                        $container = $xwpfSdtCell->parseSDTCell();
                    }
                }

                //Set default size
                $container->setAttribute('style','width : 100%');
                $container->addInnerElement($rowContainer);
            }
            return $container;

        } else {
            throw new Exception("[XWPFTable::parseTable] No Java Table instance");
        }
    }

    /**
     * @return array
     */
    public function processTableStyles()
    {
        $tableStyles = array();

        // Check if table has style ID assigned
        if ($this->getStyleID() != null) {

            // Get style name
            $style = $this->getDocumentStyles()->getStyle($this->javaTable->getStyleID());

            $xwpfStyle = new XWPFStyle($style);
            $tableStyleClass = $xwpfStyle->processStyle();
            $tableStyleClass->setAttribute("border-collapse", "collapse");
            $tableStyles['table'] = $tableStyleClass;

            $tableCellStyleClass = $xwpfStyle->processTableCellStyle();
            $tableStyles['cell'] = $tableCellStyleClass;

            $tableCellConditionalFormat = $xwpfStyle->extractConditionalFormat();
            if(!empty($tableCellConditionalFormat)){
                $tableStyles['conditionalFormat'] = $tableCellConditionalFormat;
            }
        }else {
            $tableStyleClass = new StyleClass();
            $tableStyleClass->setAttribute("border-collapse", "collapse");
            $tcBorders = $this->getProperty("wtblPr/wtblBorders");
            $borders = TableStyleHelper::getBorderProperties($tcBorders);
            $tableStyleClass = TableStyleHelper::assignTableBorderStyles($borders,$tableStyleClass);
            $tableStyles['table'] = $tableStyleClass;

            $tableCellStyleClass =  new StyleClass();
            $tableCellStyleClass = TableStyleHelper::assignCellBorderStyles($borders,$tableCellStyleClass);
            $tableStyles['cell'] = $tableCellStyleClass;
        }

        return $tableStyles;
    }

    /**
     * @param $format
     * @return StyleClass
     * @internal param $borders
     * @internal param $conditionalTableCellStyleClass
     * @internal param $backgroundColor
     */
    private function applyConditionalFormatStyles($format)
    {
        $conditionalTableCellStyleClass = new StyleClass();

        // Get internal values
        $borders = $format["borders"];
        $backgroundColor = $format["backgroundColor"];

        // Apply first row styles
        if (!empty($borders) && is_array($borders)) {
            $conditionalCellBorders = array('bottom', 'top', 'right', 'left');
            foreach ($borders as $key => $border) {
                if (in_array($key, $conditionalCellBorders)) {
                    $conditionalTableCellStyleClass->setAttribute("border-" . $key,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }

        if (strlen($backgroundColor)) {
            $conditionalTableCellStyleClass->setAttribute("background-color", '#' . $backgroundColor);
        }

        return $conditionalTableCellStyleClass;
    }

    /**
     * @param $rowNumber
     * @param $styleClass
     * @param $numberOfRows
     * @return StyleClass
     */
    private function processConditionalFormat($rowNumber, $styleClass, $numberOfRows)
    {
        $conditionalTableRowStyleClass = new StyleClass();
        $typeOfRowNumber = $this->checkOddOrEvenNumber($rowNumber);

        //Check for conditional formatting
        if (array_key_exists('conditionalFormat', $styleClass)) {
            $conditionalFormatForRows = $styleClass['conditionalFormat'];

            foreach ($conditionalFormatForRows as $rowFormat) {
                $typeOfRowFormat = $rowFormat["type"];
                if (($typeOfRowNumber == "even") && $typeOfRowFormat == "band2Horz") {
                    $conditionalTableRowStyleClass = $this->applyConditionalFormatStyles($rowFormat);
                } elseif (($typeOfRowNumber == "odd") && $typeOfRowFormat == "band1Horz") {
                    $conditionalTableRowStyleClass = $this->applyConditionalFormatStyles($rowFormat);
                } elseif (($rowNumber == 0) && $typeOfRowFormat == "firstRow") {
                    $conditionalTableRowStyleClass = $this->applyConditionalFormatStyles($rowFormat);
                } elseif (($rowNumber == $numberOfRows) && $typeOfRowFormat == "lastRow") {
                    $conditionalTableRowStyleClass = $this->applyConditionalFormatStyles($rowFormat);
                }
            }
        }

        return $conditionalTableRowStyleClass;
    }

    /**
     * @param $cell
     * @param $styleClass
     * @param $conditionalTableRowStyleClass
     * @return HTMLElement
     */
    private function processXWPFTableCell($cell, $styleClass, $conditionalTableRowStyleClass)
    {
        $xwpfCell = new XWPFTableCell($cell, $this->mainStyleSheet);
        $cellContainer = $xwpfCell->parseTableCell();
        $tableCellStyle = $xwpfCell->processTableCellStyle();
        $cellStyles = (array_key_exists('cell', $styleClass)) ? $styleClass['cell'] : array();

        if (isset($this->mainStyleSheet) and is_a($this->mainStyleSheet, 'StyleSheet')) {

            //Merge standard cell borders
            $cellStyleClass = (!empty($cellStyles)) ? $tableCellStyle->mergeStyleClass($cellStyles) : $tableCellStyle;

            //Set conditional format to cells of the current row if is set
            if (isset($conditionalTableRowStyleClass) && $conditionalTableRowStyleClass->hasAttributes()) {
                $cellStyleClass = $cellStyleClass->mergeStyleClass($conditionalTableRowStyleClass);
            }

            $className = $this->mainStyleSheet->getClassName($cellStyleClass);
            $cellContainer->setClass($className);
            return $cellContainer;
        }
        return $cellContainer;
    }

}