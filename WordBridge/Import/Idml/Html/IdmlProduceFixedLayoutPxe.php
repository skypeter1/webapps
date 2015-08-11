<?php
/**
 * @package /app/Import/Idml/IdmlProduceFixedLayout.php
 * 
 * @class   IdmlProduceFixedLayout
 * 
 * @description Creates the HTML output for a fixed layout book respecting the IdmlPage hierarchy where each
 *              InDesign page will become an ePub page.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlProduceHtmlPxe', 'Import/Idml/Html');


class IdmlProduceFixedLayoutPxe extends IdmlProduceHtmlPxe
{
    /**
     * When visiting IdmlPages for a fixed layout book, start a new ePub page.
     */
    public function visitPage(IdmlPage $item, $depth = 0)
    {
        $this->clearPage();

        $progressUpdater = IdmlAssembler::getProgressUpdater();
        if ($progressUpdater)
        {
            $progressUpdater->incrementStep();
        }
    }
    
    /**
     * When visiting IdmlPages for a fixed layout book, save all page elements when the page end is reached.
     */
    public function visitPageEnd(IdmlPage $page, $depth = 0)
    {
        $this->savePage($page);
    }    

    /**
     * Fixed layout books should ignore any 'XMLTag/ChaucerEditorBreak' 
    */
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0)
    {
    }
    
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0)
    {
    }
    
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
    }
}
?>
