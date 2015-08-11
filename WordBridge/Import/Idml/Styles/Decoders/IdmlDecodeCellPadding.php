<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCellPadding.php
 *
 * @class   IdmlDecodeCellPadding
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeCellPadding extends IdmlDecode
{
    public function convert()
    {
        $paddingTop    = (array_key_exists('TopInset',    $this->idmlContext)) ? $this->idmlContext['TopInset']    : '0';
        $paddingRight  = (array_key_exists('RightInset',  $this->idmlContext)) ? $this->idmlContext['RightInset']  : '0';
        $paddingBottom = (array_key_exists('BottomInset', $this->idmlContext)) ? $this->idmlContext['BottomInset'] : '0';
        $paddingLeft   = (array_key_exists('LeftInset',   $this->idmlContext)) ? $this->idmlContext['LeftInset']   : '0';

        $paddingTop = round($paddingTop);
        $paddingRight = round($paddingRight);
        $paddingBottom = round($paddingBottom);
        $paddingLeft = round($paddingLeft);

        if ($paddingTop == $paddingRight &&
            $paddingRight == $paddingBottom &&
            $paddingBottom == $paddingLeft)
        {
            $this->registerCSS('padding', $paddingTop . 'px');
        }
        else
        {
            $this->registerCSS('padding', sprintf("%dpx %dpx %dpx %dpx", $paddingTop, $paddingRight, $paddingBottom, $paddingLeft));
        }
    }
}
?>