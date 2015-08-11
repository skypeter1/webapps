<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeHyphenation.php
 *
 * @class   IdmlDecodeHyphenation
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeHyphenation extends IdmlDecode
{
    public function convert()
    {
        if ($this->idmlPropertyValue == 'false')
            $this->registerCSS('-webkit-hyphens', 'none');

        else // if ($this->idmlPropertyValue == 'true')
            $this->registerCSS('-webkit-hyphens', 'auto');
    }
}
?>