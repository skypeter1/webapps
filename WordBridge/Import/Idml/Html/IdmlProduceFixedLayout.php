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

App::uses('IdmlProduceHtml', 'Import/Idml/Html');


class IdmlProduceFixedLayout extends IdmlProduceHtml
{
    /**
     * The getDimensionCSS function should return a CSS declaration for the element's width and height.
     */
    public function getDimensionCSS(IdmlElement $element)
    {
        switch( get_class($element) )
        {
            case 'IdmlRectangle':

                // If a rectangle's sole child is a video or audio, don't set the dimensions, since IDML does not account for controls.
                // Otherwise, fall through to the default behavior.
                if (count($element->childrenElements) == 1 && in_array(get_class($element->childrenElements[0]), array('IdmlMovie', 'IdmlSound')))
                {
                    return '';
                }

            case 'IdmlTextFrame':
            case 'IdmlGroup':

                if (!isset($element->boundary))
                {
                    CakeLog::debug("[IdmlProduceFixedLayout::getDimensionCSS] Expecting boundary to provide dimensions for " . get_class($element));
                    return '';
                }

                $twice_weight = $element->getComputedBorders() * 2;
                return sprintf("width:%spx; height:%spx;",
                    round($element->boundary->getWidth() + $twice_weight),
                    round($element->boundary->getHeight() + $twice_weight));

            default:
                return '';
        }
    }


    /**
     * The getPositionCSS function should return a CSS declaration for the element's top and left.
     * @param IdmlElement $element
     * @return string - containing style attributes (may be empty)
     */
    public function getPositionCSS(IdmlElement $element)
    {
        $page = $this->getPage($element);

        if (is_null($page))
        {
            throw new MissingComponentException('No page was found for ' . get_class($element) . '; UID ' . $element->UID);
        }

        $elementClass = get_class($element);

        switch( $elementClass )
        {
            case 'IdmlTextFrame':
            case 'IdmlRectangle':
            case 'IdmlGroup':
            case 'IdmlPolygon':
            case 'IdmlOval':
            case 'IdmlGraphicLine':

                if (!isset($element->boundary))
                {
                    CakeLog::debug("[IdmlProduceFixedLayout::getDimensionCSS] Expecting boundary to provide position for " . get_class($element));
                    return '';
                }

                if ($element->isEmbedded())
                {
                    // Get the positioning parameters
                    $positionParams = $this->getPositionParams($element, $page);
                    $vertRefPoint = $positionParams['vertRefPoint'];

                    // If there are values for IDML's AnchoredPosition property other than 'Anchored' and 'InlinePosition' we don't support them
                    if ($positionParams['anchorPosition'] != 'Anchored' && $positionParams['anchorPosition'] != 'InlinePosition')
                    {
                        return 'position:relative;';
                    }

                    // If no anchor offset is set, set position to relative and exit
                    if (is_null($positionParams['xOffset']) && is_null($positionParams['yOffset']))
                    {
                        return 'position:relative;';
                    }

                    // Adjust offsets based on reference point
                    $this->setRelativeOffsets($element, $positionParams, $page);
                    list($left, $top) = $this->adjustOffsetsToRefPoint($element);
                }

                else  // ! $element->isEmbedded(), or $element belongs to IdmlGroup
                {
                    $vertRefPoint = null;
                    list($left, $top) = $this->getElementPosition($element, $page);
                }

                return $this->getStyleString($element, $left, $top, $vertRefPoint);

            default:
                return '';
        }
    }

