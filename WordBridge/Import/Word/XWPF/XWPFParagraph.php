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
    private function getCTP()
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

    private function getParagraphStyle()
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

        if ($indentation > 0) $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');
        $paragraphStyle->setAttribute("line-height", $lineSpacing . "%");
        $paragraphStyle->setAttribute("text-align", $alignment);
        $paragraphStyle->setAttribute("text-indent", round($indentation / 11) . 'px');
        $paragraphStyle->setAttribute("margin-bottom", '0.14in');

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
     * @internal param $styleName
     */
    private function isHeadline()
    {
        $styleName = $this->getStyleName();
        $isHeadline = (strpos(strtolower($styleName), 'heading') !== false) ? true : false;
        return $isHeadline;
    }

    /**
     * @return string
     */
    private function getStyleName()
    {
        $styleName = "";
        if ($this->hasStyleID()) {
            $style = $this->getParagraphStyle();
            $styleName = java_values($style->getName());
        }
        return $styleName;
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
    private function getCustomStyle()
    {
        $style = $this->getParagraphStyle();
        $xwpfStyle = new XWPFStyle($style);
        $paragraphCustomStyleClass = $xwpfStyle->processStyle();
        return $paragraphCustomStyleClass;
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
    private function parseRunCharacters($container)
    {
        $runs = $this->getIRuns();
        foreach ($runs as $run) {
            $pictures = java_values($run->getEmbeddedPictures());
            $xwpfRun = new XWPFRun($run, $this->mainStyleSheet);
            $charRunHTMLElement = $xwpfRun->parseRun();

            if (count($pictures) > 0) {
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            } else if (@isset($prevCharRunHTMLElement) && $charRunHTMLElement->getClass() == $prevCharRunHTMLElement->getClass()) {
                $container->getLastElement()->addInnerText($charRunHTMLElement->getInnerText());
            } else {
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            }
        }
        return $container;
    }

}