<?php

/**
 * Created by PhpStorm.
 * User: Peter
 * Date: 10/23/15
 * Time: 2:47 PM
 */
class XWPFStyle
{
    private $style;

    function __construct($style)
    {
        if (java_instanceof($style, java('org.apache.poi.xwpf.usermodel.XWPFStyle'))) {
            $this->style = $style;
        } else {
            echo "This is not  Style Class";
            die();
        }
    }

    public function getCTStyle()
    {
        $styleXML = java_values($this->style->getCTStyle()->toString());
        return $styleXML;
    }

    public function getXMLObject()
    {
        $styleXML = $this->getCTStyle();
        $styleXML = str_replace('w:', 'w', $styleXML);
        $xml = new SimpleXMLElement($styleXML);
        return $xml;
    }

    public function getType()
    {
        $type = java_values($this->style->getType()->toString());
        return $type;
    }

    public function processStyle()
    {
        $type = $this->getType();
        $styleClass = new StyleClass();
        switch ($type) {
            case 'table':
                $styleClass = $this->processTableStyle();
                break;
        }
        return $styleClass;
    }

    public function getBorderProperties()
    {
        $borders = array();
        //var_dump($this->getCTStyle());
        $tcBorders = $this->getXMLObject()->xpath("wtblPr/wtblBorders")[0];

        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $bottomVal = $tcBorders->xpath("wbottom")[0]["wval"];
            $bottomSize = $tcBorders->xpath("wbottom")[0]["wsz"];
            $bottomSpace = $tcBorders->xpath("wbottom")[0]["wspace"];
            $bottomColor = $tcBorders->xpath("wbottom")[0]["wcolor"];

            $borders['bottom']['val'] = (is_object($bottomVal)) ? (string)$bottomVal : "";
            $borders['bottom']['size'] = (is_object($bottomSize)) ? (string)round($bottomSize / 4) : "";
            $borders['bottom']['space'] = (is_object($bottomSpace)) ? (string)$bottomSpace : "";
            $borders['bottom']['color'] = (is_object($bottomColor)) ? (string)$bottomColor : "";
            if ($borders['bottom']['color'] == "auto") $borders['bottom']['color'] = "000000";

            $topVal = $tcBorders->xpath("wtop")[0]["wval"];
            $topSize = $tcBorders->xpath("wtop")[0]["wsz"];
            $topSpace = $tcBorders->xpath("wtop")[0]["wspace"];
            $topColor = $tcBorders->xpath("wtop")[0]["wcolor"];

            $borders['top']['val'] = (is_object($topVal)) ? (string)$topVal : "";
            $borders['top']['size'] = (is_object($topSize)) ? (string)round($topSize / 4) : "";
            $borders['top']['space'] = (is_object($topSpace)) ? (string)$topSpace : "";
            $borders['top']['color'] = (is_object($topColor)) ? (string)$topColor : "";
            if ($borders['top']['color'] == "auto") $borders['top']['color'] = "000000";

            $rightVal = $tcBorders->xpath("wright")[0]["wval"];
            $rightSize = $tcBorders->xpath("wright")[0]["wsz"];
            $rightSpace = $tcBorders->xpath("wright")[0]["wspace"];
            $rightColor = $tcBorders->xpath("wright")[0]["wcolor"];

            $borders['right']['val'] = (is_object($rightVal)) ? (string)$rightVal : "";
            $borders['right']['size'] = (is_object($rightSize)) ? (string)round($rightSize / 4) : "";
            $borders['right']['space'] = (is_object($rightSpace)) ? (string)$rightSpace : "";
            $borders['right']['color'] = (is_object($rightColor)) ? (string)$rightColor : "";
            if ($borders['right']['color'] == "auto") $borders['right']['color'] = "000000";

            $leftVal = $tcBorders->xpath("wleft")[0]["wval"];
            $leftSize = $tcBorders->xpath("wleft")[0]["wsz"];
            $leftSpace = $tcBorders->xpath("wleft")[0]["wspace"];
            $leftColor = $tcBorders->xpath("wleft")[0]["wcolor"];

            $borders['left']['val'] = (is_object($leftVal)) ? (string)$leftVal : "";
            $borders['left']['size'] = (is_object($leftSize)) ? (string)round($leftSize / 4) : "";
            $borders['left']['space'] = (is_object($leftSpace)) ? (string)$leftSpace : "";
            $borders['left']['color'] = (is_object($leftColor)) ? (string)$leftColor : "";
            if ($borders['left']['color'] == "auto") $borders['left']['color'] = "000000";
        }

