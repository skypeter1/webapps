<?php


/**
 *
 * @package /app/Import/Idml/IdmlPositions.php
 * @class   IdmlPositions
 * 
 * @description A class for determining element positioning in fixed layout.
 *          This class is used by the IdmlFixedLayout class.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlPositions
{

    protected $left;
    protected $top;
    protected $element;

    /**
     * The getPositionCSS function should return a CSS declaration for the element's top and left.
     */
    public function getPositionCSS(IdmlElement $element)
    {
        $elementClass = get_class($element);

        switch( $elementClass )
        {
            case 'IdmlTextFrame':
            case 'IdmlRectangle':
            case 'IdmlGroup':
            case 'IdmlPolygon':
            case 'IdmlOval':

                if (!isset($element->boundary))
                {
                    CakeLog::debug("[IdmlProduceFixedLayout::getDimensionCSS] Expecting boundary to provide position for " . get_class($element));
                    return '';
                }

                if ($element->isEmbedded())
                {
                    // Get the positioning parameters, either from the inline styles or the applied style.
                    // For now, store them in an array for easier passing to called methods.
                    $positionParams['xOffset'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorXoffset', null);
                    $positionParams['yOffset'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorYoffset', null);
                    $positionParams['anchorPosition'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchoredPosition', null);
                    $positionParams['spineRelative'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->SpineRelative', null);
                    $positionParams['anchorPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->AnchorPoint', null);
                    $positionParams['horizAlignment'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->HorizontalAlignment', null);
                    $positionParams['horizRefPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->HorizontalReferencePoint', null);
                    $positionParams['vertAlignment'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->VerticalAlignment', null);
                    $positionParams['vertRefPoint'] = $this->getStyleByKey($element, 'AnchoredObjectSetting->VerticalReferencePoint', null);

                    // Only cases where 'AnchoredPosition' is 'Anchored' are offset
                    if ($positionParams['anchorPosition'] != 'Anchored')
                    {
                        return 'position:relative;';
                    }

                    // If no anchor offset is set, set position to relative and exit
                    if (is_null($positionParams['xOffset']) && is_null($positionParams['yOffset']))
                    {
                        return 'position:relative;';
                    }

                    // At least one anchor offset is set, so set absolute positioning before determining final offsets
                    $styles['position'] = 'absolute';

                    // Adjust offsets based on reference point
                    list($xOffset, $yOffset) = $this->adjustOffsetsToRefPoint($positionParams, $element);

                    if (!is_null($xOffset))
                    {
                        $styles['left'] = $xOffset . 'px';
                    }

                    // For now, we aren't managing 'top'. All test cases given use Baseline orientation, which is problematic in translation.
//                    if (!is_null($yOffset))
//                    {
//                        $styles['top'] = $yOffset . 'px';
//                    }

                    $styleAttrib = $this->getStyleString($styles);
                    return $styleAttrib;

                }

                else  // !$element->isEmbedded()
                {
                    $position =  $this->getElementPosition($element);

                    // Set element's position, to be used to relatively position embedded elements
                    $element->setPosition($position);

                    return sprintf("position:absolute; top:%spx; left:%spx;",
                        $position['top'], $position['left']);
                }

            default:
                return '';
        }
    }

    protected function getElementPosition($element)
    {
        // apply the transformation to the boundary to arrive at coordinates in InDesign Spread Space (idss)
        $idssCoordinates = IdmlBoundary::transform($element->boundary, $element->transformation);

        $page = $this->getPage($element);

        $pageIDSS = $page->idssCoordinates;
        $pageAdjustedBoundary = $idssCoordinates->applyOffset($pageIDSS->left, $pageIDSS->top);
        $pageAdjustedBoundary->roundToIntegers();

        $weight = $element->getComputedBorders();

        $position = array();
        $position['top'] = $pageAdjustedBoundary->top - $weight;
        $position['left'] = $pageAdjustedBoundary->left - $weight;

        return $position;
    }

    /**
     * Adjusts x and y offsets based on the AnchorPoint indicated in IDML
     * @param array $positionParams - positioning data from IDML
     * @param IdmlElement $element
     * @return array - x and y offsets
     */
    protected function adjustOffsetsToRefPoint($positionParams, IdmlElement $element)
    {
        $page = $this->getCurrentPage($element);

        switch($positionParams['horizRefPoint'])
        {
            case 'TextFrame':

                list($xOffset, $yOffset) = $this->getTextFrameOffsets($element, $positionParams, $page);
                break;

            case 'PageEdge':
                list($xOffset, $yOffset) = $this->getPageOffsets($element, $positionParams, $page);
                break;

            case null:
            default:
                $xOffset = $yOffset = 0;
                break;

        }

        if ($positionParams['spineRelative'] == 'true' && $page->pagePosition != 'right')
        {
            $xOffset *= -1;
            $yOffset *= -1;
        }

        return array($xOffset, $yOffset);
    }

    /**
     * @param IdmlElement $element
     * @param array $positionParams - An array of parameters for positioning from IDML
     * @param IdmlPage $page
     * @return array x and y offsets
     */
    protected function getTextFrameOffsets(IdmlElement $element, $positionParams, IdmlPage $page)
    {
        $anchorPoint = $positionParams['anchorPoint'];
        $story = $this->getAncestor($element, 'IdmlStory');

        if ($page->pagePosition == 'right')
        {
            // get enclosing text frame width
            $boundary = $story->idmlTextFrame->boundary;
        }
        else
        {
            // use width of current element
            $boundary = $element->boundary;
        }

        $xAdjustment = $boundary->getWidth();
        $yAdjustment = $boundary->getHeight();

        if (strpos($anchorPoint, 'Right') !== false)
        {
            $xOffset = $positionParams['xOffset'] + $xAdjustment;
        }
        elseif (strpos($anchorPoint, 'Left') !== false)
        {
            $xOffset =  $positionParams['xOffset'];
        }
        else  // if not right or left, it must be centered
        {
            $xOffset =  $positionParams['xOffset'] + ($xAdjustment / 2);
        }

        if (strpos($anchorPoint, 'Bottom') !== false)
        {
            $yOffset =  $positionParams['yOffset'] + $yAdjustment;
        }
        elseif (strpos($anchorPoint, 'Top') !== false)
        {
            $yOffset =  $positionParams['yOffset'];
        }
        else  // if not top or bottom, it must be centered
        {
            $yOffset =  $positionParams['yOffset'] + ($yAdjustment / 2);
        }

        return array($xOffset, $yOffset);
    }

    protected function getPageOffsets(IdmlElement $element, $positionParams, IdmlPage $page)
    {
        $anchorPoint = $positionParams['anchorPoint'];
        $story = $this->getAncestor($element, 'IdmlStory');
        $enclosureXoffset = $story->idmlTextFrame->getPosition('left');
        $enclosureYoffset = $story->idmlTextFrame->getPosition('top');
        $width = $element->boundary->getWidth();
        $height = $element->boundary->getHeight();

        if (strpos($anchorPoint, 'Right') !== false)
        {
            $xOffset = $width - $positionParams['xOffset'] + $enclosureXoffset;
        }
        elseif (strpos($anchorPoint, 'Left') !== false)
        {
            if ($positionParams['spineRelative'] = 'true' && $page->pagePosition == 'right')
            {
                $xOffset = $page->boundary->getWidth() - $enclosureXoffset - $width + $positionParams['xOffset'];
            }
            else
            {
                $xOffset =  $positionParams['xOffset'] + $enclosureXoffset;
            }
        }
        else  // if not right or left, it must be centered
        {
            $xOffset =  ($width / 2) - $positionParams['xOffset'] + $enclosureXoffset;
        }

        if (strpos($anchorPoint, 'Bottom') !== false)
        {
            $yOffset =  $height - $positionParams['yOffset'] + $enclosureYoffset;
        }
        elseif (strpos($anchorPoint, 'Top') !== false)
        {
            $yOffset =  $positionParams['yOffset'] + $enclosureYoffset;
        }
        else  // if not top or bottom, it must be centered
        {
            $yOffset =  ($height / 2) - $positionParams['yOffset'] + $enclosureXoffset;
        }

        return array($xOffset, $yOffset);
    }
}
