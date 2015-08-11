<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTableTopStroke.php
 *
 * @class   IdmlDecodeTableTopStroke
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecodeStroke', 'Import/Idml/Decoders');


class IdmlDecodeTableTopStroke extends IdmlDecodeStroke
{
    public function convert()
    {
        $this->cssTarget['border-top'] = 'border-top';
        $colorRef = (array_key_exists('TopBorderStrokeColor', $this->idmlContext)) ? $this->idmlContext['TopBorderStrokeColor'] : 'Color/Black';
        $tint = (array_key_exists('TopBorderStrokeTint', $this->idmlContext)) ? $this->idmlContext['TopBorderStrokeTint'] : '-1';
        $weight = (array_key_exists('TopBorderStrokeWeight', $this->idmlContext)) ? $this->idmlContext['TopBorderStrokeWeight'] : '1';
        $strokeRef = (array_key_exists('TopBorderStrokeType', $this->idmlContext)) ? $this->idmlContext['TopBorderStrokeType'] : 'StrokeStyle/$ID/Solid';

        $this->convertStroke($this->cssTarget, $colorRef, $tint, $weight, $strokeRef);
    }
}
?>