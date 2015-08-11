<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlCharacterStyleGroup.php
 *
 * @class   IdmlCharacterStyleGroup
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');


class IdmlCharacterStyleGroup extends IdmlDeclaredStyleGroup
{
    public function __construct()
    {
        parent::__construct('CharacterStyle');
    }
}
?>
