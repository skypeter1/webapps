<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodePointSizeAndLeading.php
 *
 * @class   IdmlDecodePointSizeAndLeading
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodePointSizeAndLeading extends IdmlDecode
{
    // These two are handled together because browsers don't obey 'line-height' when 'font-size' is not specified.
    public function convert()
    {
        // Ideally, the $fallbackPointSize shouldn't be hard-coded. Use something along these lines:
        // $fallbackPointSize = IdmlAssembler::getInstance()->getCurrentPackage()->preferences->fallback['PointSize'];
        $fallbackPointSize = 12;

        $fontSize = (array_key_exists('PointSize', $this->idmlContext)) ? $this->idmlContext['PointSize'] : $fallbackPointSize;
        $fontSize .= 'px';

        $leading = $this->getAppliedStyleKeyValue('Properties::Leading', 'Auto');
        if ($leading == 'Auto')
            $leading = $this->getAppliedStyleKeyValue('AutoLeading', '120') . '%';
        else
            $leading .= 'px';

        $this->registerCSS('font-size', $fontSize);
        $this->registerCSS('line-height', $leading);
    }
}
?>