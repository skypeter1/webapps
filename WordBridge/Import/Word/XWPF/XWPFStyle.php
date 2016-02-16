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
        switch ($type) {
            case 'table':
                $styleClass = $this->processTableStyle();
                break;
            default:
                $styleClass = new StyleClass();
        }
        return $styleClass;
    }

    private function getProperty($propertyPath){
        $result = $this->getXMLObject()->xpath($propertyPath);
        $property = (count($result)>0) ? $result[0] : array();
        return $property;
    }

    /**
     * @param $tcBorders
     * @return array|null
     */
    public function getBorderProperties($tcBorders)
    {
        $borders = array();
        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $borderKeys = array(
                "bottom" => "wbottom",
                "top" => "wtop",
                "right" => "wright",
                "left" => "wleft",
                "insideH" => "winsideH",
                "insideV" => "winsideV"
            );
            foreach ($borderKeys as $key => $borderKey) {
                if (array_key_exists($borderKey, $tcBorders)) {
                    $val = $tcBorders->xpath($borderKey)[0]["wval"];
                    if($val != "nil") {
                        $size = $tcBorders->xpath($borderKey)[0]["wsz"];
                        $space = $tcBorders->xpath($borderKey)[0]["wspace"];
                        $color = $tcBorders->xpath($borderKey)[0]["wcolor"];

                        $borders[$key]['val'] = (is_object($val)) ? (string)$val : "";
                        $borders[$key]['size'] = (is_object($size)) ? (string)round($size / 4) : "";
                        $borders[$key]['space'] = (is_object($space)) ? (string)$space : "";
                        $borders[$key]['color'] = (is_object($color)) ? (string)$color : "";
                        if ($borders[$key]['color'] == "auto") $borders[$key]['color'] = "000000";
                    }
                }
            }
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

    /**
     * @return StyleClass
     */
    public function processTableStyle()
    {
        $tableStyleClass = new StyleClass();
        $xml = $this->getXMLObject();

        // Get text color
        $color = $xml->xpath('*/wcolor');
        $color = ($color) ? ((string)$color[0]['wval']) : false;
        if ($color == 'auto') $color = '000000';

        //Get border properties
        $tcBorders = $this->getProperty("wtblPr/wtblBorders");
        $borders = $this->getBorderProperties($tcBorders);

        //Assign border styles
        if (!is_null($borders)) {
            $tableBorders = array('left', 'right', 'top', 'bottom');
            foreach ($borders as $key => $border) {
                if (in_array($key, $tableBorders)) {
                    $tableStyleClass->setAttribute("border-" . $key,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }

        //Default settings
        $tableStyleClass->setAttribute("border-collapse", "collapse");
        $tableStyleClass->setAttribute("width", "100%");

        if ($color) $tableStyleClass->setAttribute("color", '#' . $color);

        return $tableStyleClass;
    }

    /**
     * @return array|null
     */
    private function getCellStyleBorders()
    {
        $tcBorders = $this->getProperty("wtblPr/wtblBorders");
        $cellBorders = $this->getBorderProperties($tcBorders);
        return $cellBorders;
    }

    /**
     * @return StyleClass
     */
    public function processTableCellStyle()
    {
        $tableCellStyleClass = new StyleClass();
        $cellBorders = $this->getCellStyleBorders();

        if (!is_null($cellBorders)) {
            $tableBorders = array('right'=>'insideV', 'bottom'=>'insideH');
            foreach ($cellBorders as $key => $border) {
                if (in_array($key, $tableBorders)) {
                    $direction = ($key == "insideH") ? "bottom" : "right";
                    $tableCellStyleClass->setAttribute("border-" . $direction,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }

        return $tableCellStyleClass;
    }

    /**
     * @return array
     */
    public function extractConditionalFormat()
    {
        // Get Conditional Format Borders
        $conditionalFormatArray = array();
        $conditionalFormatting = $this->getXMLObject()->xpath('wtblStylePr');
        if (!empty($conditionalFormatting)) {
            foreach ($conditionalFormatting as $tblStylePr) {
                $formatKey = (string)$tblStylePr['wtype'];
                $wtcPr = $tblStylePr->xpath('wtcPr');
                if (!empty($wtcPr)) {
                    $wtcBorders = $wtcPr[0]->xpath("wtcBorders");
                    $conditionalBorders = (!empty($wtcBorders)) ? $this->getBorderProperties($wtcBorders[0]) : array();
                    $wtcBackgroundColor = $wtcPr[0]->xpath("wshd");
                    $backgroundColor = (!empty($wtcBackgroundColor)) ? (string)$wtcBackgroundColor[0]['wfill'] : "";
                } else {
                    $conditionalBorders = array();
                    $backgroundColor = "";
                }
                $conditionalFormatArray[] = array(
                    'type' => $formatKey,
                    'borders' => $conditionalBorders,
                    'backgroundColor' => $backgroundColor
                );
            }
        }
        return $conditionalFormatArray;
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