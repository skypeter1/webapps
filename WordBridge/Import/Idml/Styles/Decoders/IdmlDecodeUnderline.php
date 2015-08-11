<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeUnderline.php
 *
 * @class   IdmlDecodeUnderline
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeUnderline extends IdmlDecode
{
    public function convert()
    {
        $underlineOnStr = (array_key_exists('Underline', $this->idmlContext)) ? $this->idmlContext['Underline'] : 'false';
        $underlineOn = ($underlineOnStr == 'true');

        $backColorName = (array_key_exists('Properties::UnderlineColor', $this->idmlContext)) ? $this->idmlContext['Properties::UnderlineColor'] : false;
        $underlineOffset = (array_key_exists('UnderlineOffset', $this->idmlContext)) ? $this->idmlContext['UnderlineOffset'] : false;

        // This code addresses the use case where underlining is used to create a background color in InDesign
        // It only applies when the Underline property is set to true, and the UnderlineColor and UnderlineOffset are set as well.
        if ($underlineOn && $backColorName && $underlineOffset) {
            $declarationMgr = IdmlDeclarationManager::getInstance();
            if (substr($backColorName,0,5) == 'Color') {
                $backColorValues = $declarationMgr->declaredColorHandler->declaredColors[$backColorName];
                $backColor = IdmlDeclaredColors::rgbAsHex($backColorValues);
                $this->registerCSS('background-color', $backColor);
            }
        }
    }
}
?>