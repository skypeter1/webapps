<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableRow.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTableCell.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFSDTCell.php';
//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/StyleClass.php';
/**
 * @author Peter Arboleda
 * Date: 10/9/15
 * Time: 10:41 AM
 */
class XWPFTable
{
    private $javaTable;
    private $tableKey;
    private $mainStyle;
    private $element;


    function __construct($element, $key, $mainStyle)
    {

        try {
            $table = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFTable');
        } catch (Exception $ex) {
            $ex->getMessage();
            return null;
        }

        if (is_a($mainStyle, 'StyleSheet')) {
            $this->mainStyle = $mainStyle;
        } else {
            throw new Exception('The parameter mainStyle must be a StyleSheet');
            return null;
        }
        $this->javaTable = $table;
        $this->tableKey = $key;
        $this->element = $element;
    }

    /**
     * @return StyleSheet
     */
    public function getMainStyle()
    {
        return $this->mainStyle;
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

    /**
     * @return $rows
     */
    public function getCellMarginBottom()
    {
        try {
            $marginBottom = java_values($this->javaTable->getCellMarginBottom());
        } catch (Exception $ex) {
            $marginBottom = null;
        }
        return $marginBottom;
    }


    /**
     * @return HTMLElement|null
     * @throws Exception
     */
    public function parseTable()
    {
        if (is_object($this->javaTable)) {

            $container = new HTMLElement(HTMLElement::TABLE);
            $tableStyleClass = $this->processTableStyles($this->javaTable);
            $tableClassName = $this->getMainStyle()->getClassName($tableStyleClass);
            $container->setClass($tableClassName);
            $rows = $this->getRows();

            foreach ($rows as $key => $row) {

                $rowContainer = new HTMLElement(HTMLElement::TR);
                $xwpfRow = new XWPFTableRow($row);
                $cells = $xwpfRow->getTableICells();

                foreach ($cells as $cell) {

                    if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
                        $cellContainer = $this->parseCell($cell);
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

    private function parseCell($cell)
    {
        $cellContainer = new HTMLElement(HTMLElement::TD);
        $xwpfCell = new XWPFTableCell($cell);
        $text = $xwpfCell->getText($cell);
        $color = $xwpfCell->getColor();
        var_dump($color);
        $cellContainer->setInnerText($text);

        return $cellContainer;
    }


    public function processTableStyles()
    {
        // Create new table style class
        $tableStyleClass = new StyleClass();
        return $tableStyleClass;
    }


}