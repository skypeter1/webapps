<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFRun.php';
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/22/15
 * Time: 11:15 AM
 */
class XWPFParagraph
{
    private $paragraph;
    private $mainStyleSheet;

    function __construct($paragraph, $mainStyleSheet)
    {
        if (java_instanceof($paragraph, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {
            $this->paragraph = $paragraph;
        }
        $this->mainStyleSheet = $mainStyleSheet;
    }

    public function getRuns(){
        $runs = java_values($this->paragraph->getRuns());
        return $runs;
    }

    public  function getText(){
        $text = java_values($this->paragraph->getText());
        $paragraphText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $paragraphText;
    }

    public function parseParagraph(){
        $paragraphContainer = new HTMLElement(HTMLElement::P);
        $runs = $this->getRuns();

        foreach($runs as $run){
            $xwpfRun = new XWPFRun($run,$this->mainStyleSheet);
            $runContainer = $xwpfRun->parseRun();
            $paragraphContainer->addInnerElement($runContainer);
        }

        return $paragraphContainer;
    }
}