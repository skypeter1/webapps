<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTableRightStroke.php
 *
 * @class   IdmlDecodeTableRightStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Decoders');


class IdmlDecodeTableRightStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-right'] = 'border-right';
        $colorRef = (array_key_exists('RightBorderStrokeColor', $this->idmlContext)) ? $this->idmlContext['RightBorderStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('RightBorderStrokeTint', $this->idmlContext)) ? $this->idmlContext['RightBorderStrokeTint'] : '-1';
        $weight = (array_key_exists('RightBorderStrokeWeight', $this->idmlContext)) ? $this->idmlContext['RightBorderStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('RightBorderStrokeType', $this->idmlContext)) ? $this->idmlContext['RightBorderStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>