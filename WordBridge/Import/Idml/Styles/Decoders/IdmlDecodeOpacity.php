<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeOpacity.php
 *
 * @class   IdmlDecodeOpacity
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeOpacity extends IdmlDecode
{
    public function convert()
    {
        $opacity = round($this->idmlPropertyValue / 100.0, 1);
        $this->registerCSS('opacity', $opacity);
    }
}
?>