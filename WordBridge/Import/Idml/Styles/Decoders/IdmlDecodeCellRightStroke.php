<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCellRightStroke.php
 *
 * @class   IdmlDecodeCellRightStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeCellRightStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-right'] = 'border-right';
        $colorRef = (array_key_exists('RightEdgeStrokeColor', $this->idmlContext)) ? $this->idmlContext['RightEdgeStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('RightEdgeStrokeTint', $this->idmlContext)) ? $this->idmlContext['RightEdgeStrokeTint'] : '-1';
        $weight = (array_key_exists('RightEdgeStrokeWeight', $this->idmlContext)) ? $this->idmlContext['RightEdgeStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('RightEdgeStrokeType', $this->idmlContext)) ? $this->idmlContext['RightEdgeStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>