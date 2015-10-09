<?php

/**
 * @author Peter Arboleda
 * Date: 10/9/15
 * Time: 10:41 AM
 */

class XWPFTable
{
    private $javaTable;
    private $tableKey;

    /**
     * @param $element
     * @param $key
     * @param $localJava
     */
    function __construct($element, $key, $localJava){

        try {
            $table = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFTable');
        }catch (Exception $ex){
            var_dump($ex);
            $ex->getMessage();
            return null;
        }
        $this->javaTable = $table;
        $this->tableKey = $key;
        include($localJava);
    }

    /**
     * @return $xmlTable
     */
    public function getCTTbl(){
        try {
            $xmlTable = java_values($this->javaTable->getCTTbl()->ToString());
        }catch(Exception $ex){
            $ex->getMessage();
            $xmlTable = null;
        }
            return $xmlTable;
    }

    /**
     * @return $styleId
     */
    public function getStyleID(){
        try {
            $styleId = java_values($this->javaTable->getStyleID());
        }catch (Exception $ex){
            $ex->getMessage();
            $styleId = null;
        }
        return $styleId;
    }

    /**
     * @return $width
     */
    public function getWidth() {
        try{
            $width = java_values($this->javaTable->getWidth());
        }catch (Exception $ex){
            $width = null;
        }
        return $width;
    }

    /**
     * @return $rows
     */
    public function getRows(){
        try{
            $rows = java_values($this->javaTable->getRows()->toArray());
        }catch (Exception $ex){
            $rows = null;
        }
        return $rows;
    }

    /**
     * @return $rows
     */
    public function getCellMarginBottom(){
        try{
            $marginBottom = java_values($this->javaTable->getCellMarginBottom());
        }catch (Exception $ex){
            $marginBottom = null;
        }
        return $marginBottom;
    }

    /**
     * Parse Word table
     * @staticvar   int Unique ID
     * @param       object  Table
     * @param       string  Element key
     * @return      HTMLElement
     */

    public function parseTable()
    {
        if(is_object($this->javaTable)){

            $table = $this->javaTable;
            $rows = $this->getRows();

            foreach($rows as $row){

            }


        }else{
            throw new Exception("[XWPFTable::parseTable]");
        }
    }

}