<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlObjectStyleGroup.php
 *
 * @class   IdmlObjectStyleGroup
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');


class IdmlObjectStyleGroup extends IdmlDeclaredStyleGroup
{
    public function __construct()
    {
        parent::__construct('ObjectStyle');
    }
}
?>
