<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeClipContentToCell.php
 *
 * @class   IdmlDecodeClipContentToCell
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Decoders');


class IdmlDecodeClipContentToCell extends IdmlDecode
{
    public function convert()
    {
        // $idmlPropertyName == 'ClipContentToCell'

        if ($this->idmlPropertyValue == 'true')
            $this->registerCSS('overflow', 'hidden');
    }
}
?>