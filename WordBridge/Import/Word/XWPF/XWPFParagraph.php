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
    private $id;

    /**
     * @param $paragraph
     * @param $mainStyleSheet
     */
    function __construct($paragraph, $mainStyleSheet)
    {
        if (java_instanceof($paragraph, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {
            $this->paragraph = $paragraph;
        }
        $this->mainStyleSheet = $mainStyleSheet;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    private function getRuns()
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

    /**
     * @return string
     */
    public function getText()
    {
        $text = java_values($this->paragraph->getText());
        $paragraphText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $paragraphText;
    }

    /**
     * @return mixed
     */
    public function getDocumentStyles()
    {
        $styles = java_values($this->paragraph->getBody()->getXWPFDocument()->getStyles());
        return $styles;
    }

    public function getStyleID()
    {
        $styleId = java_values($this->paragraph->getStyleID());
        return $styleId;
    }

    /**
     * @return string
     */
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

    /**
     * @return StyleClass
     */
    public function processParagraphStyle()
    {
        $paragraphStyle = new StyleClass();
        $lineSpacing = $this->getLineSpacing();
        $alignment = $this->getAlignment();

        $indentation = java_values($this->paragraph->getIndentationFirstLine());

        if ($indentation > 0) $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');
        $paragraphStyle->setAttribute("line-height", $lineSpacing . "%");
        $paragraphStyle->setAttribute("text-align", $alignment);
        $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');

        if ($this->getStyleID() != null) {
            $style = $this->getDocumentStyles()->getStyle($this->paragraph->getStyleID());
            $styleXML = java_values($style->getCTStyle()->toString());
            //var_dump($styleXML);
            //var_dump($this->getText());

        } else {
            $paragraphStyle->setAttribute("margin-bottom", '0.14in');
        }

        //$styleXML = java_values($this->paragraph->getCTP()->toString());
        //var_dump($styleXML);
        //var_dump($styleXML);
        //var_dump($this->paragraph->getText());

        return $paragraphStyle;
    }




    /**
     * @return string
     */
    private function getAlignment()
    {
        $alignment = java_values($this->paragraph->getAlignment()->getValue());
        $justification = HWPFWrapper::getAlignment($alignment);
        return $justification;
    }

    /**
     * @return HTMLElement
     */
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