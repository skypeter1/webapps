<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlPolygon.php
 *
 * @class   IdmlPolygon
 *
 * @description Parser for InDesign Polygon.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlSvgShape',       'Import/Idml/PageElements');
App::uses('IdmlFrameFitting',   'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');


class IdmlPolygon extends IdmlSvgShape
{
    /** Constructor and parse function are encoded in parent class IdmlSvgShape */

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitPolygon($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }
    }

    /**
     * Returns the value of the points attribute of a polygon, based on the polygon's stored vertices.
     * It will be space-separated pairs of numbers delimited by a comma, like this: "20,25 30,13 82,100 45,20"
     * @param string $strokeWidth - Value of the IDML StrokeWidth property, an integer string
     * @return string $pointStr - a string of coordinate values to used as the value of the points attribute of the polygon
     */
    public function getVertexPoints($strokeWidth)
    {
        $pointStr = '';

        foreach ($this->vertices as $vertex)
        {
            $xCoord = $vertex['coords']['x'] - $this->boundary->left + $strokeWidth;
            $yCoord = $vertex['coords']['y'] - $this->boundary->top + $strokeWidth;

            $pointStr .= $xCoord . ',' . $yCoord . ' ';
        }

        return $pointStr;
    }

    /**
     * Returns the contents of the 'd' attribute of the <path> element. This is called only if the polygon's path is open.
     * The 'd' attribute contains the data from which a connected collection of line segments and curves is constructed,
     *   and consists of a series of commands which are each followed by instructions
     * The 'M' (moveto) command is followed by the coordinates of first point, and is usually the first command in the value string.
     * The 'Q' (quadratic) command is followed by the coordinates of the Bezier control point, then the coordinates of the endpoint itself.
     * For more info visit http://www.w3.org/TR/SVG/paths.html
     * @return string $pathData
     */
    public function getPathPoints()
    {
        // Create a copy of the vertices array
        $vertices = $this->vertices;

        // Add the first point's data to the path data string
        $firstPointData = array_shift($vertices);
        $pathData = 'M' . ($firstPointData['coords']['x'] - $this->boundary->left) . ',' . ($firstPointData['coords']['y'] - $this->boundary->top);

        // Add the remaining points to the data string, including the Bezier curve control points
        // For straight lines the control points are identical to the anchor point.
        foreach ($vertices as $vertex)
        {
            $pathData .= ' Q';
            $pathData .= ($vertex['leftDir']['x'] - $this->boundary->left) . ',' . ($vertex['leftDir']['y'] - $this->boundary->top) . ' ';
            $pathData .= ($vertex['coords']['x'] - $this->boundary->left) . ',' . ($vertex['coords']['y'] - $this->boundary->top);
        }

        return $pathData;
    }
}
