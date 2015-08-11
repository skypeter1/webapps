<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodePosition.php
 *
 * @class   IdmlDecodePosition
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodePosition extends IdmlDecode
{
    public function convert()
    {
        switch($this->idmlContext['Position'])
        {
            case 'Superscript':
            case 'OTSuperscript':
                $this->registerCSS('vertical-align', 'super');
                $this->registerCSS('font-size', '80%');
                break;

            case 'Subscript':
            case 'OTSubscript':
                $this->registerCSS('vertical-align', 'sub');
                $this->registerCSS('font-size', '80%');
                break;

            case 'Normal':
            case 'OTNumerator':
            case 'OTDenominator':
            default:
                $this->registerCSS('vertical-align', 'baseline');
                break;
        }
    }
}
?>