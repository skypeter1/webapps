<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlTableStyle.php
 *
 * @class   IdmlTableStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlTableStyle extends IdmlDeclaredStyle
{
    public function __construct()
    {
        parent::__construct('TableStyle', 'Table', '');

        $this->resetCSS('margin',  '0');
        $this->resetCSS('padding', '0');
        $this->resetCSS('border',  'none');
        $this->resetCSS('border-spacing', '0');
        $this->resetCSS('border-collapse', 'collapse');     // careful: if you use 'collapse' here, the outer table borders conflict with the cell borders, and none border is shown.
        $this->resetCSS('empty-cells', 'show');
        $this->resetCSS('table-layout', 'fixed');
    }
}
?>