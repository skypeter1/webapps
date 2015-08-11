<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeVerticalScale.php
 *
 * @class   IdmlDecodeVerticalScale
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeVerticalScale extends IdmlDecode
{
    // IDML Specification says VerticalScale is the vertical scaling applied to the text as a percentage
    // of its current size. (Range: 1 to 1000)
    //
    // In order to implement this, some type of transform might be considered.
}
?>