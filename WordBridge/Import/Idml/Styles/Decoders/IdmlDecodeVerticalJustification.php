<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeVerticalJustification.php
 *
 * @class   IdmlDecodeVerticalJustification
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Decoders');


class IdmlDecodeVerticalJustification extends IdmlDecode
{
    public function convert()
    {
        // $idmlPropertyName == 'VerticalJustification'

        switch ($this->idmlPropertyValue)
        {
            case 'TopAlign':
                $this->registerCSS('vertical-align', 'top');
                break;

            case 'CenterAlign':
                $this->registerCSS('vertical-align', 'middle');
                break;

            case 'BottomAlign':
                $this->registerCSS('vertical-align', 'bottom');
                break;

            default:
                break;
        }
    }
}
?>