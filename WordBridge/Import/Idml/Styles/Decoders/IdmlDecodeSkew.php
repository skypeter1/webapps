<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeSkew.php
 *
 * @class   IdmlDecodeSkew
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeSkew extends IdmlDecode
{
    public function convert()
    {
        // The Skew property indicates a specific degree of character tilt.
        // In a perfect world this could be addressed by transforming the text; however, that would require a span with a transform on each
        // individual character, which could create a huge performance impact, as well as possible side-effects from complex code.
        // We're opting to support simply italicization of the skewed text today.
        // This strategy can and probably will be subject to revision.

        // Assign the array of applied style class names (or initialize to an empty array)
        if (!is_null($this->contextualStyle))
        {
            $allStyles = $this->contextualStyle->getAllStyles();
        }
        else
        {
            $allStyles = array();
        }

        $skew = $this->findProperty('Skew', $allStyles, '0');

        if ($skew != 0)
        {
            $this->registerCSS('font-style', 'italic');
        }
    }
}
?>