<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlMovie.php
 *
 * @class   IdmlMovie
 *
 * @description Parser for InDesign <Movie>.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlMedia', 'Import/Idml/PageElements');


class IdmlMovie extends IdmlMedia
{
    /**
     * Parse from DOM node.
      *`
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->tag = 'video';

        parent::parse($node);

        $this->boundary = IdmlParserHelper::parseBoundary($node);

        // Get all the IDML attributes needed for setting HTML attributes
        $controlsAttrib = $node->hasAttribute('ShowControls') ? $node->getAttribute('ShowControls') : 'true';
        $loopAttrib = $node->hasAttribute('MovieLoop') ? $node->getAttribute('MovieLoop') : 'false';
        $autoplayAttrib = $node->hasAttribute('PlayOnPageTurn') ? $node->getAttribute('PlayOnPageTurn') : 'false';
        $playmodeAttrib = $node->hasAttribute('PlayMode') ? $node->getAttribute('PlayMode') : 'Once';

        // Set the various HTML attributes
        $this->controls = ($controlsAttrib == 'true') ? true : false;

        if ($playmodeAttrib == 'Once' || $autoplayAttrib == 'true')
        {
            $this->autoplay = true;
        }
        else
        {
            $this->autoplay = false;
        }

        if ($loopAttrib == 'true' || $playmodeAttrib == 'RepeatPlay')
        {
            $this->loop = true;
        }
        else
        {
            $this->loop = false;
        }
    }

}
