<?php

/**
 * @package /app/Import/Idml/PageElements/Group.php
 * 
 * @class   IdmlGroup
 * 
 * @description Parser for <Group> elements which contain other elements.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlPage',           'Import/Idml');
App::uses('IdmlStory',          'Import/Idml');
App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');
App::uses('IdmlElement',        'Import/Idml/PageElements');


class IdmlGroup extends IdmlElement
{
    /**
     * InDesign unique ID of the group.
     * @var string
     */
    public $UID;

    /**
     * HTML tag name can be 'g' (inside svg element), 'span' (inside paragraph) or 'div' (default)
     */
    public $tagName = 'div';

    /**
     * Page or null.
     * @var IdmlPage
     */
    public $page;

    /**
     * Story.
     * @var IdmlStory
     */
    public $story;

    /**
     * Is this object visible or has the InDesign user specifically hidden it
     * @var boolean 
     */
    public $visible;

    /** @var IdmlTransformation $transformation */
    public $transformation;
    
    /**
     * The bounding rectangle of the text frame.
     * @var IdmlBoundary
     */
    public $boundary;
    

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
        $this->visible = true;
        $this->transformation = new IdmlTransformation();
        $this->boundary = IdmlBoundary::createDefault();
}

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        // Group is also visitable just in case it can have a style.
        $visitor->visitGroup($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitGroupEnd($this, $depth);
    }

    /**
     * Parse function.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->UID = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;

        $this->setTransform($node);

        $visible = $node->hasAttribute(IdmlAttributes::Visible) ? $node->getAttribute(IdmlAttributes::Visible) : 'true';
        $this->visible = (strtolower($visible) == 'true') ? true : false;

        $this->parseChildren($node);
        
        // Groups can be nested within groups, so this needs to be determined now, after any inner groups have
        // had _their_ bounds determined.
        $this->determineBounds();
    }

    /**
     * IdmlGroups do not have <PathGeometry> or <GeometricBounds> so their overall bounds must be determined
     * after reading all children by getting the outer bounds of all encompassed children.
     * The true bounds of the children must take into account their transformation: their true boundaries are
     * determined by call IdmlBoundary::transform.
     * Once the group's absolute boundary is computed from the children's boundaries, the group's own transformation
     * must be accounted for by subtracting the tx and ty from the boundary points.
     */
    public function determineBounds()
    {
        $boundary = null;

        foreach ($this->childrenElements as $child)
        {
            // If the child's boundary is not set, go to the next child.
            if (!isset($child->boundary)) continue;

            $childBoundary = IdmlBoundary::transform($child->boundary, $child->transformation);

            // For the first child's boundary, duplicate it
            if (is_null($boundary))
            {
                $boundary = new IdmlBoundary($childBoundary->top, $childBoundary->left, $childBoundary->bottom, $childBoundary->right);
                continue;
            }

            // Add the child's dimensions to the group's boundary.
            if (get_class($child) == 'IdmlTextFrame')
            {
                // Add the height of the text frame, which stacks vertically.
                $boundary->bottom += $childBoundary->getHeight();
            }
            else
            {
                // Use that new boundary to expand the bounds of the group.
                $boundary->encompass($childBoundary);
            }
        }

        // Now subtract the group's tx and ty from the boundary points to determine its actual position.
        $top = $boundary->top - $this->transformation->yTranslate();
        $left = $boundary->left - $this->transformation->xTranslate();
        $bottom = $boundary->bottom - $this->transformation->yTranslate();
        $right = $boundary->right - $this->transformation->xTranslate();

        $this->boundary = new IdmlBoundary($top, $left, $bottom, $right);
    }
}