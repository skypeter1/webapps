<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlRectangle.php
 *
 * @class   IdmlRectangle
 *
 * @description Parser for InDesign <Rectangle>, which can be both inside of spread or inside of story as an inline element.
 *              Rectangles are also containers for Images.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',        'Import/Idml/PageElements');
App::uses('IdmlFrameFitting',   'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');


class IdmlRectangle extends IdmlElement
{
    /**
     * InDesign unique ID of the rectangle.
     * @var string
     */
    public $UID;

    /**
     * Page where is the rectangle.
     * @var IdmlPage
     */
    public $page;

    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /** @var IdmlTransformation $transformation */
    public $transformation;

    /**
     * Boundary of the rectangle.
     * @var IdmlBoundary
     */
    public $boundary;
    
    /**
     * Is this object visible or has the InDesign user specifically hidden it
     * @var boolean 
     */
    public $visible;

    /** The InDesign cropping instructions for an enclosed image are contained in the <FrameFittingOption>
     * @var IdmlFrameFitting $frameFittingOption 
     */
    public $frameFittingOption;
            
    /**
     * Constructor.
     * @param IdmlPage $page Could be null
     * @param IdmlStory $story Could be null if rectangle is not part of story.
     */
    public function __construct(IdmlPage $page = null, IdmlStory $story = null)
    {
        parent::__construct();
        $this->UID = '';
        $this->page = $page;
        $this->story = $story;
        $this->transformation = new IdmlTransformation();
        $this->boundary = IdmlBoundary::createDefault();
        $this->visible = true;
        $this->frameFittingOption = new IdmlFrameFitting(0,0,0,0);
    }

    /**
     * Accept visitor. This element is not visited.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        // If item is visible then accept.
        if ($this->visible)
        {
            $visitor->visitRectangle($this, $depth);
            foreach($this->childrenElements as $child)
            {
                $child->accept($visitor, $depth+1);
            }
            $visitor->visitRectangleEnd($this, $depth);
        }
    }

    /**
     * Parse from DOM node.
      *
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->UID = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;

        $this->setTransform($node);

        $visible = $node->hasAttribute(IdmlAttributes::Visible) ? $node->getAttribute(IdmlAttributes::Visible) : 'true';
        $this->visible = (strtolower($visible) == 'true') ? true : false;

        $this->boundary = IdmlParserHelper::parseBoundary($node);
        
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

}
