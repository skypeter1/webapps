<?php

//include_once "XWPF/SDTToc.php";
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/SDTToc.php';

/**
 * Created by PhpStorm.
 * User: root
 * Date: 2/17/16
 * Time: 1:33 PM
 */
class TableOfContents
{
    private $toc;
    private $idList;
    private $tableContents;

    /**
     * @param $element
     */
    function __construct($element)
    {
        if (java_instanceof($element, java('org.apache.poi.xwpf.usermodel.XWPFSDT'))) {
            $toc = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFSDT');
            $this->toc = $toc;
            $this->idList = array();
            $this->tableContents = array();
        }
    }

    /**
     * @param $tocNumber
     * @return HTMLElement
     */
    public function processToc($tocNumber)
    {
        $paragraphs = $this->getStdContents($tocNumber);
        if (!empty($paragraphs)) {
            foreach ($paragraphs as $paragraph) {
                $this->processTocHeader($paragraph);
                $this->processTocEntries($paragraph);
            }
        }
        $tocTable = $this->constructTOC();
        return $tocTable;
    }

    /**
     * @param $paragraph
     */
    private function processTocEntries($paragraph)
    {
        if (!empty($paragraph->whyperlink)) {
            if (!empty($paragraph->whyperlink)) {
                $tocRuns = $paragraph->whyperlink->xpath('wr');
                if (!empty($tocRuns)) {
                    $numberRow = count($tocRuns) - 2;
                    $textDescription = "";
                    foreach ($tocRuns as $key => $run) {
                        if (!empty($run->wt)) {
                            if ($key == 0) {
                                $tocNumber = (string)$run->wt;
                            } elseif ($numberRow == $key) {
                                $tocPages = (string)$run->wt;
                            } else {
                                $textDescription .= ((string)$run->wt) . " ";
                            }
                        }
                    }
                    $this->tableContents['tocEntries'][] = array('number' => $tocNumber, 'description' => $textDescription, 'page' => $tocPages);
                }
            }
        }
    }

    /**
     * @return SimpleXMLElement
     */
    private function getDocumentXML()
    {
        $strDocument = java_values($this->toc->getDocument()->getDocument()->toString());
        $objectXml = str_replace('w:', 'w', $strDocument);
        $documentXML = new SimpleXMLElement($objectXml);
        return $documentXML;
    }

    /**
     * @param $tocNumber
     * @return mixed
     */
    private function getStdContents($tocNumber)
    {
        $document = $this->getDocumentXML();
        $valueTest = $document->xpath("wbody/wsdt/wsdtPr/wid");

        foreach ($valueTest as $id) {
            $this->idList[] = (string)$id['wval'];
        }
        $tocXml = $document->xpath("wbody/wsdt")[$tocNumber];
        $paragraphs = "";
        foreach ($tocXml as $entry) {
            $wp = $entry->xpath("wp");
            if (count($wp) > 0) $paragraphs = $wp;
        }

        return $paragraphs;
    }

    /**
     * @return HTMLElement
     */
    public function getSimpleTableContainer()
    {
        $final = java_values($this->toc->getContent()->getText());
        $tocContainer = new HTMLElement(HTMLElement::DIV);
        $tocContainer->setInnerText(nl2br($final));
        return $tocContainer;
    }

    /**
     * Return an array with two keys
     * tocHeader - which contains the Header name of the Table of Contents
     * tocEntries - which contains a compound array of the TOC entries with the internal keys: number,description and page
     * @return array
     */
    public function getTocData()
    {
        return $this->tableContents;
    }

    /**
     * @param $paragraph
     */
    private function processTocHeader($paragraph)
    {
        $headParagraph = $paragraph->xpath("wr");
        if (!empty($headParagraph)) {
            foreach ($headParagraph as $run) {
                if (!empty($run->xpath("wt"))) {
                    $headText = (string)$run->wt;
                    $this->tableContents['tocHeader'] = $headText;
                }
            }
        }
    }

    /**
     * @return HTMLElement
     */
    private function constructTOC()
    {
        $tableContents = $this->tableContents;
        $tocTable = new HTMLElement(HTMLElement::TABLE);
        $tocTable->setAttribute('width', '40%');
        foreach ($tableContents as $field => $contents) {
            switch ($field) {
                case 'tocHeader':
                    $headerRow = $this->constructTOCHeader($contents);
                    $tocTable->addInnerElement($headerRow);
                    break;

                case 'tocEntries':
                    foreach ($contents as $entry) {
                        $bodyRow = $this->constructTOCEntries($entry);
                        $tocTable->addInnerElement($bodyRow);
                    }
                    break;
            }
        }
        return $tocTable;
    }

    /**
     * @param $contents
     * @return HTMLElement
     */
    private function constructTOCHeader($contents)
    {
        $headerRow = new HTMLElement(HTMLElement::TR);
        $headerCell = new HTMLElement(HTMLElement::TD);

        $textContainer = new HTMLElement(HTMLElement::SPAN);
        $textContainer->setInnerText($contents);
        $textContainer->setAttribute('style', 'font-size:14pt;font-weight:bold');

        $headerRow->addInnerElement($headerCell);
        $headerCell->setAttribute('colspan', 3);
        $headerCell->addInnerElement($textContainer);
        return $headerRow;
    }

    /**
     * @param $entry
     * @return HTMLElement
     */
    private function constructTOCEntries($entry)
    {
        $bodyRow = new HTMLElement(HTMLElement::TR);
        $numberCell = new HTMLElement(HTMLElement::TD);
        $numberCell->setInnerText($entry['number']);

        $descriptionCell = new HTMLElement(HTMLElement::TD);
        $description = $entry['description'];
        while (strlen($description) < 140) {
            $description .= ".";
        }
        $descriptionCell->setInnerText($description);

        $pageCell = new HTMLElement(HTMLElement::TD);
        $pageCell->setInnerText($entry['page']);

        $bodyRow->addInnerElement($numberCell);
        $bodyRow->addInnerElement($descriptionCell);
        $bodyRow->addInnerElement($pageCell);
        return $bodyRow;
    }

}