<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeFontFamily.php
 *
 * @class   IdmlDecodeFontFamily
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Styles/Decoders');


class IdmlDecodeFontFamily extends IdmlDecode
{
    /**
     * FontStyle and AppliedFont need a special method, since the style needs both values and the values may be set
     * by different Idml element types (e.g. CharacterRange and ParagraphRange).
     * convert() looks through all the applicable styles of the current node to find the correct value of FontStyle
     * and Properties::AppliedFont and returns the correct style value accordingly.
     */
    public function convert()
    {
        // Assign the array of applied style class names (or initialize to an empty array)
        if (!is_null($this->contextualStyle))
        {
            $allStyles = $this->contextualStyle->getAllStyles();
        }
        else
        {
            $allStyles = array();
        }

        // Get the computed values of FontStyle and Properties::AppliedFont
        if (is_array($allStyles))
        {
            $fontFamily = $this->findProperty('Properties::AppliedFont', $allStyles, '');
            $fontStyle = $this->findProperty('FontStyle', $allStyles, '');
        }
        else
        {
            $fontFamily = $this->getAppliedStyleKeyValue( 'Properties::AppliedFont', '' );
            $fontStyle = $this->getAppliedStyleKeyValue( 'FontStyle', '' );
        }

        if ($fontFamily != '')
        {
            if ($fontStyle == 'Regular' || $fontStyle == '')
            {
                if (strpos($fontFamily, ' ') !== false)
                    $fontFamily = sprintf("'%s'", $fontFamily);
                $this->registerCSS('font-family', $fontFamily);
            }

            else // if ($fontStyle != '' /* Bold, Condensed, Expanded */)
            {
                $cssPropertyValue =  sprintf( "'%s %s'", $fontFamily, $fontStyle );     // <-- Caution, the @font-face mapper will need to match this space-delimited name with a real font-file
                $this->registerCSS('font-family', $cssPropertyValue);
            }
        }

        else // if ($fontFamily == '')
        {
            if ($fontStyle == 'Bold')
                $this->registerCSS('font-weight', 'bold');

            else if ($fontStyle == 'Italic')
                $this->registerCSS('font-style', 'italic');

            else // if ($fontStyle != '' /* Regular, Condensed, Expanded */)
                return;
        }
    }
}
?>