<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCellBottomStroke.php
 *
 * @class   IdmlDecodeCellBottomStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeCellBottomStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-bottom'] = 'border-bottom';
        $colorRef = (array_key_exists('BottomEdgeStrokeColor', $this->idmlContext)) ? $this->idmlContext['BottomEdgeStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('BottomEdgeStrokeTint', $this->idmlContext)) ? $this->idmlContext['BottomEdgeStrokeTint'] : '-1';
        $weight = (array_key_exists('BottomEdgeStrokeWeight', $this->idmlContext)) ? $this->idmlContext['BottomEdgeStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('BottomEdgeStrokeType', $this->idmlContext)) ? $this->idmlContext['BottomEdgeStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>