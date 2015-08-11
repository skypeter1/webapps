<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlParagraphStyle.php
 *
 * @class   IdmlParagraphStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlParagraphStyle extends IdmlDeclaredStyle
{
    public function __construct()
    {
        parent::__construct('ParagraphStyle', 'Para', '');

        $this->resetCSS('margin',  '0');
        $this->resetCSS('padding', '0');
        $this->resetCSS('border',  'none');
    }
}
?>