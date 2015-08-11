<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTracking.php
 *
 * @class   IdmlDecodeTracking
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeTracking extends IdmlDecode
{
    public function convert()
    {
        // IDML Specification says Tracking is the amount by which to loosen or tighten a
        // block of text, specified in thousands of an em.

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