        return $borders;
    }

    public function getTableMargins()
    {
        $cellMargins = array();
        $tblCellMargins = $this->getXMLObject()->xpath("wtblPr/wtblCellMar")[0];
        if (empty($tblCellMargins)) {
            return null;
        } else {
            $bottomVal = $tblCellMargins->xpath("wbottom")[0]["ww"];
            $bottomType = $tblCellMargins->xpath("wbottom")[0]["wtype"];
            $cellMargins['bottom']['val'] = (is_object($bottomVal)) ? (string)$bottomVal : "";
            $cellMargins['bottom']['type'] = (is_object($bottomType)) ? (string)$bottomType : "";

            $topVal = $tblCellMargins->xpath("wtop")[0]["ww"];
            $topType = $tblCellMargins->xpath("wtop")[0]["wtype"];
            $cellMargins['top']['val'] = (is_object($topVal)) ? (string)$topVal : "";
            $cellMargins['top']['type'] = (is_object($topType)) ? (string)$topType : "";

            $rightVal = $tblCellMargins->xpath("wright")[0]["ww"];
            $rightType = $tblCellMargins->xpath("wright")[0]["wtype"];
            $cellMargins['right']['val'] = (is_object($rightVal)) ? (string)$rightVal : "";
            $cellMargins['right']['type'] = (is_object($rightType)) ? (string)$rightType : "";

            $leftVal = $tblCellMargins->xpath("wleft")[0]["ww"];
            $leftType = $tblCellMargins->xpath("wleft")[0]["wtype"];
            $cellMargins['left']['val'] = (is_object($leftVal)) ? (string)$leftVal : "";
            $cellMargins['left']['type'] = (is_object($leftType)) ? (string)$leftType : "";
        }
        return $cellMargins;
    }

    public function processTableStyle()
    {
        $tableStyleClass = new StyleClass();
        $xml = $this->getXMLObject();

        // Get text color
        $color = $xml->xpath('*/wcolor');
        $color = ($color) ? ((string)$color[0]['wval']) : false;
        if ($color == 'auto') $color = '000000';

        //Get Borders
        $borders = $this->getBorderProperties();
        $margins = $this->getTableMargins();
        //var_dump($margins);
        $tableStyleClass->setAttribute("border-left", $borders['left']['size'] . "px " . HWPFWrapper::getBorder($borders['left']['val']) . " #" . $borders['left']['color']);
        $tableStyleClass->setAttribute("border-right", $borders['right']['size'] . "px " . HWPFWrapper::getBorder($borders['right']['val']) . " #" . $borders['right']['color']);
        $tableStyleClass->setAttribute("border-top", $borders['top']['size'] . "px " . HWPFWrapper::getBorder($borders['top']['val']) . " #" . $borders['top']['color']);
        $tableStyleClass->setAttribute("border-bottom", $borders['bottom']['size'] . "px " . HWPFWrapper::getBorder($borders['bottom']['val']) . " #" . $borders['bottom']['color']);

        if ($color) $tableStyleClass->setAttribute("color", '#' . $color);

        return $tableStyleClass;
    }

    /**
     * Process element style
     * @param   object  Style class
     * @param   string  Style XML
     * @return  object
     */
    public function processParagraphStyle()
    {
        $styleClass = new StyleClass();
        $xml = $this->getXMLObject();

        // TODO: Check what this is being used for (currently not in use)
        $based = $xml->xpath('*/wbasedOn');
        if ($based) $based = ((string)$based[0]['wval']);

        // Get font
        $font = $xml->xpath('*/wrFonts');
        $font = ($font) ? ((string)$font[0]['wascii']) : '';

        // Get background color
        $wshd = $xml->xpath('*/wshd');
        if ($wshd) $wshd = ((string)$wshd[0]['wfill']);

        // Get text color
        $color = $xml->xpath('*/wcolor');
        $color = ($color) ? ((string)$color[0]['wval']) : false;
        if ($color == 'auto') $color = '000000';

        // Get font size
        $sz = $xml->xpath('*/wsz');
        $sz = ($sz) ? floor(((string)$sz[0]['wval']) / 2) : '';

        // Get first line indentation
        $ident = $xml->xpath('*/wind');
        if ($ident) {
            $identNum = round(((string)$ident[0]['wfirstLine']) / 11) . 'px';
            if ($identNum == '0px') {
                $identNum = round(((string)$ident[0]['wleft']) / 11) . 'px';
            }
            $ident = $identNum;
        } else {
            $ident = '';
        }

        // Get top and bottom margins
        $spacing = $xml->xpath('*/wspacing');
        if ($spacing) {
            $spacingBefore = ((string)$spacing[0]['wbefore']);
            $spacingAfter = ((string)$spacing[0]['wafter']);
        } else {
            $spacingBefore = 0;
            $spacingAfter = 0;
        }

        // Get font weight
        $bold = $xml->xpath('*/wb');
        $weight = ($bold) ? 'bold' : 'normal';

        // Get font style
        $italic = $xml->xpath('*/wi');
        $italic = ($italic) ? true : false;

        // Get text transformation
        $allcaps = $xml->xpath('*/wcaps');
        $allcaps = ($allcaps) ? true : false;

        // Set margins
        $styleClass->setAttribute("margin-top", round(((string)$spacingBefore) / 11) . 'px');
        $styleClass->setAttribute("margin-bottom", round(((string)$spacingAfter) / 11) . 'px');

        // Set font styles
        $styleClass->setAttribute("font-family", $font);
        if ($color) $styleClass->setAttribute("color", '#' . $color);
        if (@$wshd) $styleClass->setAttribute("background-color", '#' . $wshd);
        if ($sz > '') $styleClass->setAttribute("font-size", $sz . 'pt');
        if ($weight != 'normal') $styleClass->setAttribute("font-weight", $weight);
        if ($italic) $styleClass->setAttribute("font-style", 'italic');
        if ($allcaps) $styleClass->setAttribute("text-transform", 'uppercase');
        if ($ident > '') $styleClass->setAttribute("text-indent", $ident);

        // Return styled class
        return $styleClass;
    }

}