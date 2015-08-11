<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlGraphicLine.php
 *
 * @class   IdmlGraphicLine
 *
 * @description Parser for InDesign GraphicLine.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlSvgShape',       'Import/Idml/PageElements');

class IdmlGraphicLine extends IdmlSvgShape
{
    protected $anchorPoints = array();

    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $pathPoints = $node->getElementsByTagName('PathPointType');
        foreach ($pathPoints as $pathPoint)
        {
            $anchorAttrib = $pathPoint->getAttribute('Anchor');
            $this->anchorPoints[] = explode(' ', $anchorAttrib);
        }
    }

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitGraphicLine($this, $depth);
    }

    /**
     * Format anchor points into an array of coordinate tag attributes
     * The enclosing svg tag has already been absolutely positioned, so the coordinates have to be adjusted so they're relative to the svg element.
     * @return array $anchorPoints
     */
    public function getAnchorPoints()
    {
        $anchorPoints = array();

        $anchorPoints['x1'] = 0;
        $anchorPoints['y1'] = 0;
        $anchorPoints['x2'] = abs($this->anchorPoints[0][0] - $this->anchorPoints[1][0]);
        $anchorPoints['y2'] = abs($this->anchorPoints[0][1] - $this->anchorPoints[1][1]);

        return $anchorPoints;
    }
}
