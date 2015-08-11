<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeJustification.php
 *
 * @class   IdmlDecodeJustification
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeJustification extends IdmlDecode
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
            case 'ToBindingSide':
                $this->registerCSS('text-align', ($leftOrRightPage == 'left') ? 'right' : 'left');
                break;

            case 'AwayFromBindingSide':
                $this->registerCSS('text-align', ($leftOrRightPage == 'left') ? 'left' : 'right');
                break;

            default:
                $this->convert();
                break;
        }
    }

    public function convert()
    {
        switch ($this->idmlPropertyValue)
        {
            case 'LeftAlign':
                $this->registerCSS('text-align', 'left');
                break;

            case 'CenterAlign':
                $this->registerCSS('text-align', 'center');
                break;

            case 'RightAlign':
                $this->registerCSS('text-align', 'right');
                break;

            // The CSS property text-align-last is not yet supported by browsers.
            // See http://www.quirksmode.org/css/text/textalignlast.html
            // So these next four options are forward-looking.
            case 'LeftJustified':
                $this->registerCSS('text-align', 'justify');
                $this->registerCSS('text-align-last', 'left');
                break;

            case 'RightJustified':
                $this->registerCSS('text-align', 'justify');
                $this->registerCSS('text-align-last', 'right');
                break;

            case 'CenterJustified':
                $this->registerCSS('text-align', 'justify');
                $this->registerCSS('text-align-last', 'center');
                break;

            case 'FullyJustified':
                $this->registerCSS('text-align', 'justify');
                $this->registerCSS('text-align-last', 'justify');
                break;

            case 'ToBindingSide':
                $this->registerCSS('text-align', 'left');
                break;

            case 'AwayFromBindingSide':
                $this->registerCSS('text-align', 'right');
                break;

            default:
                $this->registerCSS('text-align', 'left');
        }
    }
}
?>