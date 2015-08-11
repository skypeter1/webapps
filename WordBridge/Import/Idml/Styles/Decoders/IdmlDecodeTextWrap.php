<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTextWrap.php
 *
 * @class   IdmlDecodeTextWrap
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeTextWrap extends IdmlDecode
{
    /*
     * This variant of the convert function is meaningful only for fixed layout and only on specific elements
     * It is not useful for reflowable books or for style sheets.
     * @param string $leftOrRightPage should be either 'left' or 'right'
     */
    public function convertInSitu($leftOrRightPage)
    {
        switch ($this->idmlPropertyValue)
        {
            case 'SideTowardsSpine':
                $this->registerCSS('float', ($leftOrRightPage == 'left') ? 'right' : 'left');
                break;

            case 'SideAwayFromSpine':
                $this->registerCSS('float', ($leftOrRightPage == 'left') ? 'left' : 'right');
                break;

            default:
                $this->convert();
                break;
        }
    }

    public function convert()
    {
        // TextWrapMode possible values:
        //     None, JumpObjectTextWrap, NextColumnTextWrap, BoundingBoxTextWrap, Contour
        $textWrapMode = $this->getAppliedStyleKeyValue( 'TextWrapPreference->TextWrapMode', 'None' );
        if ($textWrapMode == 'None')
            return;

        // TextWrapSide possible values:
        //    BothSides, LeftSide, RightSide, SideTowardsSpine, SideAwayFromSpine, LargestArea
        $textWrapSide = $this->getAppliedStyleKeyValue( 'TextWrapPreference->TextWrapSide', 'LeftSide' );
        switch($textWrapSide)
        {
            case 'LeftSide':
                $this->registerCSS('float', 'right');
                break;

            case 'BothSides':
            case 'RightSide':
                $this->registerCSS('float', 'left');
                break;

            case 'SideTowardsSpine':
            case 'SideAwayFromSpine':
            default:
                CakeLog::debug("[IdmlDecodeTextWrap:convert] unhandled TextWrapSide=$textWrapSide");
                return;
        }


        $offsetTop    = $this->getAppliedStyleKeyValue( 'TextWrapPreference::Properties::TextWrapOffset->Top',    '0' );
        $offsetLeft   = $this->getAppliedStyleKeyValue( 'TextWrapPreference::Properties::TextWrapOffset->Left',   '0' );
        $offsetBottom = $this->getAppliedStyleKeyValue( 'TextWrapPreference::Properties::TextWrapOffset->Bottom', '0' );
        $offsetRight  = $this->getAppliedStyleKeyValue( 'TextWrapPreference::Properties::TextWrapOffset->Right',  '0' );

        if (($offsetTop    == $offsetLeft)   &&
            ($offsetLeft   == $offsetBottom) &&
            ($offsetBottom == $offsetRight))
        {
            $this->registerCSS('margin', $offsetTop . 'px');
        }
        else
        {
            $this->registerCSS('margin-top',    $offsetTop    . 'px');
            $this->registerCSS('margin-left',   $offsetLeft   . 'px');
            $this->registerCSS('margin-bottom', $offsetBottom . 'px');
            $this->registerCSS('margin-right',  $offsetRight  . 'px');
        }
    }
}
?>