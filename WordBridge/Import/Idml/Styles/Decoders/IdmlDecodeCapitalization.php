<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCapitalization.php
 *
 * @class   IdmlDecodeCapitalization
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeCapitalization extends IdmlDecode
{
    public function convert()
    {
        switch($this->idmlPropertyValue)
        {
            case 'SmallCaps':
            case 'CapToSmallCap':
                $this->registerCSS('font-variant', 'small-caps');
                $this->registerCSS('text-transform', 'none');
                break;

            case 'AllCaps':
                $this->registerCSS('font-variant', 'normal');
                $this->registerCSS('text-transform', 'uppercase');
                break;

            case 'Normal':
            default:
                $this->registerCSS('font-variant', 'normal');
                $this->registerCSS('text-transform', 'none');
                break;
        }
    }
}
?>