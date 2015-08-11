<?php
/**
 * @package /app/Import/Idml/IdmlProduceReflowable.php
 * 
 * @class   IdmlProduceReflowable
 * 
 * @description Creates the HTML output for a PXE reflowable book respecting
 *              the special <XMLElement markupTag == 'XMLTag/ChaucerEditorBreak'>
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlProduceHtmlPxe', 'Import/Idml/Html');


class IdmlProduceReflowablePxe extends IdmlProduceHtmlPxe
{
    /**
     * When visiting IdmlPages for a reflowable book, nothing needs to be done. Page breaks are handled by the special
     * 'ChaucerEditorBreak' feature.
     */
    public function visitPage(IdmlPage $item, $depth = 0)
    {
        // This progress indicator is simply for user feedback, it has nothing to do with book construction or page breaks.
        $progressUpdater = IdmlAssembler::getProgressUpdater();
        if ($progressUpdater)
        {
            $progressUpdater->incrementStep();
        }
    }
    
    /**
     * Visit page end, has no special meaning in reflowable books
     */
    public function visitPageEnd(IdmlPage $page, $depth = 0)
    {
    }
   
    /**
     * XmlElement is the InDesign 'tag' feature, which we use mostly for PXE, but as a special case,
     * we use 'XMLTag/ChaucerEditorBreak' to allow book designers to specify where they want to produce
     * an ePub chapter break.
    */
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0)
    {
        // DEBUG return immediately to get everything on one long page
        // return;
        
        if ($element->markupTag == 'XMLTag/ChaucerEditorBreak')
        {
            $stack = $this->getAncestors($element,'IdmlPage');
            
            $this->addPageElement("<!-- Begin ChaucerEditorBreak -->", $depth);
            $this->rewind($stack, $depth);
        
            $this->savePage();
            $this->clearPage();
            
            $this->fastForward($stack, $depth);
            $this->addPageElement("<!-- End ChaucerEditorBreak -->", $depth);
        }
        else
        {
            if(is_subclass_of($element, "IdmlElement"))
            {
                parent::visitXmlElement($element, $depth);
            }
        }
    }

    
    /**
     *  Nothing to do, because the special 'XMLTag/ChaucerEditorBreak' is handled as if
     * it were a "caret" at the opening of the XMLElement, rather than an "enclosing" element with children.
     */
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0)
    {
        parent::visitXmlElementEnd($element, $depth);
    }
    
    /**
     * Flush all remaining elements when we reach the end of each package.
     * Recall that a "package" is our name for a single InDesign document.
     * This means that if the user has not specified any 'ChaucerEditorBreak' tags,
     * the output will all be in a single ePub chapter.
     */
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
        $this->savePage();
        $this->clearPage();
    }

}
?>
