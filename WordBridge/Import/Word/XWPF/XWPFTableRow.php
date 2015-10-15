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

    function __construct($row){
        if(is_object($row)){
            $this->row = $row;
        }else{
            throw new Exception("[XWPFTableRow::new XWPFTableRow] Fail on create row");
        }
    }

    public function getCtRow() {
        $ctRow = java_values($this->row->getCtRow()->ToString());
        return $ctRow;
    }

    public function getHeight(){
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

    public function getCell($pos) {
        $cell = java_values($this->row->getCell($pos));
        if(is_object($cell)) {
            return $cell;
        }else{
            return null;
        }
    }

}