<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFRun.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/Helpers/ParagraphHelper.php';

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
     * @param $id
     */
    function __construct($paragraph, $mainStyleSheet, $id)
    {
        if (java_instanceof($paragraph, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {
            $this->paragraph = $paragraph;
        }
        $this->id = $id;
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

    /**
     * @return mixed
     */
    private function getRuns()
    {
        $runs = java_values($this->paragraph->getRuns());
        return $runs;
    }

    private function getIRuns()
    {
        $iruns = java_values($this->paragraph->getIRuns());
        return $iruns;
    }

    /**
     * @return mixed
     */
    public function getCTP()
    {
        $ctp = java_values($this->paragraph->getCTP()->toString());
        return $ctp;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getXMLObject()
    {
        $ctp = $this->getCTP();
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

    public function getParagraphStyle()
    {
        $style = $this->getDocumentStyles()->getStyle($this->paragraph->getStyleID());
        return $style;
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
        $paddingLeft = round(java_values($this->paragraph->getFirstLineIndent()) / 20, 2);
        $marginLeft = round(java_values($this->paragraph->getIndentationLeft()) / 20, 2);

        if ($indentation > 0) $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');
        $paragraphStyle->setAttribute("line-height", $lineSpacing . "%");
        $paragraphStyle->setAttribute("text-align", $alignment);
        $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');
        $paragraphStyle->setAttribute("margin-bottom", '0.14in');
        $paragraphStyle->setAttribute('padding-left', $paddingLeft > 0 ? $paddingLeft.'px' : '5.4px');
        $paragraphStyle->setAttribute('margin-left', $marginLeft > 0 ? $marginLeft.'px' : null);

        if ($this->hasStyleID()) {
            $paragraphCustomStyleClass = $this->getCustomStyle();
            $paragraphStyle = $paragraphStyle->mergeStyleClass($paragraphCustomStyleClass);
        }

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
     * @return bool
     */
    public function hasBookmark()
    {
        $xml = $this->getCTP();
        $hasBookmark = (strpos($xml, '<w:bookmarkStart') !== false) ? true : false;
        return $hasBookmark;
    }

    /**
     * @return bool
     * @internal param $styleName
     */
    public function isHeadline()
    {
        $styleName = $this->getStyleName();
        $isHeadline = (strpos(strtolower($styleName), 'heading') !== false) ? true : false;
        return $isHeadline;
    }

    /**
     * @return string
     */
    public function getStyleName()
    {
        $styleName = "";
        if ($this->hasStyleID()) {
            $style = $this->getParagraphStyle();
            $styleName = java_values($style->getName());
        }
        return $styleName;
    }

    public function getNumId()
    {
        $numId = java_values($this->getNumID());
        return $numId;
    }


    /**
     * @return HTMLElement
     */
    private function selectParagraphContainer()
    {
        if ($this->hasStyleID()) {
            $styleName = $this->getStyleName();
            $paragraphContainer = ($this->isHeadline()) ? ParagraphHelper::selectHeadlineContainer($styleName) : new HTMLElement(HTMLElement::P);
        } else {
            $paragraphContainer = new HTMLElement(HTMLElement::P);
        }
        return $paragraphContainer;
    }

    /**
     * @return bool
     */
    private function hasStyleID()
    {
        $hasStyleId = (java_values($this->paragraph->getStyleID()) != null) ? true : false;
        return $hasStyleId;
    }

    /**
     * @return StyleClass
     */
    public function getCustomStyle()
    {
        $style = $this->getParagraphStyle();
        $xwpfStyle = new XWPFStyle($style);
        $paragraphCustomStyleClass = $xwpfStyle->processStyle();
        return $paragraphCustomStyleClass;
    }

    public function getNumberingFromStyle(){
        $style = $this->getParagraphStyle();
        $xwpfStyle = new XWPFStyle($style);
        $styleXml = $xwpfStyle->getXMLObject();
        $num = $styleXml->xpath("wpPr/wnumPr/wnumId");
        $numInfo = (count($num) > 0) ? $num : false;
        return $numInfo;
    }

    /**
     * @return HTMLElement
     */
    public function parseParagraph()
    {
        $container = $this->selectParagraphContainer();
        $paragraphContainer = $this->parseRunCharacters($container);
        $paragraphStyle = $this->processParagraphStyle();

        $className = $this->mainStyleSheet->getClassName($paragraphStyle);
        $paragraphContainer->setClass('textframe horizontal common_style1 ' . $className);
        $paragraphContainer->setAttribute('id', 'div_' . $this->getId());

        // Wrap inside header tag if is a headlines
        if ($this->isHeadline()) {
            $headline = $paragraphContainer;
            $paragraphContainer = new HTMLElement(HTMLElement::HEADER);
            $exists = $paragraphStyle->attributeExists('font-size');
            if (!$exists) $paragraphStyle->setAttribute("font-size", 'medium');
            $paragraphContainer->addInnerElement($headline);
        }

        return $paragraphContainer;
    }


    /**
     * @param $container
     * @return
     * @internal param $paragraphContainer
     */
    private function parseRunCharacters(HTMLElement $container)
    {
        $runs = $this->getIRuns();

        if (count($runs) > 0) {
            foreach ($runs as $run) {
                $pictures = java_values($run->getEmbeddedPictures());
                $xwpfRun = new XWPFRun($run, $this->mainStyleSheet);
                $charRunHTMLElement = $xwpfRun->parseRun();

                if ($charRunHTMLElement->getTagName() == 'sub' || 'sup') {
                    $container->addInnerElement($charRunHTMLElement);
                } else if (count($pictures) > 0) {
                    $container->addInnerElement($charRunHTMLElement);
                    $prevCharRunHTMLElement = clone $charRunHTMLElement;
                } else if (@isset($prevCharRunHTMLElement) && $charRunHTMLElement->getClass() == $prevCharRunHTMLElement->getClass()) {
                    $container->getLastElement()->addInnerText($charRunHTMLElement->getInnerText());
                } else {
                    $container->addInnerElement($charRunHTMLElement);
                    $prevCharRunHTMLElement = clone $charRunHTMLElement;
                }
            }
        } else {
            $container = new HTMLElement(HTMLElement::BR);
            //$container->addInnerText('<br/>');
//            var_dump(count($runs));
 //           var_dump($container);
//            $container->addInnerText('<br/>');
        }
        return $container;
    }

}