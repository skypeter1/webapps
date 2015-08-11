<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCellLeftStroke.php
 *
 * @class   IdmlDecodeCellLeftStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeCellLeftStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-left'] = 'border-left';
        $colorRef = (array_key_exists('LeftEdgeStrokeColor', $this->idmlContext)) ? $this->idmlContext['LeftEdgeStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('LeftEdgeStrokeTint', $this->idmlContext)) ? $this->idmlContext['LeftEdgeStrokeTint'] : '-1';
        $weight = (array_key_exists('LeftEdgeStrokeWeight', $this->idmlContext)) ? $this->idmlContext['LeftEdgeStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('LeftEdgeStrokeType', $this->idmlContext)) ? $this->idmlContext['LeftEdgeStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>