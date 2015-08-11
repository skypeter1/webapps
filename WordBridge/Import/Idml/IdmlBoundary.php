<?php

/**
 * @package /app/Import/Idml/IdmlBoundary.php
 * 
 * @class   IdmlBoundary
 * 
 * @description A boundary is essentially a rectangle, conceptually drawn around the outer bounds of an element on the page.
 *              This can be a text frame, a rectangle, a group, a polygon, an oval, or a graphic line.s
 *              For a right-angled rectangle, this class perfectly matches the top, left, right, bottom of the original.
 *              For a rotated rectangle or any other element the top, left, right, bottom are the "rubberband" that
 *              describes the smallest bounds around the element.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlBoundary
{
    public $left;
    public $top;
    public $right;
    public $bottom;

    /**
     * The constructor
     *
     * @param float $top
     * @param float $left
     * @param float $bottom
     * @param float $right
     */
    public function __construct($top, $left, $bottom, $right)
    {
        $this->top    = (float)min($top, $bottom);
        $this->left   = (float)min($left, $right);
        $this->bottom = (float)max($bottom, $top);
        $this->right  = (float)max($right, $left);
    }

    /**
     * Get Width. This can be wrong if boundary is rotated.
     * @return int
     */
    public function getWidth()
    {
        return $this->right - $this->left;
    }

    /**
     * Get Height. This can be wrong if boundary is rotated.
     * @return int
     */
    public function getHeight()
    {
        return $this->bottom - $this->top;
    }

    /**
     * Get center X.
     * @return float
     */
    public function getCenterX()
    {
        return $this->left + ($this->getWidth() / 2);
    }

    /**
     * Get center Y.
     * @return float
     */
    public function getCenterY()
    {
        return $this->top + ($this->getHeight() / 2);
    }

    /**
     * Create default boundary
     * @return IdmlBoundary
     */
    public static function createDefault()
    {
        return new IdmlBoundary(0, 0, 0, 0);
    }

    /**
     * CreateFromCornerPoints is a convenience function for constructing an object from a set of corner points.
     *
     * @param array[array[x,y]] $points is a collection of [x,y] pairs that define the corners of a rectangle.
     * The algorithm will also work with four points that do not define a true rectilinear boundary, as well as with
     * polygons that have five or more points, (or even with triangles technically).  The smallest bounding rectangle
     * for the points is created.
     *
     *  @return IdmlBoundary new object
     */
    public static function createFromCornerPoints($points)
    {
//        assert(count($points) >= 3);

        $xmin = (float)$points[0]['x'];
        $ymin = (float)$points[0]['y'];
        $xmax = (float)$points[0]['x'];
        $ymax = (float)$points[0]['y'];

        for($i = 1; $i < count($points); $i++)
        {
            $x = (float)$points[$i]['x'];
            $y = (float)$points[$i]['y'];

            if ($x < $xmin) $xmin = $x;
            if ($y < $ymin) $ymin = $y;
            if ($x > $xmax) $xmax = $x;
            if ($y > $ymax) $ymax = $y;
        }

        return new IdmlBoundary($ymin, $xmin, $ymax, $xmax);
    }

    /**
     * CreateFromIDMLString is a convenience function for constructing an object from an IDML string.
     *
     * @param string $geometricBounds is a string containing four space separated values
     *  that correspond to top, left, bottom, right.
     *
     * @return IdmlBoundary new object
     */
    public static function createFromIDMLString($geometricBounds)
    {
        $parts = explode(' ', $geometricBounds);
        assert(count($parts) == 4);
        return new IdmlBoundary($parts[0], $parts[1], $parts[2], $parts[3]);
    }

    /**
     * The encompass function will merge the current boundary's outline with the other boundary's outline
     * to create a larger boundary that encompasses both.
     * 
     * @param IdmlBoundary $other
     */
    public function encompass(IdmlBoundary $other)
    {
        $this->top    = (float)min($this->top,    $other->top);
        $this->left   = (float)min($this->left,   $other->left);
        $this->bottom = (float)max($this->bottom, $other->bottom);
        $this->right  = (float)max($this->right,  $other->right);
    }

    /**
     * Returns the height of the offset attribute for a rotated element.
     * Using this height, we'll construct a div before and after the rotated element
     * @param IdmlElement $element
     * @return string
     */
    public static function getOffsetHeight(IdmlElement $element)
    {
        $containingBoundary = new IdmlBoundary($element->boundary->top,
                                               $element->boundary->left,
                                               $element->boundary->bottom,
                                               $element->boundary->right);

        $containingBoundary->rotate($element->transformation->getB());

        $elementHeight = $element->boundary->getHeight();
        $containerHeight = $containingBoundary->getHeight();

        $offsetHeight = ($containerHeight - $elementHeight) / 2;

        if ($offsetHeight < 0)
        {
            $offsetHeight = 0;
        }

        return $offsetHeight;
    }

    /**
     * The transform function applies the transformation matrix to this object and returns a new coordinates object.
     *
     * @param IdmlBoundary $boundary
     * @param IdmlTransformation $matrix
     * 
     * @return IdmlBoundary a new object
     */
    public static function transform(IdmlBoundary $boundary, IdmlTransformation $matrix)
    {
        $a = $matrix->getA();
        $b = $matrix->getB();
        $c = $matrix->getC();
        $d = $matrix->getD();
        $tx = $matrix->xTranslate();
        $ty = $matrix->yTranslate();

        $left   = $boundary->left;
        $top    = $boundary->top;
        $right  = $boundary->right;
        $bottom = $boundary->bottom;

        // Transform (x1,y1)
        $left2 = $left*$a + $top*$c + $tx;
        $top2  = $left*$b + $top*$d + $ty;

        // Transform (x2,y2)
        $right2  = $right*$a + $bottom*$c + $tx;
        $bottom2 = $right*$b + $bottom*$d + $ty;

        return new IdmlBoundary($top2, $left2, $bottom2, $right2);
    }

    /**
     * This function changes the boundary parameters when a text frame is rotated.
     * Its intended use is to properly set the boundary of a containing div to maintain spacing on the page
     *   when an inline text frame is transformed
     * @param $radians
     */
    public function rotate($radians)
    {
        $cos = cos($radians);
        $sin = sin($radians);

        $this->left   =  ($this->left * $cos)  + ($this->top * $sin);
        $this->top    = -($this->left * $sin)  + ($this->top * $cos);
        $this->right  =  ($this->right * $cos) + ($this->bottom * $sin);
        $this->bottom = -($this->right * $sin) + ($this->bottom * $cos);
    }

    /**
     * Apply an offset to a boundary's four points
     * @param $xOffset
     * @param $yOffset
     *
     * @return IdmlBoundary a new object
     */
    public function applyOffset($xOffset, $yOffset)
    {
        $top2    = $this->top    - $yOffset;
        $left2   = $this->left   - $xOffset;
        $bottom2 = $this->bottom - $yOffset;
        $right2  = $this->right  - $xOffset;

        return new IdmlBoundary($top2, $left2, $bottom2, $right2);
    }


    /**
     * This function can be called just before using the object's values in a CSS declaration
     */
    public function roundToIntegers()
    {
        $this->top    = round($this->top, 0);
        $this->left   = round($this->left, 0);
        $this->bottom = round($this->bottom, 0);
        $this->right  = round($this->right, 0);
    }


    /**
     * isPointInside determines if the given point is within the bounds of the rectangle defined by this object.
     *
     * @param float $xpoint is the x value of the point to test
     * @param float $ypoint is the y value of the point to test
     *
     * @return true if the point is within the rectangle's coordinates
     */
    public function isPointInside($xpoint, $ypoint)
    {
        $xpoint = (float)$xpoint;
        $ypoint = (float)$ypoint;

        if ( $xpoint > $this->right ||
             $xpoint < $this->left ||
             $ypoint > $this->bottom ||
             $ypoint < $this->top )
        {
            return false;
        }
        else
        {
            return true;
        }
    }

   /**
     * Dump the coordinates in a human readable format
     * 
     * @return string 
     */
    public function diagnostic()
    {
        $s = array();
        $s[] = "[left]=" . $this->left;
        $s[] = "[xcenter]=" . $this->getCenterX();
        $s[] = "[right]=" . $this->right;
        $s[] = "[width]=" . $this->getWidth();
        $s[] = "[top]=" . $this->top;
        $s[] = "[ycenter]=" . $this->getCenterY();
        $s[] = "[bottom]=" . $this->bottom;
        $s[] = "[height]=" . $this->getHeight();
        return implode('  ', $s);
    }
}
