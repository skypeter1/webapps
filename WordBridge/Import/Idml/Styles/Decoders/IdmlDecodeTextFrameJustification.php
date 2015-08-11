<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTextFrameJustification.php
 *
 * @class   IdmlDecodeTextFrameJustification
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeTextFrameJustification extends IdmlDecode
{
    /*
     * The 'div' used here will appear in the <html> as a child of every section and aside. It is intentionally placed
     * in the html so that this child selector can be used as for vertical alignment of *its* children.
    */
    public function convert()
    {
        $valign = $this->getAppliedStyleKeyValue( 'TextFramePreference->VerticalJustification',  'TopAlign');

        switch($valign)
        {
            case 'BottomAlign':
                $this->registerChildCSS('> div', 'position', 'relative');
                $this->registerChildCSS('> div', 'display', 'table-cell');
                $this->registerChildCSS('> div', 'vertical-align', 'bottom');
                break;

            case 'CenterAlign':
                $this->registerChildCSS('> div', 'position', 'relative');
                $this->registerChildCSS('> div', 'display', 'table-cell');
                $this->registerChildCSS('> div', 'vertical-align', 'middle');
                break;

            case 'TopAlign':
            default:
                break;

        }
    }
}
?>