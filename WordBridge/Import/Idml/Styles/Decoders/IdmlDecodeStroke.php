<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeStroke.php
 *
 * @class   IdmlDecodeStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


/*
 * This is the base class for IdmlDecodeTable[XXX]Stroke and IdmlDecodeCell[XXX]Stroke
 * used in the 'Decoration' decodeContext
 */
class IdmlDecodeStroke extends IdmlDecode
{
    public function convert()
    {
        // Stroke weight on a group does not indicated a border; skip this function for a group.
        if ($this->contextualStyle->idmlContextualElement == 'Group')
        {
            return;
        }

        $enable = (array_key_exists('EnableStroke', $this->idmlContext)) ? $this->idmlContext['EnableStroke'] : 'true';

        if ($enable == 'false')
            return;

        if ($this->decodeContext == 'SVG')
        {
            $colorRef = (array_key_exists('StrokeColor', $this->idmlContext)) ? $this->idmlContext['StrokeColor'] : 'Color/Black';
            $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;
            $rgbRaw = $handle->colorRefToRGB($colorRef);
            $hexColor = $handle->rgbAsHex($rgbRaw);
            $this->registerCSS('stroke', $hexColor);
            return;
        }

        if ($this->decodeContext === 'Typography')
        {
            $colorRef = (array_key_exists('StrokeColor', $this->idmlContext)) ? $this->idmlContext['StrokeColor'] : 'Color/Black';
            $tint = (array_key_exists('StrokeTint', $this->idmlContext)) ? $this->idmlContext['StrokeTint'] : '-1';
            $weight = (array_key_exists('StrokeWeight', $this->idmlContext)) ? $this->idmlContext['StrokeWeight'] : '1';

            if ((float) $weight > 0 && (float) $weight <= 1)
                $weight = '1';
            else
                $weight = round((int) $weight);

            // -wekbit-text-stroke is pending deprecation via CSS3 'text-outline' property
            $this->convertFontStroke('-webkit-text-stroke', $colorRef, $tint, $weight);
            return;
        }

        // else we're in 'Decoration' decodeContext

        // If stroke weight is 0, set border to none to override any cascading border style
        if ($this->idmlPropertyName == 'StrokeWeight' && $this->idmlPropertyValue == '0')
        {
            $this->registerCSS('border', 'none');
            return;
        }

        $colorRef = (array_key_exists('StrokeColor', $this->idmlContext)) ? $this->idmlContext['StrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('StrokeTint', $this->idmlContext)) ? $this->idmlContext['StrokeTint'] : '-1';
        $weight = (array_key_exists('StrokeWeight', $this->idmlContext)) ? round($this->idmlContext['StrokeWeight']) : '1';
        $strokeRef = (array_key_exists('StrokeType', $this->idmlContext)) ? $this->idmlContext['StrokeType'] : 'StrokeStyle/$ID/Solid';
        $alignment = (array_key_exists('StrokeAlignment', $this->idmlContext)) ? $this->idmlContext['StrokeAlignment'] : 'CenterAlignment';

        if ($strokeRef === 'StrokeStyle/$ID/Solid')
        {
            switch ($alignment)
            {
                case 'OutsideAlignment':
                    $this->cssTarget['border'] = null;
                    $this->cssTarget['box-shadow'] = 'box-shadow';
                    $this->cssTarget['margin'] = 'margin';
                    break;
                case 'CenterAlignment':
                    // Cannot effectively split < 2px
                    $this->cssTarget['border'] = 'border';
                    $this->cssTarget['box-shadow'] = ((int) $weight < 2) ? null : 'box-shadow';
                    $this->cssTarget['margin'] = 'margin';
                    break;
                case 'InsideAlignment':
                default:
                    $this->cssTarget['border'] = 'border';
                    break;
            }
        }
        else
        {
            $this->cssTarget['border'] = 'border';
        }
        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }


    /**
     * @param array[string] $cssTarget is the CSS attribute name to write, like 'border', 'border-top', 'border-right',
     *  'border-bottom', 'border-left', or 'box-shadow' if Outside or Centered StrokeAlignment is declared.
     * @param string $colorRef is the IDML color reference, like 'Color/name' or 'Tint/name' or 'Gradient/name'.
     * @param float $tint is a number from 0 to 100
     * @param integer $weight is a non-negative integer
     * @param string $strokeRef is an IDML StrokeStyle
     */
    public function convertStroke($cssTarget, $colorRef, $tint, $weight, $strokeRef)
    {
        $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;
        list($colorRefType, $colorRefName) = explode('/', $colorRef, 2);
        $cssValue = array();

        switch($colorRefType)
        {
            case 'Swatch':
                if ($colorRefName === 'None')
                    $this->registerCSS('border', 'none');
                break;

            case 'Color':
            case 'Ink':
            case 'MixedInk':
            case 'Tint':
                $rgbRaw = $handle->colorRefToRGB($colorRef);
                $rgbTint = $handle->applyTintToRGB($rgbRaw, $tint);
                $hexColor = $handle->rgbAsHex($rgbTint);
                $borderStyle = IdmlStrokeStyle::strokeRefAsCSS($strokeRef);

                // box-shadow and border
                if (isset($this->cssTarget['border']) && isset($this->cssTarget['box-shadow']))
                {
                    $weight = ((int) $weight < 2) ? 1 : round((int) $weight/2);
                    $cssValue['border'] = sprintf('%spx %s %s', $weight, $borderStyle, $hexColor);
                    $cssValue['box-shadow'] = $this->getBoxShadowValue($weight, $hexColor);
                    $cssValue['margin'] = sprintf('%spx', $weight);
                    $this->registerCSS($cssTarget['border'], $cssValue['border']);
                    $this->registerCSS($cssTarget['box-shadow'], $cssValue['box-shadow']);
                    $this->registerCSS($cssTarget['margin'], $cssValue['margin']);
                }
                // box-shadow only
                elseif (!isset($this->cssTarget['border']) && isset($this->cssTarget['box-shadow']))
                {
                    $weight = ((int) $weight < 2) ? 1 : round((int) $weight);
                    $cssValue['box-shadow'] = $this->getBoxShadowValue($weight, $hexColor);
                    $cssValue['margin'] = sprintf('%spx', $weight);
                    $this->registerCSS($cssTarget['box-shadow'], $cssValue['box-shadow']);
                    $this->registerCSS($cssTarget['margin'], $cssValue['margin']);
                }
                // border only
                else
                {
                    $cssValue['this'] = sprintf('%spx %s %s', $weight, $borderStyle, $hexColor);
                    $this->registerCSS(key($cssTarget), $cssValue['this']);
                }
                break;

            case 'Gradient':
                $hexColor = $handle->firstColorAsCSS($colorRef);
                $borderStyle = IdmlStrokeStyle::strokeRefAsCSS($strokeRef);
                $cssValue['this'] = sprintf('%spx %s %s', $weight, $borderStyle, $hexColor);
                $this->registerCSS(key($cssTarget), $cssValue['this']);
                break;

            case 'PastedSmoothShade':
            case 'MixedInkGroup':
            default:
                break;
        }

        // copy the stroke weight to the Idml[XXX]Style for use by IdmlProduceBaseHtml
        $this->contextualStyle->SetComputedBorders($weight);
    }

    /**
     * Return the verbose string representing the four-sided box-shadow property value, given weight and color.
     * @param int $weight
     * @param string $hexColor
     * @return string $cssValue
     */
    protected function getBoxShadowValue($weight, $hexColor)
    {
        $cssValue = sprintf('%spx %spx %s, -%spx -%spx %s, -%spx %spx %s, %spx -%spx %s',
                             $weight, $weight, $hexColor, $weight, $weight, $hexColor,
                             $weight, $weight, $hexColor, $weight, $weight, $hexColor);

        return $cssValue;
    }

    /**
     * @param string $cssTarget
     * @param string $colorRef
     * @param int $tint
     * @param int $weight
     */
    public function convertFontStroke($cssTarget, $colorRef, $tint, $weight)
    {
        $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;
        list($colorRefType, $colorRefName) = explode('/', $colorRef, 2);

        switch($colorRefType)
        {
            case 'Swatch':
                if ($colorRefName === 'None')
                    break;
            case 'Color':
            case 'Ink':
            case 'MixedInk':
            case 'Tint':
                $rgbRaw = $handle->colorRefToRGB($colorRef);
                $rgbTint = $handle->applyTintToRGB($rgbRaw, $tint);
                $hexColor = $handle->rgbAsHex($rgbTint);
                $cssValue = sprintf('%spx %s', $weight, $hexColor);

                $this->registerCSS($cssTarget, $cssValue);
        }
    }
}
?>