<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlObjectStyle.php
 *
 * @class   IdmlObjectStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlObjectStyle extends IdmlDeclaredStyle
{
    public function __construct()
    {
        parent::__construct('ObjectStyle', 'Obj', '');

        $this->resetCSS('margin',  '0');
        $this->resetCSS('padding', '0');
        $this->resetCSS('border',  'none');
    }
}
?>