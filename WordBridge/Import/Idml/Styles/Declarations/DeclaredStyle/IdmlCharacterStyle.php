<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlCharacterStyle.php
 *
 * @class   IdmlCharacterStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlCharacterStyle extends IdmlDeclaredStyle
{
    public function __construct()
    {
        parent::__construct('CharacterStyle', 'Char', '');

        $this->resetCSS('margin',  '0');
        $this->resetCSS('padding', '0');
        $this->resetCSS('border',  'none');
    }
}
?>