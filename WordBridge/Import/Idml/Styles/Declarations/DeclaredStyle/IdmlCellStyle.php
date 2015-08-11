<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlCellStyle.php
 *
 * @class   IdmlCellStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlCellStyle extends IdmlDeclaredStyle
{
    public function __construct()
    {
        parent::__construct('CellStyle', 'Cell', '');

        $this->resetCSS('margin',  '0');
        $this->resetCSS('padding', '0');
        $this->resetCSS('border',  '1px solid #000000');
        $this->resetCSS('vertical-align',  'top');
    }
}
?>