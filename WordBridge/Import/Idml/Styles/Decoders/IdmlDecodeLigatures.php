<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeLigatures.php
 *
 * @class   IdmlDecodeLigatures
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeLigatures extends IdmlDecode
{
    public function convert()
    {
        // The Ligatures property indicates whether or not to use single glyphs for character combos like 'fi' and 'fl'.
        // To cover all our bases, we're setting both the text-rendering property and the font-variant-ligatures property.
        // The font-variant-ligatures property is part of the CSS3 spec, and should eventually be supported; however,
        // the support for this property is almost non-existent at the time of this writing.
        // The text-rendering property is not part of the official CSS spec, but is supported by webkit and gecko browsers.

        if ($this->idmlPropertyValue == "true")
        {
            $this->registerCSS('text-rendering', 'optimizeLegibility');
            $this->registerCSS('font-variant-ligatures', 'common-ligatures');
        }
        else
        {
            $this->registerCSS('text-rendering', 'optimizeSpeed');
            $this->registerCSS('font-variant-ligatures', 'none');
        }
    }
}
?>