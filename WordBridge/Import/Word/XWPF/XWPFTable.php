<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableRow.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableCell.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFSDTCell.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFStyle.php';

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

    public function getDocumentStyles(){
        $styles = java_values($this->javaTable->getBody()->getXWPFDocument()->getStyles());
        return $styles;
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

            foreach ($rows as $key => $row) {

                $rowContainer = new HTMLElement(HTMLElement::TR);
                $xwpfRow = new XWPFTableRow($row);
                $cells = $xwpfRow->getTableICells();

                foreach ($cells as $cell) {

                    if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
                        $xwpfCell = new XWPFTableCell($cell,$this->mainStyleSheet);
                        $cellContainer = $xwpfCell->parseTableCell();
                        $tableCellStyle = $xwpfCell->processTableCellStyle();
                        $cellStyles = (!empty($styleClass)) ? $styleClass['cell'] : $styleClass;

                        //Set cell style class
                        if(isset($this->mainStyleSheet) and is_a($this->mainStyleSheet,'StyleSheet')){
                            $cellStyleClass = $tableCellStyle->mergeStyleClass($cellStyles);
                            $className = $this->mainStyleSheet->getClassName($cellStyleClass);
                            $cellContainer->setClass($className);
                        }
                        $rowContainer->addInnerElement($cellContainer);
                    }

                    if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFSDTCell'))) {
                        $rowXml = java_values($row->getCtRow()->ToString());
                        $xwpfSdtCell = new XWPFSDTCell($cell, $rowXml);
                        $container = $xwpfSdtCell->parseSDTCell();
                    }
                }
                $container->addInnerElement($rowContainer);
            }
            return $container;

        } else {
            throw new Exception("[XWPFTable::parseTable] No Java Table instance");
        }
    }

    public function processTableStyles()
    {
        $tableStyles = array();

        // Check if table has style ID assigned
        if ($this->getStyleID() != null) {

            // Get style name
            $style = $this->getDocumentStyles()->getStyle($this->javaTable->getStyleID());

            $xwpfStyle = new XWPFStyle($style);
            $tableStyleClass = $xwpfStyle->processStyle();
            $tableStyleClass->setAttribute("border-collapse", "inherit");
            $tableStyleClass->setAttribute("width", "100%");
            $tableStyles['table'] = $tableStyleClass;

            $tableCellStyleClass = $xwpfStyle->processTableCellStyle();
            $tableStyles['cell'] = $tableCellStyleClass;
        }

        return $tableStyles;
    }

}