    /**
     * Obtain and manipulate the position parameters for the current element from IDML
     * @param IdmlElement $element
     * @param IdmlPage $page
     * @return array $positionParams
     */
    protected function getPositionParams(IdmlElement $element, IdmlPage $page)
    {
        // Get the raw values of all the IDML properties associated with positioning
        $positionParams['xOffset'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorXoffset', null);
        $positionParams['yOffset'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorYoffset', null);
        $positionParams['anchorPosition'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchoredPosition', null);
        $positionParams['spineRelative'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->SpineRelative', null);
        $positionParams['anchorPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorPoint', null);
        $positionParams['horizAlignment'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->HorizontalAlignment', null);
        $positionParams['horizRefPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->HorizontalReferencePoint', null);
        $positionParams['vertAlignment'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->VerticalAlignment', null);
        $positionParams['vertRefPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->VerticalReferencePoint', null);

        // Reverse sign on offset values: IDML displaces positive offsets right, but HTML displaces positive offsets left
        $positionParams['xOffset'] *= -1;
        $positionParams['yOffset'] *= -1;

        // If this is the right-hand page and the object's position is spine-relative,
        //   switch horizontal alignment parameters and reverse the sign on the x-offset.
        if ($page->pagePosition == 'right' && $positionParams['spineRelative'] == 'true' ) {
            $positionParams['horizAlignment'] = $this->switchPositionAlignment($positionParams['horizAlignment']);
            $positionParams['anchorPoint'] = $this->switchPositionAlignment($positionParams['anchorPoint']);
            $positionParams['xOffset'] *= -1;
        }

        return $positionParams;
    }

    /**
     * @param IdmlElement $element
     * @param IdmlPage $page
     * @return array - top and left position offsets for the element
     */
    protected function getElementPosition(IdmlElement $element, $page)
    {
        $idssCoordinates = IdmlBoundary::transform($element->boundary, $element->transformation);

        $pageIDSS = $page->idssCoordinates;
        $pageAdjustedBoundary = $idssCoordinates->applyOffset($pageIDSS->left, $pageIDSS->top);
        $pageAdjustedBoundary->roundToIntegers();

        $weight = $element->getComputedBorders();

        $top = $pageAdjustedBoundary->top - $weight;
        $left = $pageAdjustedBoundary->left - $weight;

        $element->setPosition(array('left'=>$left, 'top'=>$top));

        return $this->adjustOffsetsToRefPoint($element);
    }

    /**
     * Determines page-relative x and y offsets by calling the appropriate method for the enclosing object.
     * These are set on the object--they are used by descendants and used to set the context-relative offsets
     * @param IdmlElement $element
     * @param array $positionParams - positioning data from IDML
     * @param IdmlPage $page
     */
    protected function setRelativeOffsets(IdmlElement $element, $positionParams, IdmlPage $page)
    {
        if ($positionParams['anchorPosition'] == 'InlinePosition')
        {
            $xOffset = $positionParams['xOffset'];
            $yOffset = $positionParams['yOffset'];
        }
        elseif ($positionParams['horizRefPoint'] == 'PageEdge')
        {
            list ($xOffset, $yOffset) = $this->getPageOffsets($element, $positionParams, $page);
        }
        else
        {
            list($xOffset, $yOffset) = $this->getTextFrameOffsets($element, $positionParams);
        }

        $element->setPosition(array('left'=>$xOffset, 'top'=>$yOffset));
    }

    /**
     * Takes the element's page-relative position and returns the left and top CSS offsets
     * These CSS offsets are relative to the closest positioned ancestor, which must be found by crawling the hierarchy
     * @param IdmlElement $element
     * @return array
     */
    protected function adjustOffsetsToRefPoint(IdmlElement $element)
    {
        // Get the element's page-relative position and first ancestor
        $xOffset = $element->getPosition('left');
        $yOffset = $element->getPosition('top');

        // If this element has no ancestors, return its page-relative offsets
        if (!isset($element->parentElement))
        {
            return array($xOffset, $yOffset);
        }

        $ancestor = $element->parentElement;

        while (!is_null($ancestor))
        {
            // SVG elements are produced as siblings, so they are never an ancestor in produced html
            // Stories aren't IdmlElements, and so have no position. But they may have ancestors that do.
            if (is_a($ancestor, 'IdmlSvgShape') || is_a($ancestor, 'IdmlStory'))
            {
                $ancestor = $ancestor->parentElement;
                continue;
            }

            // If the ancestor has no position, continue to the next ancestor
            if (!$ancestor->hasPosition('left') || !$ancestor->hasPosition('top'))
            {
                $ancestor = $ancestor->parentElement;
                continue;
            }

            // If we encountered an IdmlStory, use its text frame
            if (is_a($ancestor, 'IdmlStory'))
            {
                $ancestor = $ancestor->idmlTextFrame;
                continue;
            }

            // If the ancestor's positions are not set, get the next ancestor
            if (!$ancestor->hasPositions())
            {
                $ancestor = $ancestor->parentElement;
                continue;
            }

            $ancestorLeft = $ancestor->getPosition('left');
            $ancestorTop = $ancestor->getPosition('top');

            // If the ancestor is positioned, return offsets calculated relative to the ancestor
            if (isset($ancestorLeft) && !is_null($ancestorLeft))
            {
                return array($xOffset - $ancestorLeft, $yOffset - $ancestorTop);
            }

            // Step up the chain and reiterate
            $ancestor = $ancestor->parentElement;
        }

        // No positioned ancestor was found: return page-relative position
        return array($xOffset, $yOffset);
    }

    /**
     * Calculate the final offsets for top and left for elements positioned relative to a text frame.
     * @param IdmlElement $element
     * @param array $positionParams - An array of parameters for positioning from IDML
     * @return array x and y offsets
     */
    protected function getTextFrameOffsets(IdmlElement $element, $positionParams)
    {
        $xOffset = $positionParams['xOffset'];
        $yOffset = $positionParams['yOffset'];

        $story = $this->getAncestor($element, 'IdmlStory');
        $anchorWidth = $story->idmlTextFrame->boundary->getWidth();
        $anchorHeight = $story->idmlTextFrame->boundary->getHeight();

        $xAdjustment = $this->calculateAdjustment('x', $element, $positionParams, $anchorWidth);
        $yAdjustment = $this->calculateAdjustment('y', $element, $positionParams, $anchorHeight);

        $xOffset += $xAdjustment;
        $yOffset += $yAdjustment;

        return array($xOffset, $yOffset);
    }

    /**
     * Calculate the final offsets for top and left for elements positioned relative to the page edge.
     * @param IdmlElement $element
     * @param $positionParams
     * @param IdmlPage $page
     * @return array
     */
    protected function getPageOffsets(IdmlElement $element, $positionParams, IdmlPage $page)
    {
        $story = $this->getAncestor($element, 'IdmlStory');
        $anchorWidth = $page->boundary->getWidth();
        $anchorHeight = $page->boundary->getHeight();

        $xOffset = $positionParams['xOffset'] - $story->idmlTextFrame->getPosition('left');
        $yOffset = $positionParams['yOffset'] - $story->idmlTextFrame->getPosition('top');

        $xAdjustment = $this->calculateAdjustment('x', $element, $positionParams, $anchorWidth);
        $yAdjustment = $this->calculateAdjustment('y', $element, $positionParams, $anchorHeight);

        $xOffset += $xAdjustment;
        $yOffset += $yAdjustment;

        return array($xOffset, $yOffset);
    }

    /**
     * Calculates the adjustment which must be applied to the left or top offset of an embedded element.
     * This same method is used for x and y offsets, and for multiple enclosing elements (text frames or pages).
     * The code assumes that all anchored objects' positions have similar derivations:
     *   There's a basic offset which dictates the position when the left edges of the element and its container are aligned.
     *   If the element's right edge is aligned with the container, tbe element's width must be subtracted from the final offset.
     *   If the element is aligned with the container's right edge, the width of the container must be added.
     * @param string $axis - 'x' or 'y'
     * @param IdmlElement $element
     * @param array $positionParams
     * @param $anchorSize - width or height of either the enclosing text frame or the enclosing page
     * @return float|int
     */
    protected function  calculateAdjustment($axis, IdmlElement $element, $positionParams, $anchorSize)
    {
        $adjustment = 0;

        $anchorPoint = $positionParams['anchorPoint'];

        // Set the proper values for processing which depend on axis orientation
        if ($axis == 'x')
        {
            $firstCorner = 'Left';
            $lastCorner = 'Right';
            $dimensionFunction = 'getWidth';
            $alignment = 'horizAlignment';
        }
        else
        {
            $firstCorner = 'Top';
            $lastCorner = 'Bottom';
            $dimensionFunction = 'getHeight';
            $alignment = 'vertAlignment';
        }

        // Add the element's width (or 1/2 of it), depending on which of its corners is aligned to the container.
        if (strpos($anchorPoint, $lastCorner) !== false)
        {
            $adjustment -= $element->boundary->$dimensionFunction();
        }
        elseif (strpos($anchorPoint, $firstCorner) === false)  // Not left, right, top, or bottom, so it must be center
        {
            $adjustment -= ($element->boundary->$dimensionFunction() / 2);
        }

        // Add the container's width (or 1/2 of it), depending on what part of it the element aligns to.
        if (strpos($positionParams[$alignment], $lastCorner) !== false)
        {
            $adjustment += $anchorSize;
        }
        elseif (strpos($positionParams[$alignment], 'Center') !== false)
        {
            $adjustment += $anchorSize / 2;
        }

        return $adjustment;
    }

    /**
     * This method switches the alignment of the given parameter.
     * If the parameter is right-oriented, it gets switched to left-oriented, and vice versa.
     * @param string $param
     * @return string
     */
    private function switchPositionAlignment($param)
    {
        if (strpos($param, 'Right') !== false)
        {
            $param = str_replace('Right', 'Left', $param);
        }
        elseif (strpos($param, 'Left') !== false)
        {
            $param = str_replace('Left', 'Right', $param);
        }

        return $param;
    }

    /**
     * Utility function to create an array of position styles and convert to a string.
     * @param IdmlElement $element
     * @param float $left
     * @param float $top
     * @param string $vertRefPoint - used only for embedded elements
     * @return string representation of styles
     */
    public function getStyleString(IdmlElement $element, $left, $top, $vertRefPoint)
    {
        // Create and populate the array with position, left, and top
        $styles = array('position'=>'absolute');

        // Assign final left and top offset values
        if (!is_null($left))
        {
            $styles['left'] = $left . 'px';
        }

        // Don't supply a top value for an embedded element if the VerticalReferencePoint is unsupported.
        if (!$element->isEmbedded() ||
            (!is_null($top) && !in_array($vertRefPoint, array('LineBaseline', 'Capheight', 'TopOfLeading'))))
        {
            $styles['top'] = $top . 'px';
        }

        // Now convert the array to a string and return it.
        $styleString = "";

        foreach($styles as $name=>$value)
        {
            if(strlen($value)>0)
            {
                $styleString .= sprintf( "%s:%s;", $name, $value);
            }
        }

        return trim($styleString);
    }

    /**
     * Find and return the IdmlPage object associated with the specified IdmlELement
     * @param IdmlElement $element
     * @return IdmlPage
     */
    protected function getPage(IdmlElement $element)
    {
        if (isset($this->pageObject)) {
            return $this->pageObject;
        }

        if (isset($element->page)) {
            return $element->page;
        }

        // If page object isn't set on the element or the producer, crawl the class hierarchy to locate it.
        $searchObject = $element;

        while (isset($searchObject->parentElement))
        {
            if (isset($searchObject->parentElement->page) && is_a($searchObject->parentElement->page, 'IdmlPage'))
            {
                return $searchObject->parentElement->page;
            }

            if (is_a($searchObject->parentElement, 'IdmlStory'))
            {
                if (isset($searchObject->parentElement->idmlTextFrame) && !is_null($searchObject->parentElement->idmlTextFrame))
                {
                    if (isset($searchObject->parentElement->idmlTextFrame->page) && !is_null($searchObject->parentElement->idmlTextFrame->page))
                    {
                        return $searchObject->parentElement->idmlTextFrame->page;
                    }
                    else
                    {
                        $searchObject = $searchObject->parentElement->idmlTextFrame;
                    }
                }
            }
            else
            {
                $searchObject = $searchObject->parentElement;
            }
        }

        return null;
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
        parent::visitTextFrameEnd($element, $depth);
    }

    /**
     * When visiting IdmlPages for a fixed layout book, start a new ePub page.
     */
    public function visitPage(IdmlPage $element, $depth = 0)
    {
        $this->pageObject = $element;

        $this->clearPage();

        $progressUpdater = IdmlAssembler::getProgressUpdater();
        if ($progressUpdater)
        {
            $progressUpdater->incrementStep();
        }
    }

    public function visitPageEnd(IdmlPage $element, $depth = 0)
    {
        $this->closeFinalElements($depth);

        $pageFilename = $this->savePage();

        $this->pageNameXref[$element->UID] = $pageFilename . '.xhtml';

        $this->clearPage();
    }

    /**
     * Returns the page UID; in fixed layout, the pages are named using the page id, unlike in reflowable.
     * @param string $pageUID
     * @return string page UID
     */
    public function getDestinationPage($pageUID)
    {
        return $pageUID;
    }

    /**
     * Returns the ID used to generate the page name used in a hyperlink's href attribute.
     * The element used differs between fixed and reflowable, so this method is coded in both producers.
     * @param array $uidArray - Array containing both page and text frame UIDs for the hyperlink.
     * @return string page UID
     */
    public function getHyperlinkPage($uidArray)
    {
        return $uidArray['pageUID'];
    }

    /**
     * This method overrides the parent method, which saves a page at the end of the package element.
     * We save/clear pages at the end of the page element in fixed layout, so saving the page here creates empty pages.
     * @param IdmlPackage $item
     * @param int $depth
     */
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
    }

    /**
     * Process a tab character. This may require either an ampersand character (&#09;) or a complex web of span tags
     * @param IdmlTab $element
     * @param int $depth
     */
    public function visitTab(IdmlTab $element, $depth = 0)
    {
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');

        if (is_a($paragraph, 'IdmlParagraphRange') && $paragraph->hasNestedStyle)
        {
            $currNestedStyle = $this->getCurrentNestedStyles($paragraph->nestedStyleHelpers);

            if (!is_null($currNestedStyle))
            {
                $currNestedStyle->processContent(null, $element, $this, $depth);
                return;
            }
        }

        // No nested styles to process; render without additional character style
        $this->processTab($element, $depth);
    }

    /**
     * Process tab
     * @param IdmlTab $element
     * @param int $depth
     */
    public function processTab(IdmlTab $element, $depth = 0)
    {
        // If there's no tab info in memory, default to using the ampersand character for the tab
        if (is_null($element->position))
        {
            $this->addPageElement('<pre style="display:inline">&#09;</pre>', $depth);
            return;
        }

        // If the tab is in a line-indented paragraph, which don't render span tabs well, use the ampersand character
        if (!$element->usesSpans)
        {
            $this->addPageElement('<pre style="display:inline">&#09;</pre>', $depth);
            return;
        }

        // Create the tab span structure, dependent on the tab's alignment.
        if (strpos($element->alignment, 'Right') !== false)
        {
            $this->processRightAlignedTab($element, $depth);
        }
        else
        {
            $this->processLeftAlignedTab($element, $depth);
        }
    }

    /**
     * If the paragraph's first tab hasn't been processed, insert the opening width span for the first tab.
     * @param IdmlParagraphRange $paragraph
     */
    protected function processFirstTab(IdmlParagraphRange $paragraph, $depth=0)
    {
        if ($paragraph->tabIndex == 0 && !is_null($paragraph->firstTabPosition))
        {
            $htmlTag = '<span style="width:' . $paragraph->firstTabPosition . 'px;display:inline-block;">';
            $this->addPageElement($htmlTag, $depth);
            $paragraph->tabIndex++;
            $paragraph->tabSpansToClose++;
        }
    }

    /**
     * For left-aligned tabs, the contained content has been written to the page.
     * Close the character style span and any tab spans (width and alignment) that have been opened.
     * Then open the required spans for the next tab (or the remainder of the line): width (if there's another tab) and character style.
     * @param IdmlTab $element
     * @param int $depth
     */
    protected function processLeftAlignedTab(IdmlTab $element, $depth)
    {
        // Start by getting info about ancestor elements.
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');
        $charRange = $this->getAncestor($element, 'IdmlCharacterRange');

        /*** Close all the open spans (width, alignment, and character style).
             If the previous tab was right-aligned, add an empty width span to position the next tab. */

        // Close the character style span
        $this->addPageElement('</span>', $depth);

        // Close the width span
        $this->addPageElement('</span>', $depth);

        // If the previous tab was right-aligned, close the text alignment span,
        // then add an empty span to represent the tab's spacing
        if (strpos($element->lastAlignment, 'Right') !== false)
        {
            $this->addPageElement('</span>', $depth);
            $paragraph->tabSpansToClose--;

            $width = $element->position - $element->lastPosition;

            $this->addPageElement('<span style="width:' . $width .  'px;display:inline-block;">',$depth);
            $this->addPageElement('</span>',$depth);
        }

        /***  Open the required spans: the width span if there's a next tab, and always a character style tab. */

        // Start a new width span if there's another tab.
        if ($paragraph->tabIndex < $paragraph->tabCount)
        {
            $width = $element->nextPosition - $element->position;

            $this->addPageElement('<span style="width:' . $width . 'px;display:inline-block">', $depth);
        }
        else
        {
            $paragraph->tabSpansToClose--;
        }

        // Always add the character range styling span tag.
        $this->addPageElement('<span ' . $this->convertAttribsToString($charRange->attribs) . '>', $depth);

        // Always increment tabIndex, since a new tab is added.
        $paragraph->tabIndex++;
    }

    /**
     * For right-aligned tabs, the content follows the tab.
     * Close all open spans (character style, alignment, and width).
     * Then open new spans for the right-aligned tab stop: width, alignment, and character style.
     * If this is the first tab on the line, the width span was already open, so don't open it here.
     * @param IdmlTab $element
     * @param $depth
     */
    protected function processRightAlignedTab(IdmlTab $element, $depth)
    {
        // Start by getting info about ancestor elements.
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');
        $charRange = $this->getAncestor($element, 'IdmlCharacterRange');

        /***  Close all the open tab spans, based on the previous tab
              If there is no previous tab, close only the character style span (use the existing width span)
              If the previous tab is left-aligned, close the character style span and the width span: no align span was opened.
              If the previous tab is right-aligned, close the character style span, the alignment span, and the width span. */

        // Always close the character range formatting span.
        $this->addPageElement('</span>', $depth);

        // Close the alignment span if the previous tab was right-aligned (otherwise no alignment span was opened)
        if (strpos($element->lastAlignment, 'Right') !== false)
        {
            $this->addPageElement('</span>', $depth);
        }

        // Close the width span if there was a tab before this one.
        if (!is_null($element->lastAlignment))
        {
            $this->addPageElement('</span>', $depth);
        }

        /*** Open the width, alignment, and character style spans.
             On the first tab, use the existing width span. */

        $width = $element->position - $element->lastPosition;

        // If this is the first tab, the span for width is already in place.
        if (!is_null($element->lastAlignment))
        {
            $this->addPageElement('<span style="width:' . $width . 'px;display:inline-block;">', $depth);
        }

        // Now open the new tab's alignment span.  It needs to indicate the width to properly render.
        $this->addPageElement('<span style="display:inline-block;text-align:right;width:' . $width . 'px;">', $depth);

        // Finally, reopen the styling span for the character range.
        $this->addPageElement('<span ' . $this->convertAttribsToString($charRange->attribs) . '>', $depth);

        /*** Manage paragraph properties. */

        // Always increment tabIndex, since a new tab is added.
        $paragraph->tabIndex++;

        // If the last tab was not right-aligned, increment tabSpansToClose
        if (strpos($element->lastAlignment, 'Right') === false)
        {
            $paragraph->tabSpansToClose++;
        }
    }
}
?>
