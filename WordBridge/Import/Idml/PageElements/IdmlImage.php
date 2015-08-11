<?php
/**
 * @package /app/Import/Idml/PageElement/IdmlImage.php
 * 
 * @class   IdmlImage
 * 
 * @description This class defines an image, which may be either inline or at the block level.
 *          Most of the processing for scaling images to be within the page size, and compressing images to be within
 *          book distributor filesize limitations, are handled by the Media Manager.
 *          Also, upload processing, moving to the temporary directory, saving to the database, and generating
 *          thumbnails is all outside of this class.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlAssembler',      'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlMedia',          'Import/Idml/PageElements');
App::uses('IdmlRectangle',      'Import/Idml/PageElements');


class IdmlImage extends IdmlMedia
{
    const IMAGE_WIDTH_THRESHOLD = 650;          // images that are wider than this, will be reduced to 85% using CSS
    const IMAGE_HEIGHT_THRESHOLD = 800;         // images that are taller than this, will be reduced to 55% using CSS
    const IMAGE_WIDTH_PERCENT = 85;             // images that are too wide for a typical eBook device, will be shown at this percent
    const IMAGE_HEIGHT_PERCENT = 65;            // images that are too tall for a typical eBook device, will be shown at this percent
    const IMAGE_WIDTH_HEIGHT_PERCENT = 50;      // images that are too wide and too tall for a typical eBook device, will be shown at this percent

    /**
     * @var integer $width
     */
    public $width;

    /**
     * @var integer $height
     */
    public $height;

    /** @var int $ppiX is the horizontal pixels per inch, as recorded by InDesign, necessary for cropping */
    public $ppIX;
    
    /** @var int $ppiY is the vertical pixels per inch, as recorded by InDesign, necessary for cropping */
    public $ppIY;
    
    /**
     * Image content. Full image content base64 encoded. Takes a lot of memory and should be cleaned after usage.
     * @var string
     */
    public $imageContent;

    /**
     * Is this image embedded.
     * @var boolean default is false.
     */
    public $embeddedImage = false;

    /**
     * Idml resource manager.
     * @var IdmlResourceManager
     */
    public $resourceManager;

    /**
     * Constructor.
     *
     * @param IdmlResourceManager $resourceManager Default is null or if you want to inject the object you can pass the param.
     */
    public function __construct(IdmlResourceManager $resourceManager = null)
    {
       if(is_null($resourceManager))
       {
           $this->resourceManager = IdmlAssembler::getInstance()->resourceManager;
       }
       else
       {
           $this->resourceManager = $resourceManager;
       }
       $this->boundary = IdmlBoundary::createDefault();
       $this->height = null;
       $this->width = null;
       $this->ppiX = null;
       $this->ppiY = null;
       $this->idmlTag = "img";
    }

    /**
     * Parse from DOM node.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);
        
        $actualPpi = $node->hasAttribute(IdmlAttributes::ActualPpi) ? $node->getAttribute(IdmlAttributes::ActualPpi) : '';
        $xy = explode(' ', $actualPpi);
        if (count($xy) == 2)
        {
            $this->ppiX = $xy[0];
            $this->ppiY = $xy[1];
        }
        
        // Get original width and height (in InDesign units of 72ppi)
        $graphicBoundNodes = $node->getElementsByTagName('GraphicBounds');
        $graphicBoundNode = $graphicBoundNodes->item(0);
        if ($graphicBoundNode)
        {
            $top    = $graphicBoundNode->getAttribute(IdmlAttributes::Top);
            $left   = $graphicBoundNode->getAttribute(IdmlAttributes::Left);
            $bottom = $graphicBoundNode->getAttribute(IdmlAttributes::Bottom);
            $right  = $graphicBoundNode->getAttribute(IdmlAttributes::Right);
            $this->boundary = new IdmlBoundary($top, $left, $bottom, $right);
            $this->width = $this->boundary->getWidth();
            $this->height = $this->boundary->getHeight();
        }

        // Load image content.
        $xpath = new DOMXPath($node->ownerDocument);
        $q = "./Properties/Contents";
        $contentNodes = $xpath->query($q, $node);
        if ($contentNodes->length > 0)
        {
            $contentNode = $contentNodes->item(0);
            $this->imageContent = IdmlParserHelper::getCData($contentNode);
            $this->embeddedImage = true;
        }

        $this->idmlTag = "img";

        $this->processImage();
    }

    /**
     * This will process image.
      */
    protected function processImage()
    {
        $containersAllowed = array('IdmlRectangle', 'IdmlPolygon');

        if ($this->resourceManager)
       {
           $parentElement = $this->parentIdmlObject();
           
           // <Image> items _should_ be inside <Rectangle> items . . .
           if (in_array(get_class($parentElement), $containersAllowed))
           {
               $this->resourceManager->registerImage($this, $parentElement);
           }
           // . . .but if not, make it clear to the function that we don't have a Rectangle
           else
           {
               $this->resourceManager->registerImage($this, null);
           }
       }
    }

    /**
     * return the URL of the image suitable for HTML output
     */
    public function getImageUrl()
    {
        
    }
    
    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        if ($this->visible)
        {
            // IdmlImage cannot have children.
            $visitor->visitImage($this, $depth);
        }
    }

}

?>
