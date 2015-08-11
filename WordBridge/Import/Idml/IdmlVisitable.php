<?php

/**
 * @package /app/Import/Idml/IdmlVisitable.php
 *
 * @class   IdmlVisitable
 *
 * @description Just a simple visitable interface that follows the classic "visitor pattern".
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

interface IdmlVisitable
{
    /**
     * Accept visitor.
     */
    public function accept(IdmlVisitor $visitor, $depth = 0);
}

?>
