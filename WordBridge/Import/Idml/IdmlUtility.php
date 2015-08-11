<?php

/**
 * @package /app/Import/Idml/IdmlUtility.php
 *
 * @class   IdmlUtility
 *
 * @description A class containing static member functions only, to be used as helpers with the IDML library.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
class IdmlUtility
{
    /**
     *  A tracing function for development and debugging
     * This is enabled when the php.ini 'error_reporting' has E-USER-NOTICE turned on.
     * This function accepts any number of string arguments, and concatenates them together
     * before writing to the php_error.log
     */
    static public function trace()
    {
        // Enable only if the php.ini 'error_reporting' option is set to show E_USER_NOTICE
        CakeLog::debug(implode(" ", func_get_args()));
    }

    
}

?>
