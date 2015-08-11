<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlSvgShape.php
 *
 * @class   IdmlSvgShape
 *
 * @description Parser for InDesign Oval.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlElement',        'Import/Idml/PageElements');
App::uses('IdmlFrameFitting',   'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');


class IdmlSvgShape extends IdmlElement
{
    protected $inline;

    /**
     * @var array $vertices - array of vertex points for the shape
     * Each vertex is an array of three x,y coordinate arrays, indexed as 'coords', 'leftDir', and 'rightDir'.
     * 'coords' is the point position, and 'leftDir' and 'rightDir' are the positions of the Bezier control points.
     */
    public $vertices;

    /**
     * InDesign unique ID of the shape's element.
     * @var string
     */
    public $UID;

    /** @var IdmlBoundary $boundary */
    public $boundary;

    /** The InDesign cropping instructions for an enclosed image are contained in the <FrameFittingOption>
     * @var IdmlFrameFitting $frameFittingOption
     */
    public $frameFittingOption;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->vertices = array();
        $this->frameFittingOption = new IdmlFrameFitting(0,0,0,0);
    }

    /**
     * Programming note: some properties set in parsing may not necessarily be of use at this time
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->UID = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;

        $this->setTransform($node);

        $this->vertices = $this->parseVertices($node);
        if (count($this->vertices) < 2)   // Any svg elements with < 2 vertices will not appear on the page: make it invisible and exit
        {
            $this->visible = false;
            $this->boundary = new IdmlBoundary(0,0,0,0);
            return;
        }

        $this->boundary = $this->setBoundary($this->vertices);

        $this->inline = (get_class($this->parentElement) == 'IdmlCharacterRange') ? true : false;

        $visible = $node->hasAttribute(IdmlAttributes::Visible) ? $node->getAttribute(IdmlAttributes::Visible) : 'true';
        $this->visible = (strtolower($visible) == 'true') ? true : false;

        $ffiList = $node->getElementsByTagName('FrameFittingOption');
        if( $ffiList->length > 0 )
        {
            $ffiNode = $ffiList->item(0);
            $topCrop    = $ffiNode->hasAttribute(IdmlAttributes::TopCrop)    ? $ffiNode->getAttribute(IdmlAttributes::TopCrop)    : 0;
            $leftCrop   = $ffiNode->hasAttribute(IdmlAttributes::LeftCrop)   ? $ffiNode->getAttribute(IdmlAttributes::LeftCrop)   : 0;
            $bottomCrop = $ffiNode->hasAttribute(IdmlAttributes::BottomCrop) ? $ffiNode->getAttribute(IdmlAttributes::BottomCrop) : 0;
            $rightCrop  = $ffiNode->hasAttribute(IdmlAttributes::RightCrop)  ? $ffiNode->getAttribute(IdmlAttributes::RightCrop)  : 0;
            $this->frameFittingOption = new IdmlFrameFitting($topCrop, $leftCrop, $bottomCrop, $rightCrop);
        }

        $this->parseChildren($node);
    }

    /**
     * The IdmlBoundary is determined by finding the lowest and highest values of all x- and y-coordinates
     * @param array $vertices - The vertex points of the svg element
     * @return IdmlBoundary $boundary
     */
    protected function setBoundary($vertices)
    {
        $left = $right = $vertices[0]['coords']['x'];
        $top = $bottom = $vertices[0]['coords']['y'];

        foreach($vertices as $vertex)
        {
            $left = min($left, $vertex['coords']['x']);
            $right = max($right, $vertex['coords']['x']);
            $top = max($top, $vertex['coords']['y']);
            $bottom = min($bottom, $vertex['coords']['y']);
        }

        return new IdmlBoundary($top, $left, $bottom, $right);
    }

    /**
     * Parse the shape object's vertices.
     * @param DOMNode $node
     * @return array - An array of three (x,y) coordinate arrays
     */
    protected function parseVertices(DOMNode $node)
    {
        $vertices = array();
        $xpath = new DOMXPath($node->ownerDocument);

        // Get all the <PathPointType> tags which define the objects corner
        $points = $xpath->query('Properties/PathGeometry/GeometryPathType/PathPointArray//PathPointType', $node);
        foreach($points as $point)
        {
            $vertex = array();
            $attributes = $point->attributes;

            $vertex['coords'] = self::setVertex($attributes->getNamedItem('Anchor')->value);
            $vertex['leftDir'] = self::setVertex($attributes->getNamedItem('LeftDirection')->value);
            $vertex['rightDir'] = self::setVertex($attributes->getNamedItem('RightDirection')->value);

            $vertices[] = $vertex;
        }

        return $vertices;
    }

    protected function setVertex($rawCoords)
    {
        $pointParts = explode(' ', $rawCoords);
        $vertex = array('x' => (float)$pointParts[0], 'y' => (float)$pointParts[1]);

        return $vertex;
    }
}
