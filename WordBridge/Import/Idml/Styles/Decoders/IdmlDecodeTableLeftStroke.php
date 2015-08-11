<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTableLeftStroke.php
 *
 * @class   IdmlDecodeTableLeftStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeTableLeftStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-left'] = 'border-left';
        $colorRef = (array_key_exists('LeftBorderStrokeColor', $this->idmlContext)) ? $this->idmlContext['LeftBorderStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('LeftBorderStrokeTint', $this->idmlContext)) ? $this->idmlContext['LeftBorderStrokeTint'] : '-1';
        $weight = (array_key_exists('LeftBorderStrokeWeight', $this->idmlContext)) ? $this->idmlContext['LeftBorderStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('LeftBorderStrokeType', $this->idmlContext)) ? $this->idmlContext['LeftBorderStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>