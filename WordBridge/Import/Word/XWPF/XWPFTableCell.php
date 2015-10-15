<?php

//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/HTMLElement.php';
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/12/15
 * Time: 11:50 AM
 */
class XWPFTableCell
{
    private $cell;

    function __construct($cell){
        if(is_object($cell)){
            $this->cell = $cell;
        }else{
            throw new Exception("[XWPFTableCell::new XWPFTableCell] Cell cannot be null");
        }
    }

    public function getPart(){
        $part= java_values($this->cell->getPart());
        return $part;
    }

    public function getBodyElements(){
        $bodyElements =  java_values($this->cell->getBodyElements());
        return $bodyElements;
    }

    public function getColor(){
        $color = java_values($this->cell->getColor());
        return $color;
    }

    public function getCTTc(){
        $cctc =  java_values($this->cell->getCTTc()->ToString());
        return $cctc;
    }

    public function getParagraphArray($pos){
        $paragraph = java_values($this->cell->getParagraphArray($pos));
        return $paragraph;
    }

    public function getTableRow() {
        $tableRow = java_values($this->cell->getTableRow());
        return $tableRow;
    }

    public function getPartType(){
        $partType =  java_values($this->cell->getPartType());
        return $partType;
    }

    public function getText() {
        $text = java_values($this->cell->getText());
        return $text;
    }

    public function getParagraphs(){
        $paragraphs = java_values($this->cell->getParagraphs());
        return $paragraphs;
    }

    public function getTables(){
        $tables = java_values($this->cell->getTables());
        return $tables;
    }

    public function getTextRecursively(){
        $recursiveText = java_values($this->cell->getTextRecursively());
        return $recursiveText;
    }

    public function getVerticalAlignment(){
        $verticalAlignment = java_values($this->cell->getVerticalAlignment());
        return $verticalAlignment;
    }

    public function getXWPFDocument(){
        $xwpfDocument =  java_values($this->cell->getXWPFDocument());
        return $xwpfDocument;
    }

    public function getXWPFDocumentToString(){
        $xwpfDocumentString = java_values($this->getXWPFDocument()->ToString());
        return $xwpfDocumentString;
    }

}