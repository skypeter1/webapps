<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCellTopStroke.php
 *
 * @class   IdmlDecodeCellTopStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Styles/Decoders');


class IdmlDecodeCellTopStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-top'] = 'border-top';
        $colorRef = (array_key_exists('TopEdgeStrokeColor', $this->idmlContext)) ? $this->idmlContext['TopEdgeStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('TopEdgeStrokeTint', $this->idmlContext)) ? $this->idmlContext['TopEdgeStrokeTint'] : '-1';
        $weight = (array_key_exists('TopEdgeStrokeWeight', $this->idmlContext)) ? $this->idmlContext['TopEdgeStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('TopEdgeStrokeType', $this->idmlContext)) ? $this->idmlContext['TopEdgeStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>