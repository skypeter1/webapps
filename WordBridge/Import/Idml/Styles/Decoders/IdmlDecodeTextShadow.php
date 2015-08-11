<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTextShadow.php
 *
 * @class   IdmlDecodeTextShadow
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeTextShadow extends IdmlDecode
{
    public function convert()
    {
        // The Mode value must be 'Drop'; otherwise this isn't a drop shadow.
        if ($this->idmlPropertyValue == 'Drop')
        {
            // First, obtain the values of x-offset, y-offset, and color.
            // These are stored in separate properties in the contextual style
            $horizOffset = (array_key_exists('ContentTransparencySetting::DropShadowSetting->XOffset', $this->idmlContext)) ? $this->idmlContext['ContentTransparencySetting::DropShadowSetting->XOffset'] : '0';
            $vertOffset  = (array_key_exists('ContentTransparencySetting::DropShadowSetting->YOffset', $this->idmlContext)) ? $this->idmlContext['ContentTransparencySetting::DropShadowSetting->YOffset'] : '0';
            $effectColor  = (array_key_exists('ContentTransparencySetting::DropShadowSetting->EffectColor', $this->idmlContext)) ? $this->idmlContext['ContentTransparencySetting::DropShadowSetting->EffectColor'] : 'Color/Black';
            $size  = (array_key_exists('ContentTransparencySetting::DropShadowSetting->Size', $this->idmlContext)) ? $this->idmlContext['ContentTransparencySetting::DropShadowSetting->Size'] : '5';

            // Convert the color from IDML syntax to rgb, using the color handler associated with the declaration manager
            $declarationMgr = IdmlDeclarationManager::getInstance();
            $colorHandler = $declarationMgr->declaredColorHandler;
            $rgbColor = $colorHandler->colorRefAsHex($effectColor);

            // Use the components to assemble and register the CSS
            $propertyValue = sprintf('%spx %spx %spx %s', $horizOffset, $vertOffset, $size, $rgbColor);
            $this->registerCSS('text-shadow', $propertyValue);
        }
    }
}
?>