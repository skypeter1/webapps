<?php
/**
 * @package /app/Import/Idml/IdmlProduceReflowable.php
 * 
 * @class   IdmlProduceReflowable
 * 
 * @description Creates the HTML output for a non-PXE reflowable book where each <TextFrame> will become a a page
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlProduceHtml', 'Import/Idml/Html');

class IdmlProduceReflowable extends IdmlProduceHtml
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Since in reflowable, a text frame defines a page, the page UID is set here,
     *   then the parent is called.
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    public function visitTextFrame(IdmlTextFrame $element, $depth = 0)
    {
        $this->pageObject = $element;

        return parent::visitTextFrame($element, $depth);
    }

    /**
     * For standalone text frames, close the section and start a new page.
     * For embedded text frames, close the aside, but continue processing the current page.
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0)
    {
        $this->initData();
        $this->writeSectionEnd($element, $depth);

        $pageFilename = $this->savePage();
        $this->pageNameXref[$element->UID] = $pageFilename . '.xhtml';

        $this->clearPage();
    }

    /**
     * If the story is not embedded, this is the end of the page, so close any open elements
     * @param IdmlStory $item
     * @param int $depth
     */
    public function visitStoryEnd(IdmlStory $item, $depth = 0)
    {
        if (!$item->idmlTextFrame->isEmbedded())
        {
            $this->closeFinalElements($depth);
        }
    }

    /**
     * For reflowable content, a tab is always a tab: insert a tab character into the content.
     * @param IdmlTab $element
     * @param int $depth
     */
    public function visitTab(IdmlTab $element, $depth=0)
    {
        $this->addPageElement("\x09", $depth);
    }

    /**
     * Reflowable content simply adds tab characters when tabs are encountered. No special processing is done for the 1st tab.
     * @param IdmlParagraphRange $paragraph
     * @param int $depth
     */
    protected function processFirstTab(IdmlParagraphRange $paragraph, $depth=0)
    {
    }

    /**
     * Returns the text frame UID mapped to the specified page UID, for renaming the page in the href attribute
     * @param string $pageUID
     * @return string text frame UID
     */
    public function getDestinationPage($pageUID)
    {
        return IdmlHyperlinkManager::getInstance()->getTextFrameUID($pageUID);
    }

    /**
     * Return the text frame ID, which is used in generating the destination page for a hyperlink.
     * This function is also coded in IdmlProduceFixedLayout, which returns the page id from the same array.
     * @param $uidArray
     * @return mixed
     */
    public function getHyperlinkPage($uidArray)
    {
        return $uidArray['textFrameUID'];
    }


    /**
     * The getDimensionCSS function should return a CSS declaration for the element's width and height.
     */
    public function getDimensionCSS(IdmlElement $element)
    {
        $weight = $element->getComputedBorders();
        $twice_weight = $weight * 2;

        switch( get_class($element) )
        {
            case 'IdmlTextFrame':
                if($element->isEmbedded())
                    return sprintf("width:%spx; height:%spx;", round($twice_weight + $element->boundary->getWidth()), round($twice_weight + $element->boundary->getHeight()));
                else
                    return '';

            case 'IdmlRectangle':

                // If a rectangle's sole child is a video or audio, don't set the dimensions, since IDML does not account for controls.
                // Otherwise, fall through to the default behavior.
                if (count($element->childrenElements) == 1 && in_array(get_class($element->childrenElements[0]), array('IdmlMovie', 'IdmlSound')))
                {
                    return '';
                }

            case 'IdmlGroup':
                return sprintf("width:%spx; height:%spx;", round($twice_weight + $element->boundary->getWidth()), round($twice_weight + $element->boundary->getHeight()));

            default:
                return '';
        }
    }


    /**
     * The getPositionCSS function should return a CSS declaration for the element's top and left.
     */
    public function getPositionCSS(IdmlElement $element)
    {
        return '';
    }
}