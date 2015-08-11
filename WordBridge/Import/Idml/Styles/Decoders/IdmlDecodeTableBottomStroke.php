<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTableBottomStroke.php
 *
 * @class   IdmlDecodeTableBottomStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeTableBottomStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-bottom'] = 'border-bottom';
        $colorRef = (array_key_exists('BottomBorderStrokeColor', $this->idmlContext)) ? $this->idmlContext['BottomBorderStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('BottomBorderStrokeTint', $this->idmlContext)) ? $this->idmlContext['BottomBorderStrokeTint'] : '-1';
        $weight = (array_key_exists('BottomBorderStrokeWeight', $this->idmlContext)) ? $this->idmlContext['BottomBorderStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('BottomBorderStrokeType', $this->idmlContext)) ? $this->idmlContext['BottomBorderStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>