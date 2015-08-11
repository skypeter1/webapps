<?php

/**
 * @package /app/Import/Idml/IdmlSpread.php
 *  
 * @class   IdmlSpread
 * 
 * @description This class is the parser for IDML spreads (which are XML files). It is instantiated by IdmlAssembler
 *          and its main parsing function is called by IdmlAssembler::assemble().  A spread is an IDML definition of
 *          one or two pages (depending on whether or not the IDML package uses "FacingPages"). Spreads contain the
 *          information about page dimensions and placement, so converting each page's frame from IDML coordinates
 *          (within a spread) to CSS coordinates in an HTML document is among this class's tasks.
 * 
 *          Spreads also contain text frames that point to stories, so determining the absolute position of these
 *          inner text frames is necessary for fixed layout books. On the other hand, text frames in reflowable books
 *          do not, by definition, have position or size, so those coordinates can be ignored when producing the HTML.
 *          Nevertheless, in order to determine which page a text frame falls within, the position information must
 *          still be computed.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlTransformation',     'Import/Idml');
App::uses('IdmlBoundary',           'Import/Idml');
App::uses('IdmlPage',               'Import/Idml');
App::uses('IdmlTextFrame',          'Import/Idml/PageElements');
App::uses('IdmlRectangle',          'Import/Idml/PageElements');
App::uses('IdmlGroup',              'Import/Idml/PageElements');
App::uses('IdmlPolygon',            'Import/Idml/PageElements');
App::uses('IdmlOval',               'Import/Idml/PageElements');
App::uses('IdmlGraphicLine',        'Import/Idml/PageElements');


class IdmlSpread
{
    /** 
     * Is the parent object.
     * @var IdmlPackage
     */
    private $idmlPackage;
        
    /**
     * is either 'Spread' or 'MasterSpread' which corresponds to the
     * XML file's highest level tag: <idPkg:Spread> and <idPkg:MasterSpread>
     * @var string
     */
    protected $xmlRootName;
        
    /** 
     * is the InDesign unique identifier for this spread.
     * @var string 
     */
    public $UID;
    
    /**
     * is the transformation for the entire spread.
     * @var IdmlTransformation
     */
    public $transformation;
    
    /**
     * is the number of pages (per InDesign).
     * @var integer
     */
    public $pageCount;

    /**
     * An indexed collection of pages to be produced by this spread. Usually 1 or 2.
     * @var array[IdmlPage]
     */
    public $pages;

    /**
     * Filename where spread is. This is full path.
     * @var string
     */
    public $filename;

    /** The constructor
     * @param IdmlPackage $idmlPackage is the parent object. If not provided spread does not have a parent.
     */
    public function __construct($idmlPackage = null)
    {
        $this->idmlPackage = $idmlPackage;
        $this->xmlRootName = 'Spread';
        $this->UID = '';
        $this->transformation = new IdmlTransformation();
        $this->pages = array();
        $this->pageCount = 0;
    }

    
    public function parentIdmlObject()
    {
        return $this->idmlPackage;
    }
    
    
    /** Quickly scan the spreads to determine how many pages are in this package. This is suitable for use
     *  by IdmlAssembler::preparation().
     *  Note: this is not for use by IdmlAssembler::parse() and its cascading functions, use $this->pageCount for that.
     * 
     *  @param string $filename is a fully qualified filename of a Spread item XML file.
     *  @return integer $pageCount
     */
    public function determinePageCount($filename)
    {
        // create DOMDocument and DOMXPath objects
        $doc = new DomDocument();
        $b = $doc->load($filename);
        if ($b === false)
        {
            return false;
        }
        $xpath = new DOMXPath($doc);

        $q = "//idPkg:{$this->xmlRootName}/{$this->xmlRootName}";
        $tags = $xpath->query($q);
        $attributes = $tags->item(0)->attributes;
        $pageCount = (integer)$attributes->getNamedItem('PageCount')->value;
        
        return $pageCount;
    }
    
    
    /**
     *  The parse function is the starting point for parsing the spread.
     *  This will load spread from $filename or if $filename is empty it will use its own internal variable.
     *
     *  @param string $filename
     */
    public function load($filename = '')
    {
        if(empty($filename))
        {
            $filename = $this->filename;
        }
        
        // create DOMDocument and DOMXPath objects
        $doc = new DomDocument();
        $b = $doc->load($filename);
        if ($b === false)
        {
            return;
        }

        $this->parse($doc);
    }

    /**
     * Parse DOM.
     * 
     * @param DOMDocument $doc
     */
    private function parse(DOMDocument $doc)
    {
        $xpath = new DOMXPath($doc);

        $q = "//idPkg:{$this->xmlRootName}/{$this->xmlRootName}";
        $nodes = $xpath->query($q);
        $pageNode = $nodes->item(0);

        $attributes = $pageNode->attributes;
        $this->UID = $attributes->getNamedItem('Self')->value;
        $itemTransform = $attributes->getNamedItem('ItemTransform')->value;
        $this->transformation = new IdmlTransformation($itemTransform);
        $this->pageCount = (integer)$attributes->getNamedItem('PageCount')->value;

        if ($pageNode->hasAttribute('BindingLocation'))
        {
            $bindingLocation = (integer)$pageNode->getAttribute('BindingLocation');
        }
        else
        {
            // this happens with MasterSpreads which do not have a binding location.
            $bindingLocation = -1;
        }


        // First construct the pages (typically one or two pages per spread)
        // although more than two are technically allowed.
        // We need to have pages loaded first.
        $q = "//idPkg:{$this->xmlRootName}/{$this->xmlRootName}//Page";
        $nodes = $xpath->query($q);
        foreach($nodes as $pageNode)
        {
            $newPage = new IdmlPage($this);
            $newPage->parse($pageNode);

            $this->pages[] = $newPage;
        }

        // Process children text frames and rectangles.
        $spreadNodes = $doc->getElementsByTagName($this->xmlRootName);
        $spreadNode = $spreadNodes->item(1);

        // Child nodes.
        foreach($spreadNode->childNodes as $element)
        {
            $nodeSelf = '';
            if ($element->nodeType == XML_ELEMENT_NODE)
            {
                /*@var $element DomNode*/
                //get the element's id if possible and make sure it is not to be hidden before we parse it
                $selfIdNode = $element->attributes->getNamedItem('Self');
                if($selfIdNode)
                {
                 $nodeSelf = $selfIdNode->nodeValue;
                }
            }
            if($nodeSelf && (array_key_exists($nodeSelf, $this->idmlPackage->chaucerHidden)))
            {
                error_log('Processing Spread Page, hiding '.$element->nodeName.' '.$nodeSelf.' '.__FILE__.' Line '.__LINE__);
            }
            elseif ($element->nodeType == XML_ELEMENT_NODE)
            {
                switch ($element->nodeName)
                {
                    case 'Rectangle':
                        // @TODO - determine whether spans should be used for rectangles parallel to the axes, and code accordingly
//                        $rectangle = new IdmlRectangle();
                        $rectangle = new IdmlPolygon();
                        $rectangle->parse($element);
                        $this->dropElementIntoPage($rectangle);
                        break;

                    case 'TextFrame':
                        $newTextFrame = new IdmlTextFrame();
                        $newTextFrame->parse($element);
                        $this->dropElementIntoPage($newTextFrame);
                        $pageUID = ($newTextFrame->page != null) ? $newTextFrame->page->UID : '000';

                        // And now for some hyperlink management:
                        $hyperlinkMgr = IdmlHyperlinkManager::getInstance();

                        // Update the destination page names for links to anchors.
                        // Refs to the anchors are stored during processing of children, but we don't have the UIDs until the text frame is fully parsed.
                        $hyperlinkMgr->updateDestinationPages($newTextFrame->UID, $pageUID);

                        // Also, map the page UID to the text frame UID so the destination page name can be determined for fixed layout.
                        $hyperlinkMgr->saveTextFrameUID($newTextFrame->UID, $pageUID);
                        break;

                    case 'Group':
                        $group = new IdmlGroup();
                        $group->parse($element);
                        $this->dropElementIntoPage($group);
                        break;

                    case 'Polygon':
                        $polygon = new IdmlPolygon();
                        $polygon->parse($element);
                        $this->dropElementIntoPage($polygon);
                        break;

                    case 'Oval':
                        $oval = new IdmlOval();
                        $oval->parse($element);
                        $this->dropElementIntoPage($oval);
                        break;

                    case 'GraphicLine':
                        $line = new IdmlGraphicLine();
                        $line->parse($element);
                        $this->dropElementIntoPage($line);
                        break;

                    default:
                        // Placeholder: no default action at this time
                        break;
                }
            }
        }
    }

    /**
     * Drop element into page. It will set the warning if it fails.
     * @param IdmlElement $element
     */
    private function dropElementIntoPage(IdmlElement $element)
    {
        if ($element->visible == true)
        {
            // Convert element's coordinates to Page coordinates
            $boundary = IdmlBoundary::transform($element->boundary, $element->transformation);
            $idmlPage = $this->determinePageFromCoordinates($boundary);

            if ($idmlPage != null && get_class($idmlPage) == 'IdmlPage')
            {
                // Share mutual references between the page and the text frame.
                $element->page = $idmlPage;
                $idmlPage->addChildElement($element);
            }
            else
            {
                $element->page = null;
                $progressUpdater = IdmlAssembler::getProgressUpdater();
                if ($progressUpdater)
                {
                    $progressUpdater->setWarning('Could not find page for current text frame.');
                }
            }
        }
    }
    
    /**
     * Since a spread contains one or two (or more) pages, we need a way to determine which spread objects are on
     * which page. Do this by passing in the center point of the object in question. Determine which page the point
     * falls on, and return that page. Note that a point may not fall on any page because it is on the
     * InDesign "artboard" -- these should be ignored. Also note that pages never overlap, so a point can never fall
     * on more than one page.
     * 
     * This algorithm does not look to see if the object is wholly on the page. This is intentional because it is quite
     * common for objects to be stretched a few pixels off the page by the designer. Also designers like to have bleeds
     * that fully go off the page. When an object is bled off like this the resulting CSS might have negative values for
     * top and left, or values for right and bottom that are larger than the page size. The resultant CSS should use
     * "overflow:hidden" for these cases.
     * 
     * @param float $xcenter is x value of the center point to test -- using this IdmlSpread's frame of reference.
     * @param float $xyenter is y value of the center point to test -- using this IdmlSpread's frame of reference.
     * 
     * @return IdmlPage|null The return may be an IdmlPage or may be null. The caller must test for this,
     * since both are valid.
     */
    public function determinePageFromCoordinates($coordinates)
    {
        foreach($this->pages as $page)
        {
            $boundary = IdmlBoundary::transform($page->boundary, $page->transformation);
            if ($boundary->isPointInside($coordinates->getCenterX(), $coordinates->getCenterY()) == true)
            {
                return $page;
            }
        }
        return null;    // not on either page, probably on the "artboard"
    }


    /* This accept function is called by the parent IdmlPackage.
     * 
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth)
    {
        $visitor->visitSpread($this, $depth);

        foreach($this->pages as $page)
        {
            $page->accept($visitor, $depth+1);
        }

        $visitor->visitSpreadEnd($this, $depth);
    }
    
}
?>
