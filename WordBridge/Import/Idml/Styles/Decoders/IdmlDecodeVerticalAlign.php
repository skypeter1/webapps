<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeVerticalAlign.php
 *
 * @class   IdmlDecodeVerticalAlign
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeVerticalAlign extends IdmlDecode
{
    public function convert()
    {
        // BaselineShift is the amount by which to shift the block of text vertically, from .001 to 100.

        if ($this->idmlPropertyValue == 0)
            $this->registerCSS('vertical-align', 'normal');
        else
            $this->registerCSS('vertical-align', $this->idmlPropertyValue . 'px');
    }
}
?>