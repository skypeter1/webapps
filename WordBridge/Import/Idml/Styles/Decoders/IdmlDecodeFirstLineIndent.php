<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeFirstLineIndent.php
 *
 * @class   IdmlDecodeFirstLineIndent
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Styles/Decoders');


class IdmlDecodeFirstLineIndent extends IdmlDecode
{
    public function convert()
    {
        // IDML Specification says FirstLineIndent is the amount to indent the first line of a paragraph, in pixels.
        // It can be negative in which case a 'hanging indent' is created.
        if ($this->idmlPropertyValue != 0)
        {
            $value = round($this->idmlPropertyValue,1);
            $this->registerCSS('text-indent', $value . 'px');
        }

        // IDML also has a 'LastLineIndent' which cannot be represented in CSS
    }
}
?>