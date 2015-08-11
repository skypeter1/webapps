<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeTextDecoration.php
 *
 * @class   IdmlDecodeTextDecoration
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeTextDecoration extends IdmlDecode
{
    public function convert()
    {
        $strikethru = (array_key_exists('StrikeThru', $this->idmlContext)) ? $this->idmlContext['StrikeThru'] : 'false';
        $underline = (array_key_exists('Underline', $this->idmlContext)) ? $this->idmlContext['Underline'] : 'false';

        // If Properties::UnderlineColor is set, the underlining was probably used to create a background color, so don't set text-decoration
        $underlineColor = (array_key_exists('Properties::UnderlineColor', $this->idmlContext)) ? $this->idmlContext['Properties::UnderlineColor'] : false;

        if ($strikethru == 'true')
            $this->registerCSS('text-decoration', 'line-through');      // this wins the tie if both are specified
        else if ($underline == 'true' && $underlineColor === false)
            $this->registerCSS('text-decoration', 'underline');

        // Note that CSS also has an 'overline' property but IDML does not
    }
}
?>