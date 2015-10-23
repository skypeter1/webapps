<?php

/**
 * Created by Peter.
 * User: root
 * Date: 10/22/15
 * Time: 12:32 PM
 */
class XWPFRun
{
    private $run;
    private $mainStyleSheet;

    function __construct($run, $mainStyleSheet)
    {
        if (java_instanceof($run, java('org.apache.poi.xwpf.usermodel.XWPFRun'))) {
            $this->run = $run;
        }
        $this->mainStyleSheet = $mainStyleSheet;
    }

    public function getText()
    {
        $text = java_values($this->run->getText(0));
        $runText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $runText;
    }

    public function parseRun()
    {
        $runContainer = new HTMLElement(HTMLElement::SPAN);
        $text = $this->getText();
        $runStyle = $this->processRunStyle($this->run);
        $runContainer->setInnerText($text);
        $runContainer->setClass($runStyle);
        return $runContainer;
    }

    private function processRunStyle($run)
    {
        //Get Run xml
        $charXml = java_values($run->getCTR()->ToString());
        $charXml = str_replace('w:', 'w', $charXml);
        $xml = new SimpleXMLElement($charXml);

        // Get color
        $color = java_values($run->getColor());
        if (is_null($color)) {
            $color = 'black';
        } else {
            $color = '#' . $color;
        }

        //Get background highlight color
        $runCharShadows = $xml->xpath("wrPr/wshd");
        if (!empty($runCharShadows)) {
            $backgroundColor = $runCharShadows[0]['wfill'];
        } else {
            $backgroundColor = null;
        }

        // Get style
        $isItalic = java_values($run->isItalic());
        $fontStyle = ($isItalic) ? 'italic' : 'normal';

        // Get weight
        $isBold = java_values($run->isBold());
        $fontWeight = ($isBold) ? 'bold' : 'normal';

        // Get char font family and size
        $fontFamily = java_values($run->getFontFamily());
        $fontSize = floor(java_values($run->getFontSize()));

        // Get underline
        $underlined_type = java_values($run->getUnderline()->getValue());

        //Default underline set to none
        if (!is_int($underlined_type)) $underlined_type = 12;
        $underlined = HWPFWrapper::getUnderline($underlined_type);

        // Create empty class, and attach it to span element
        $styleClass = new StyleClass();

        // Set style attributes
        if ($color != 'black' and $color != '#000000') $styleClass->setAttribute("color", $color);
        if ($fontWeight != 'normal') $styleClass->setAttribute("font-weight", $fontWeight);
        if ($fontStyle != 'normal') $styleClass->setAttribute("font-style", $fontStyle);
        if ($fontSize) $styleClass->setAttribute("font-size", (string)$fontSize . "pt");
        if ($underlined != 'none') $styleClass->setAttribute("text-decoration", $underlined);
        if (!is_null($backgroundColor)) $styleClass->setAttribute("background-color", "#" . $backgroundColor->__toString());
        if ($fontFamily) {
            $styleFont = HWPFWrapper::getFontFamily($fontFamily);
            $styleClass->setAttribute("font-family", $styleFont);
        }

        $className = $this->mainStyleSheet->getClassName($styleClass);

        return $className;
    }
}