<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeInsetSpacing.php
 *
 * @class   IdmlDecodeInsetSpacing
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeInsetSpacing extends IdmlDecode
{
    public function convert()
    {
        $insetTop    = $this->getAppliedStyleKeyValue( 'TextFramePreference::Properties::InsetSpacing::ListItem',  '0' );
        $insetLeft   = $this->getAppliedStyleKeyValue( 'TextFramePreference::Properties::InsetSpacing::ListItem2', '0' );
        $insetBottom = $this->getAppliedStyleKeyValue( 'TextFramePreference::Properties::InsetSpacing::ListItem3', '0' );
        $insetRight  = $this->getAppliedStyleKeyValue( 'TextFramePreference::Properties::InsetSpacing::ListItem4', '0' );

        $insetTop = round($insetTop);
        $insetLeft = round($insetLeft);
        $insetBottom = round($insetBottom);
        $insetRight = round($insetRight);

        if (($insetTop    == $insetLeft)   &&
            ($insetLeft   == $insetBottom) &&
            ($insetBottom == $insetRight))
        {
            $this->registerCSS('padding', $insetTop . 'px');
        }
        else
        {
            $this->registerCSS('padding-top',    $insetTop    . 'px');
            $this->registerCSS('padding-left',   $insetLeft   . 'px');
            $this->registerCSS('padding-bottom', $insetBottom . 'px');
            $this->registerCSS('padding-right',  $insetRight  . 'px');
        }
    }
}
?>