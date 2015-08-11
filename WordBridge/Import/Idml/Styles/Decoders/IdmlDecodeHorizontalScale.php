<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeHorizontalScale.php
 *
 * @class   IdmlDecodeHorizontalScale
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeHorizontalScale extends IdmlDecode
{
    // IDML Specification says HorizontalScale is the horizontal scaling applied to the text as a
    // percentage of its current size. (Range: 1 to 1000)
    //
    // In order to implement this, some type of transform might be considered.
}
?>