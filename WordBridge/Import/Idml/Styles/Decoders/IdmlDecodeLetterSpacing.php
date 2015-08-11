<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeLetterSpacing.php
 *
 * @class   IdmlDecodeLetterSpacing
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeLetterSpacing extends IdmlDecode
{
    public function convert()
    {
        // IDML Specification says DesiredLetterSpacing is the desired letter spacing, specified as a percentage
        // of the built-in space between letters in the font. (Range: -100 to 500)
        // MinimumLetterSpacing and MaximumLetterSpacing do not have CSS equivalents
        //
        // This is an advanced IDML feature for justified paragraphs. This is not the same as Tracking.

        if ($this->idmlPropertyValue == 0)
            $this->registerCSS('letter-spacing', 'normal');
        else
            $this->registerCSS('letter-spacing', $this->idmlPropertyValue . '%');
    }
}
?>