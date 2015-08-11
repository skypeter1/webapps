<?php
/**
 * @package /app/Import/Idml/IdmlPage.php
 * 
 * @class   IdmlPage
 * 
 * @description This class defines a page's dimensions and holds a collection of block-level and inline elements.
 *          This class holds information; it is not a parser or a producer.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlBoundary',              'Import/Idml');


class IdmlPage
{
    /** @var idmlSpread $idmlSpread is the parent of this page */
    public $spread;
    
    /** @var string $pageUID is the unique ID assigned to this page by InDesign. */
    public $UID;

    /** @var  string $appliedMasterUID is the UID of this page's master page. It is "n" for any page that does not use a master. */
    public $appliedMasterUID;

    /** @var  string $inDesignPageName is a page number for ordinary pages, or a page identifier for masterspread pages */
    public $inDesignPageName;
    
     /** @var IdmlTransformation $transformation is the itemTransform matrix for this page. */
    public $transformation;

    /** @var IdmlTransformation $masterPageTransform is the transformation matrix for this page's master spread. */
    public $masterPageTransform;

    /** @var IdmlBoundary raw coordinates of the page, read directly from the IDML file */
    public $boundary;

    /** @var IdmlBoundary idssCoordinates InDesign Spread Space Coordinates, which is the raw shape in "Spread Space"
     * after applying the transformation matrix */
    public $idssCoordinates;

    /**
     * For books with <FacingPages> this is 'left' if this page is left of the binding
     * and 'right' if this page is to the right of the binding. For <FacingPages> false, then this is always 'right'.
     * This is needed to handle paragraph justification towards the spine and away from the spine.
     * Note that pages on MasterSpreads do not have a bindingLocation,
     * so these master pages are given a $pagePosition of 'master'.
     * @var string
     */
    public $pagePosition;

    /**
     * An indexed array of IdmlElements that contain stories that appear on this page.
     * Their order is signficant for reflowable ePubs. Their position information is important for fixed layout books.
     * @var array containing IdmlRectangle, IdmlTextFrame, and IdmlGroup objects
     */
    public $childrenElements;
    
    /**
     * Page width
     * @var int
     */
    public $width;

    /**
     * Page Height
     * @var int
     */
    public $height;

    /**
     * @var array[UID] $masterSpreadOverrides is a list of UIDs (of TextFrames, Rectangles, Groups) that are on the
     * master page and should be ignored because they've been copied from the master to the actual page using
     * the "Override all master page items" InDesign option.
     */
    public $masterSpreadOverrides;

    /**
     * The constructor
     * @param IdmlSpread $idmlSpread Can be null.
     */
    public function __construct($idmlSpread = null)
    {
        $this->spread = $idmlSpread;
        $this->UID = '';
        $this->appliedMasterUID = 'n';
        $this->inDesignPageName = '';
        $this->transformation = null;
        $this->masterPageTransform = null;
        $this->boundary = IdmlBoundary::createDefault();
        $this->idssCoordinates = IdmlBoundary::createDefault();
        $this->pagePosition = '';
        $this->childrenElements = array();
        $this->masterSpreadOverrides = array();
        $this->properties = array();
    }

    /**
     * Return parent Idml Object.
     * @return IdmlSpread
     */
    public function parentIdmlObject()
    {
        return $this->spread;
    }

    /**
     * Parse the portion of a spread.xml file that contains a <Page>
     * 
     * @param DOMNode $domNode is a single <Page> node within the document
     */
    public function parse($domNode)
    {
        $attributes = $domNode->attributes;
        $this->UID = $attributes->getNamedItem('Self')->value;                                  // like 'ud3'
        $this->appliedMasterUID = $attributes->getNamedItem('AppliedMaster')->value;            // like 'ud3' or 'n'
        $this->inDesignPageName = $attributes->getNamedItem('Name')->value;                     // ordinary pages are numbers, master spreads are letters
        $itemTransform = $attributes->getNamedItem('ItemTransform')->value;                     // like "1 0 0 1 0 -396"
        $masterPageTransform = $attributes->getNamedItem('MasterPageTransform')->value;         // like "1 0 0 1 0 0"
        $geometricBounds = $attributes->getNamedItem('GeometricBounds')->value;                 // like "0 0 792 612"
        $overrideList = $attributes->getNamedItem('OverrideList')->value;                       // like "ufb u174"

        $this->transformation = new IdmlTransformation($itemTransform);
        $this->masterPageTransform = new IdmlTransformation($masterPageTransform);
        $this->boundary = IdmlBoundary::createFromIDMLString($geometricBounds);
        $this->idssCoordinates = IdmlBoundary::transform($this->boundary, $this->transformation);
        if ($overrideList <> '')
            $this->masterSpreadOverrides = explode(' ', $overrideList);

        $pageX = $this->transformation->xTranslate();
        if ($pageX < 0)
            $this->pagePosition = 'left';
        else
            $this->pagePosition = 'right';

        // Increment progress step.
        $p = IdmlAssembler::getProgressUpdater();
        if ($p)
            $p->incrementStep();
    }

    /*
     * @return IdmlPage a pointer to the master page for this page, or null if there is no master page
     */
    public function getMasterPage()
    {
        $masterSpread = IdmlAssembler::getInstance()->getCurrentPackage()->getMasterSpread($this->appliedMasterUID);
        if ($masterSpread == null)
            return null;

        if ($this->pagePosition == 'left')
            return $masterSpread->getLeftPage();

        else if ($this->pagePosition == 'right')
            return $masterSpread->getRightPage();

        else
            return null;
    }


    /**
     * Add a text frame to this page.
     * 
     * @param IdmlElement $idmlElement is a child element of this IDML text frame
     */
    public function addChildElement(IdmlElement $idmlElement)
    {
        $this->childrenElements[] = $idmlElement;
    }

    /**
     * Return true in case page has only text frames and all were linked ones. Only valid for reflowable books.
     * 
     * @return boolean
     */
    private function hasOnlyEmptyTextFrames()
    {
        if (IdmlAssembler::getInstance()->isFixedLayout())
        {
            return false;
        }

        $onlyTextFrames = true;
        $noneHasStory = true;
        foreach($this->childrenElements as $childElement)
        {
            if (!($childElement instanceof IdmlTextFrame))
            {
                $onlyTextFrames = false;
            }
            else 
            {
                if ($childElement->hasStory())
                {
                    $noneHasStory = false;
                }
            }
        }

        if ($onlyTextFrames && $noneHasStory)
        {
            return true;
        }

        return false;
    }
  
    /**
     * This accept function is called by the parent IdmlSpread.
     * 
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth)
    {
        // Skip the page in case it does not have any children elements.
        if (IdmlAssembler::getInstance()->isReflowable())
        {
            if (count($this->childrenElements) == 0 || $this->hasOnlyEmptyTextFrames())
                return;
        }

        $visitor->visitPage($this, $depth);

        // First walk the elements of the associated master spread.
        if (IdmlAssembler::getInstance()->isFixedLayout())
        {
            $this->visitMasterPageItems($visitor, $this->inDesignPageName, $depth+1);
        }

        // Then walk the elements of this page itself
        foreach ($this->childrenElements as $childElement)
        {
            $childElement->accept($visitor, $depth+1);
        }

        $visitor->visitPageEnd($this, $depth);
    }

    /**
     * Each page may have a master page, and master pages themselves may be dependent upon other master pages
     *
     * @param IdmlVisitor $visitor
     * @param string $pageNumber - originally provided by the 'actual' page and passed through all recursion
     * @param int $depth is how deep in the traversal we are
     */
    public function visitMasterPageItems(IdmlVisitor $visitor, $pageNumber, $depth)
    {
        $this->inDesignPageName = $pageNumber;

        $masterPage = $this->getMasterPage();

        if ($masterPage !== null)
        {
            // recurse, because master pages may be based on other master pages
            $masterPage->visitMasterPageItems($visitor, $pageNumber, $depth+1);

            // walk this master page's elements
            foreach ($masterPage->childrenElements as $childElement)
            {
                if(!$this->isInOverrideList($childElement))
                {
                    $childElement->accept($visitor, $depth+1);
                }
            }
        }
    }

    /**
     * @param IdmlElement $element is the target to examine
     * @return bool
     */
    public function isInOverrideList($element)
    {
        // only IdmlTextFrame, IdmlGroup, and IdmlRectangle have UID
        if (!isset($element->UID))
            return false;
        else
            return in_array($element->UID, $this->masterSpreadOverrides);
    }
}

?>
