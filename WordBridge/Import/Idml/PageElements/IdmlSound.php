<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlSound.php
 *
 * @class   IdmlSound
 *
 * @description Parser for InDesign <Sound>.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlMedia', 'Import/Idml/PageElements');


class IdmlSound extends IdmlMedia
{
    /**
     * Parse from DOM node.
      *
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->tag = 'audio';

        parent::parse($node);

        $this->boundary = IdmlParserHelper::parseBoundary($node);

        $loopAttrib = $node->hasAttribute('SoundLoop') ? $node->getAttribute('SoundLoop') : 'false';
        $autoplayAttrib = $node->hasAttribute('PlayOnPageTurn') ? $node->getAttribute('PlayOnPageTurn') : 'false';

        $this->loop = ($loopAttrib == 'true') ? true : false;
        $this->autoplay = ($autoplayAttrib == 'true') ? true : false;
    }
}
