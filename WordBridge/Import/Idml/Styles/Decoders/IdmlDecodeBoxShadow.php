<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeBoxShadow.php
 *
 * @class   IdmlDecodeBoxShadow
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeBoxShadow extends IdmlDecode
{
    // this handles both inset and outset box-shadow
    public function convert()
    {
        $enableDropShadow = (array_key_exists('TransparencySetting::DropShadowSetting->Mode', $this->idmlContext)) ? $this->idmlContext['TransparencySetting::DropShadowSetting->Mode'] : 'None';
        $enableInnerShadow = (array_key_exists('TransparencySetting::InnerShadowSetting->Applied', $this->idmlContext)) ? $this->idmlContext['TransparencySetting::InnerShadowSetting->Applied'] : 'false';

        if ($enableDropShadow == 'None' && $enableInnerShadow == 'false')
            return;

        else if ($enableDropShadow == 'Drop')
            $inset = '';

        else if ($enableInnerShadow == 'true')
            $inset = 'inset';

        else
        {
            CakeLog::debug("[IdmlDecodeBoxShadow:convert] enableDropShadow=$enableDropShadow enableInnerShadow=$enableInnerShadow");
            return;
        }

        // Initialize all values to defaults. Most may or may not be defined.
        $horizOffset = '7';
        $vertOffset = '7';
        $blur = '5';
        $spread = 5;
        $opacity = .75;
        $rgb = array(0,0,0);

        // First, obtain the values of x- and y- offsets
        if (array_key_exists('TransparencySetting::DropShadowSetting->XOffset', $this->contextualStyle->idmlKeyValues))
        {
            $horizOffset = (int) $this->contextualStyle->idmlKeyValues['TransparencySetting::DropShadowSetting->XOffset'];
        }

        if (array_key_exists('TransparencySetting::DropShadowSetting->YOffset', $this->contextualStyle->idmlKeyValues))
        {
            $vertOffset = (int) $this->contextualStyle->idmlKeyValues['TransparencySetting::DropShadowSetting->YOffset'];
        }

        // Account for the 'angle' property of an inner shadow.
        // For now, we're simply assuming that if the angle is negative we have to reverse the 'polarity' by multiplying
        //  the offsets by -1. IDML's strategy may be more complicated and may need further management.
        if (array_key_exists('TransparencySetting::InnerShadowSetting->Angle', $this->contextualStyle->idmlKeyValues))
        {
            $angle = (int) $this->contextualStyle->idmlKeyValues['TransparencySetting::InnerShadowSetting->Angle'];
            if ($angle > 0)
            {
                $horizOffset *= -1;
                $vertOffset *= -1;
            }
        }

        // Next, obtain the values of size and color.
        if (array_key_exists('TransparencySetting::DropShadowSetting->Size', $this->contextualStyle->idmlKeyValues))
        {
            $spread = $this->contextualStyle->idmlKeyValues['TransparencySetting::DropShadowSetting->Size'];
        }

        // Get CMYK color and convert to RGB array
        if (array_key_exists('TransparencySetting::DropShadowSetting->EffectColor', $this->contextualStyle->idmlKeyValues))
        {
            $effectColor = $this->contextualStyle->idmlKeyValues['TransparencySetting::DropShadowSetting->EffectColor'];

            // Convert the color from IDML syntax to rgb, using the color handler associated with the declaration manager
            $declarationMgr = IdmlDeclarationManager::getInstance();
            $colorHandler = $declarationMgr->declaredColorHandler;
            $rgb = $colorHandler->colorRefToRGB($effectColor);
        }

        // Idml's 'spread' is not the same as any CSS property. It affects the blur, but the size of the
        // shadow--including the blur--always remains inside the box specified by the size property (in CSS, blur pushes the
        // shadow outside the dimensions of its 'spread' property, which in CSS is the size of the shadow). Thus, the box shadow
        // models in IDML and CSS are fundamentally different.
        // Our use here is not correct, but rather represents a provisionary attempt at a best representation.
        // Blur is set based on spread: higher spread means lower blur, but the relationship is not linear, so use
        // a fractional exponent to scale. This still needs some tweaking, if not complete rewrite.

        if (array_key_exists('TransparencySetting::DropShadowSetting->Spread', $this->contextualStyle->idmlKeyValues))
        {
            $idmlBlur = 100 - $this->contextualStyle->idmlKeyValues['TransparencySetting::DropShadowSetting->Spread'];
            $scalingExponent = .25;
            $blur = pow($idmlBlur, $scalingExponent);
        }

        // Add opacity to color
        $rgbColor = 'rgba(' . $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2] . ',' . $opacity . ')';

        // Use the components to assemble and register the CSS
        $propertyValue = $horizOffset . 'px ' . $vertOffset . 'px ' . $blur . 'px ' . $spread . 'px ' . $rgbColor . ' ' . $inset;

        $this->registerCSS('box-shadow', $propertyValue);
    }
}
?>