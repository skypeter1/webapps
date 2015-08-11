<?php

/**
 * @package /app/Import/Idml/IdmlMasterSpread.php
 *  
 * @class   IdmlMasterSpread
 * 
 * @description This class a special type of IdmlSpread that contains pages and text frames that should be applied
 *              to all _real_ spreads which reference this master spread.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlSpread', 'Import/Idml');


class IdmlMasterSpread extends IdmlSpread
{
    /** The constructor
     * @param IdmlPackage $idmlPackage is the parent object
     */
    public function __construct($idmlPackage)
    {
        parent::__construct($idmlPackage);
        $this->xmlRootName = 'MasterSpread';
    }


    /**
     * Get the page that is on the left side of the spread
     * @return IdmlPage or null
     */
    public function getLeftPage()
    {
        // If the master spread only contains a single page, use it regardless of whether it is left or right
        if (count($this->pages) == 1)
        {
            return $this->pages[0];
        }

        foreach($this->pages as $page)
        {
            if ($page->pagePosition == 'left')
                return $page;
        }

        return null;
    }


    /**
     * Get the page that is on the right side of the spread
     * @return IdmlPage or null
     */
    public function getRightPage()
    {
        // If the master spread only contains a single page, use it regardless of whether it is left or right
        if (count($this->pages) == 1)
        {
            return $this->pages[0];
        }

        foreach($this->pages as $page)
        {
            if ($page->pagePosition == 'right')
                return $page;
        }

        return null;
    }

}

?>
