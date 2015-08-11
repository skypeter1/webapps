<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeWordSpacing.php
 *
 * @class   IdmlDecodeWordSpacing
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeWordSpacing extends IdmlDecode
{
    public function convert()
    {
        // IDML Specification says DesiredWordSpacing is the desired word spacing, specified as a percentage
        // of the font word space value. (Range: 0 to 1000)
        // MinimumWordSpacing and MaximumWordSpacing do not have CSS equivalents
        //
        // This is an advanced IDML feature for justified paragraphs. This is not the same as Tracking.

        if ($this->idmlPropertyValue == 100)
            $this->registerCSS('word-spacing', 'normal');
        else
            $this->registerCSS('word-spacing', $this->idmlPropertyValue . '%');
    }
}
?>