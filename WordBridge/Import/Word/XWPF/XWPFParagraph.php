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

    public function getRuns()
    {
        $runs = java_values($this->paragraph->getRuns());
        return $runs;
    }

    public function getXMLObject()
    {
        $ctp = java_values($this->paragraph->getCTP()->toString());
        $paragraphXml = str_replace('w:', 'w', $ctp);
        $xml = new SimpleXMLElement($paragraphXml);
        return $xml;
    }

    public function getText()
    {
        $text = java_values($this->paragraph->getText());
        $paragraphText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $paragraphText;
    }

    private function getLineSpacing()
    {
        $xml = $this->getXMLObject();
        $PSpacingStyle = $xml->xpath('*/wspacing');

        if ($PSpacingStyle) {
            $PSpacingLineValue = ((string)$PSpacingStyle[0]['wline']);
            if ($PSpacingLineValue == '240') {
                $lineValue = '100';
            } else {
                $tmpSpace = ((int)$PSpacingLineValue - 240);
                $addSpace = $tmpSpace + 100;
                $lineValue = ((string)$addSpace);
            }

            return $lineValue;
        }
    }

    public function processParagraphStyle()
    {
        $xml = $this->getXMLObject();
        $paragraphStyle = new StyleClass();
        $lineSpacing = $this->getLineSpacing();
        $paragraphStyle->setAttribute("line-height", $lineSpacing . "%");

        return $paragraphStyle;
    }

    public function parseParagraph()
    {
        $paragraphContainer = new HTMLElement(HTMLElement::P);
        $runs = $this->getRuns();

        foreach ($runs as $run) {
            $xwpfRun = new XWPFRun($run, $this->mainStyleSheet);
            $runContainer = $xwpfRun->parseRun();
            $paragraphContainer->addInnerElement($runContainer);
        }

        return $paragraphContainer;
    }
}