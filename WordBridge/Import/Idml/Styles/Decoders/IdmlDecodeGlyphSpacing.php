<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeGlyphSpacing.php
 *
 * @class   IdmlDecodeGlyphSpacing
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeGlyphSpacing extends IdmlDecode
{
    public function convert()
    {
        // IDML Specification says DesiredGlyphSpacing is the desired width (as a percentage) of individual
        // characters. (Range: 50 to 200)
        // MinimumGlyphSpacing and MaximumGlyphSpacing do not have CSS equivalents
        //
        // This is an advanced IDML feature for justified paragraphs. This is not the same as Tracking.
        // There is no CSS equivalent
    }
}
?>