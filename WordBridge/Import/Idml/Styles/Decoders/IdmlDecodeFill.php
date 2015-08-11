<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeFill.php
 *
 * @class   IdmlDecodeFill
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeFill extends IdmlDecode
{
    public function convert()
    {
        // This function uses multiple IDML properties: 'FillColor', 'FillTint', 'GradientFillAngle', 'EnableFill'
        // Careful, it is possible for either FillColor or FillTint to be missing, which is entirely valid in IDML,
        // in which case the fallbacks are 'Color/Black' and -1.

        $colorRef = $this->getAppliedStyleKeyValue( 'FillColor', 'Color/Black' );
        $fillTint = $this->getAppliedStyleKeyValue( 'FillTint', '-1' );

        if ($this->decodeContext == 'Decoration')
        {
            $this->decorationFill($colorRef, $fillTint);
        }
        else
        {
            $this->typographyFill($colorRef, $fillTint);
        }
    }

    /*
     * Suitable for TextFrames, Rectangles, Tables, Cells
     */
    protected function decorationFill($colorRef, $fillTint)
    {
        $enableFill = (array_key_exists('EnableFill', $this->idmlContext)) ? $this->idmlContext['EnableFill'] : 'true';
        if ($enableFill == 'false')
        {
            $this->registerCSS('background-color', 'transparent');
            return;
        }

        $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;

        list($colorRefType, $colorRefName) = explode('/', $colorRef, 2);
        switch($colorRefType)
        {
            case 'Swatch':
                if ($colorRefName == 'None')
                    $this->registerCSS('background-color', 'transparent');
                break;

            case 'Color':
            case 'Ink':
            case 'MixedInk':
            case 'Tint':
                $fillTint = (array_key_exists('FillTint', $this->idmlContext)) ? $this->idmlContext['FillTint'] : '-1';
                $rgbRaw = $handle->colorRefToRGB($colorRef);
                $rgbTint = $handle->applyTintToRGB($rgbRaw, $fillTint);
                $hexColor = $handle->rgbAsHex($rgbTint);
                $this->registerCSS('background-color', $hexColor);
                break;

            case 'Gradient':
                $angle = (array_key_exists('GradientFillAngle', $this->idmlContext)) ? $this->idmlContext['GradientFillAngle'] : '0';
                $gradient = $handle->colorRefAsGradient($colorRef, $angle);
                $this->registerCSS('background-image', $gradient);
                break;

            case 'PastedSmoothShade':
            case 'MixedInkGroup':
            default:
                break;
        }
    }

    /*
     * Suitable for ParagraphRange, CharacterRange
     */
    protected function typographyFill($colorRef, $fillTint)
    {
        $enableFill = (array_key_exists('EnableFill', $this->idmlContext)) ? $this->idmlContext['EnableFill'] : 'true';
        if ($enableFill == 'false')
        {
            $this->registerCSS('color', 'transparent');
            return;
        }

        $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;

        list($colorRefType, $colorRefName) = explode('/', $colorRef);
        switch($colorRefType)
        {
            case 'Swatch':
                if ($colorRefName == 'None')
                    $this->registerCSS('color', 'transparent');
                break;

            case 'Color':
            case 'Ink':
            case 'MixedInk':
            case 'Tint':
                $rgbRaw = $handle->colorRefToRGB($colorRef);
                $rgbTint = $handle->applyTintToRGB($rgbRaw, $fillTint);
                $hexColor = $handle->rgbAsHex($rgbTint);
                $this->registerCSS('color', $hexColor);
                break;

            case 'Gradient':
                $hexColor = $handle->firstColorAsCSS($colorRef);
                $this->registerCSS('color', $hexColor);
                break;

            case 'PastedSmoothShade':
            case 'MixedInkGroup':
            default:
                break;
        }
    }
}
?>