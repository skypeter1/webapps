<?php

/**
 * @package /app/Import/Idml/IdmlParsable.php
 *
 * @class   IdmlParsable
 *
 * @description Just a simple interface that allows us to "parse" and create objects without knowing what type they are
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

interface IdmlParsable
{
    /**
     * parse DOMElement and populate the parsable Idml Object with it.
     */
      public function parse(DOMElement $node);
 }

?>
