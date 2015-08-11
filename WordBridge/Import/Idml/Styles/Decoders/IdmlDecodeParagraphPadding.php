<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeParagraphPadding.php
 *
 * @class   IdmlDecodeParagraphPadding
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeParagraphPadding extends IdmlDecode
{
    public function convert()
    {
        $paddingTop    = (array_key_exists('SpaceBefore', $this->idmlContext)) ? $this->idmlContext['SpaceBefore'] : '0';
        $paddingRight  = (array_key_exists('RightIndent', $this->idmlContext)) ? $this->idmlContext['RightIndent'] : '0';
        $paddingBottom = (array_key_exists('SpaceAfter', $this->idmlContext))  ? $this->idmlContext['SpaceAfter']  : '0';
        $paddingLeft   = (array_key_exists('LeftIndent', $this->idmlContext))  ? $this->idmlContext['LeftIndent']  : '0';

        // InDesign allows negative SpaceBefore and SpaceAfter, but CSS does not allow negative padding.
        $paddingTop = max(0, $paddingTop);
        $paddingBottom = max(0, $paddingBottom);

        $paddingTop = round($paddingTop);
        $paddingRight = round($paddingRight);
        $paddingBottom = round($paddingBottom);
        $paddingLeft = round($paddingLeft);

        if ($paddingTop == $paddingRight &&
            $paddingRight == $paddingBottom &&
            $paddingBottom == $paddingLeft)
        {
            $this->registerCSS('padding', $paddingTop . 'px');
        }
        else
        {
            $this->registerCSS('padding', sprintf("%dpx %dpx %dpx %dpx", $paddingTop, $paddingRight, $paddingBottom, $paddingLeft));
        }

        // IDML differs from CSS in it's implementation of space before the first paragraph.
        // IDML only uses SpaceBefore for the second and subsequent paragraphs, so we need CSS
        // to suppress padding-top on the first paragraph.
        if ($paddingTop > 0 && $this->decodeContext == 'Typography')
            $this->registerPseudoCSS('first-of-type', 'padding', sprintf("%dpx %dpx %dpx %dpx", 0, $paddingRight, $paddingBottom, $paddingLeft));
    }
}
?>