<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeLists.php
 *
 * @class   IdmlDecodeLists
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeLists extends IdmlDecode
{
    public function convert()
    {
        $listType = (array_key_exists('BulletsAndNumberingListType', $this->idmlContext)) ? $this->idmlContext['BulletsAndNumberingListType'] : 'NoList';
        $bulletType = (array_key_exists('Properties::BulletChar->BulletCharacterType', $this->idmlContext)) ? $this->idmlContext['Properties::BulletChar->BulletCharacterType'] : 'UnicodeOnly';  // Unicode 8226 is a BULLET
        $bulletChar = (array_key_exists('Properties::BulletChar->BulletCharacterValue', $this->idmlContext)) ? $this->idmlContext['Properties::BulletChar->BulletCharacterValue'] : '8226';  // Unicode 8226 is a BULLET
        $numberingFormat = (array_key_exists('Properties::NumberingFormat', $this->idmlContext)) ? $this->idmlContext['Properties::NumberingFormat'] : '1, 2, 3, 4...';

        $fontFamily = (array_key_exists('Properties::BulletsFont', $this->idmlContext)) ? $this->idmlContext['Properties::BulletsFont'] : '';
        $fontStyle = (array_key_exists('Properties::BulletsFontStyle', $this->idmlContext)) ? $this->idmlContext['Properties::BulletsFontStyle'] : '';
        if ($fontStyle == 'Regular' || $fontStyle == '')
        {
            $fontName = $fontFamily;
        }
        else
        {
            $fontName = $fontFamily . ' ' . $fontStyle;
        }

        switch ($listType)
        {
            case 'NumberedList':
                $this->registerTagSpecificCSS('ol', 'list-style-position', 'outside');
                $this->registerTagSpecificCSS('ol', 'text-indent', '0');
                $this->registerTagSpecificCSS('ol', 'padding-left', '2.0em');
                $this->registerTagSpecificCSS('ol', 'list-style-type',  IdmlNumberingFormat::convertIdmlToCSS($numberingFormat));
                break;

            case 'BulletList':
                $this->registerTagSpecificCSS('ul', 'list-style-position', 'outside');
                $this->registerTagSpecificCSS('ul', 'text-indent', '0');
                $this->registerTagSpecificCSS('ul', 'padding-left', '2.0em');

                if ($bulletType=='UnicodeOnly' ||       $bulletType=='UnicodeWithFont')
                {
                    $this->registerTagSpecificCSS('ul', 'list-style', 'none');
                    $this->registerChildCSS('li:before', 'content', '"\\' . dechex((int)$bulletChar) . '  "');
                    $this->registerChildCSS('li:before', 'margin-left', '-1em');
                    if ($fontName != '')
                    {
                        $this->registerChildCSS('li:before', 'font-family', '"' . $fontName . '"');
                    }

// This code is probably rendered obsolete by above code; saved here in case it proves useful for edge cases.
//
//                    switch($bulletChar)
//                    {
//                        case '42':      // IDML has an Asterisk (*) as its first non-bullet alternate; let's map it to a CSS circle
//                            $this->registerTagSpecificCSS('ul', 'list-style-type', 'circle');
//                            break;
//                        case '9674':   // IDML has a Lozenge (◊) as its second non-bullet alternate; let's map it to a CSS square
//                            $this->registerTagSpecificCSS('ul', 'list-style-type', 'square');
//                            break;
//                        case '8226':    // the default is a bullet (•)
//                        default:
//                            $this->registerTagSpecificCSS('ul', 'list-style-type', 'disc');
//                            break;
//                    }
                }
                else
                    $this->registerTagSpecificCSS('ul', 'list-style-type', 'disc');
                break;

            case 'NoList':
            default:
                break;
        }
    }
}
?>