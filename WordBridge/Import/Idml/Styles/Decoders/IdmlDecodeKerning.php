<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeKerning.php
 *
 * @class   IdmlDecodeKerning
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeKerning extends IdmlDecode
{
    public function convert()
    {
        // KerningValue is the amount by which to shift the text horizontally.

        if ($this->idmlPropertyValue == 0)
            $this->registerCSS('letter-spacing', 'normal');
        else
        {
            $thousands = $this->idmlPropertyValue / 1000.0;
            $this->registerCSS('letter-spacing', $thousands . 'em');
        }
    }
}
?>