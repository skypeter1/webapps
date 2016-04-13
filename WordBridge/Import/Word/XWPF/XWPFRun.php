<?php

include_once "XWPFPicture.php";
/**
 * Created by Peter.
 * User: root
 * Date: 10/22/15
 * Time: 12:32 PM
 */
class XWPFRun
{
    protected $run;
    protected $mainStyleSheet;
    protected $runXml;

    private $isHyperLink;

    /**
     * @param $run
     * @param $mainStyleSheet
     */
    function __construct($run, $mainStyleSheet)
    {
        if (java_instanceof($run, java('org.apache.poi.xwpf.usermodel.XWPFRun'))) {
            $this->run = $run;
            $this->runXml = $this->getCTP();
        }
        $this->mainStyleSheet = $mainStyleSheet;
        $this->isHyperLink = java_instanceof($this->run, java('org.apache.poi.xwpf.usermodel.XWPFHyperlinkRun'));
    }

    /**
     * @return SimpleXMLElement
     */
    private function getXMLRun()
    {
        $charXml = str_replace('w:', 'w', $this->runXml);
        $runXml = new SimpleXMLElement($charXml);
        return $runXml;
    }

    /**
     * @return mixed
     */
    private function getCTP(){
        $charXml = java_values($this->run->getCTR()->ToString());
        return $charXml;
    }

    /**
     * @return HTMLElement
     */
    private function selectSubscriptContainer()
    {
        $subValue = $this->getSubscript();
        switch ($subValue) {
            case 2:
                $container = new HTMLElement(HTMLElement::SUP);
                break;
            case 3:
                $container = new HTMLElement(HTMLElement::SUB);
                break;
            default :
                $container = new HTMLElement(HTMLElement::SPAN);
                break;
        }
        return $container;
    }

    /**
     * @return bool
     */
    public function hasDefaultPageBreak()
    {
        $pageBreakMarkup = array('manual' => '<w:br w:type="page"/>', 'byPage' => '<w:lastRenderedPageBreak/>');
        $isByPage = (strpos($this->runXml, $pageBreakMarkup['byPage']) !== false) ? true : false;
        $isManual = (strpos($this->runXml, $pageBreakMarkup['manual']) !== false) ? true : false;
        $isPageBreak = ($isByPage) ? true : false;
        return $isPageBreak;
    }

    /**
     * Retrieve the vertical align value
     * baseline = 1 , superscript = 2, subscript = 3
     * @return mixed
     */
    private function getSubscript(){
        $subValue = java_values($this->run->getSubscript()->getValue());
        return $subValue;
    }

    /**
     * Retrieve the hyper link URL value
     * @return mixed
     */
    private function getHyperLinkURL()
    {
        if (is_null(java_values($this->run->getHyperlink($this->run->getDocument())))) {
            return '';
        }
        return java_values($this->run->getHyperlink($this->run->getDocument())->getURL());
    }

    /**
     * @return string
     */
    public function getText()
    {
        $text = java_values($this->run->getText(0));
        $runText = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        return $runText;
    }

    /**
     * @return HTMLElement
     */
    public function parseRun()
    {
        // Get and process pictures if there are any
        $pictures = java_values($this->run->getEmbeddedPictures());
        if (count($pictures) > 0) {
            foreach ($pictures as $key => $picture) {
                $path = XWPFToHTMLConverter::getDirectoryPath();
                $pictureContainer = new XWPFPicture($picture, $this->mainStyleSheet, $path);
                $container = $pictureContainer->processPicture();
                return $container;
            }
        }

        // Character parser
        $runContainer = new HTMLElement(HTMLElement::SPAN);
        if ($this->getSubscript() != 1) {
            $runContainer = $this->selectSubscriptContainer();
        } else {
            if ($this->isHyperLink) {
                $runContainer = new HTMLElement(HTMLElement::A);
                $runContainer->setAttribute('href', $this->getHyperLinkURL());
            }
        }

        $text = $this->getText();
        $addNewLine = (strlen($text) == 1 && (substr($text, -1, 1) == "\r" || ord(substr($text, -1, 1)) == HWPFWrapper::BEL_MARK)) ? true : false;
        if ($addNewLine) {
            $text .= '<br />';
        }
        $runStyle = $this->processRunStyle($this->run);
        $runContainer->setInnerText($text);
        $runContainer->setClass($runStyle);

        return $runContainer;
    }

    /**
     * @param $run
     * @return mixed
     */
    private function processRunStyle($run)
    {
        $xml = $this->getXMLRun();

        // Get color
        $color = java_values($run->getColor());
        $color = (is_null($color)) ? 'black' : '#' . $color;

        //Get background highlight color
        $runCharShadows = $xml->xpath("wrPr/wshd");
        $backgroundColor = (!empty($runCharShadows)) ? $runCharShadows[0]['wfill'] : null;
        if (is_null($backgroundColor)) {
            $runCharHighlight = $xml->xpath("wrPr/whighlight");
            $backgroundColor = (!empty($runCharHighlight)) ? (string)$runCharHighlight[0]['wval'] : null;
        }

        // Get StrikeThrough
        $runStrike = $xml->xpath("wrPr/wstrike");
        $isStrikeThrough = (!empty($runStrike)) ? true : false;

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

        // Default underline set to none
        if (!is_int($underlined_type)) $underlined_type = 12;
        $underlined = HWPFWrapper::getUnderline($underlined_type);

        // Hyper Link Default styles
        if($this->isHyperLink) {
            if(is_null(java_values($run->getColor()))) {
                $color = 'blue';
            }
        }

        // Create empty class, and attach it to span element
        $styleClass = new StyleClass();

        // Set style attributes
        if ($color != 'black' and $color != '#000000') $styleClass->setAttribute("color", $color);
        if ($fontWeight != 'normal') $styleClass->setAttribute("font-weight", $fontWeight);
        if ($fontStyle != 'normal') $styleClass->setAttribute("font-style", $fontStyle);
        if ($fontSize) $styleClass->setAttribute("font-size", (string)$fontSize . "pt");
        if ($underlined != 'none') $styleClass->setAttribute("text-decoration", $underlined);
        if (!is_null($backgroundColor)) {
            $background = (ctype_alpha($backgroundColor)) ? $backgroundColor : "#" . $backgroundColor->__toString();
            $styleClass->setAttribute("background-color", $background);
        }
        if ($fontFamily) {
            $styleFont = HWPFWrapper::getFontFamily($fontFamily);
            $styleClass->setAttribute("font-family", $styleFont);
        }
        if ($isStrikeThrough) $styleClass->setAttribute("text-decoration", "line-through");

        $className = $this->mainStyleSheet->getClassName($styleClass);

        return $className;
    }
}