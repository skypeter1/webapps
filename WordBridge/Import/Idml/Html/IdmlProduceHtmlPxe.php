<?php
/**
 * @package /app/Import/Idml/IdmlProduceHtmlPxe.php
 * 
 * @class   IdmlProduceHtmlPxe
 * 
 * @description Base class for IdmlProduceReflowablePxe and IdmlProduceFixedLayoutPxe.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

//?App::uses('Folder', 'Utility');
App::uses('IdmlProduceHtmlBase',    'Import/Idml/Html');
App::uses('PxeRules',               'Import/Idml/Pxe');
App::uses('IdmlPxeHelper',          'Import/Idml/Pxe');
App::uses('IdmlPxeExporter',        'Import/Idml/Pxe');
App::uses('IdmlImage',              'Import/Idml/PageElements');


class IdmlProduceHtmlPxe extends IdmlProduceHtmlBase
{
    public $hasDivParent = false;
    public $pxeRules;

    public function visitAssembler(IdmlAssembler $item, $depth = 0)
    {
        $this->pxeRules = new PxeRules();
        parent::visitAssembler($item, $depth);
    }

    public function visitAssemblerEnd(IdmlAssembler $item, $depth = 0)
    {
        //$this->addCssImages();
        //$this->addCssFonts();
        parent::visitAssemblerEnd($item, $depth);
    }

    public function addCssImages()
    {
        if(!is_object(IdmlAssembler::getInstance()->getProcessor()))
        {
            return;
        }
        $dir = new Folder(APP."Data/Pxe/Images");
        $files = $dir->find();
        foreach($files as $file)
        {
            IdmlAssembler::getInstance()->getProcessor()->addImageAssetToBook($dir->pwd().DS.$file, "images/");
        }
    }

    public function addCssFonts()
    {
        if(!is_object(IdmlAssembler::getInstance()->getProcessor()))
        {
            return;
        }
        $dir = new Folder(APP."Data/Pxe/Fonts");
        $files = $dir->find();
        foreach($files as $file)
        {
            IdmlAssembler::getInstance()->getProcessor()->addFontToBook($dir->pwd().DS.$file);
        }
    }

    public function verifyElementId($element, $createIfEmpty=false)
    {
        $id = $element->idAttribute;
        if(strlen($id)>0 && substr($id,0,3) != 'pxe')
        {
            $id = 'pxe-'.$id;
        }
        elseif(!strlen($id) && $createIfEmpty)
        {
            if(IdmlPxeHelper::hasPxeHash($element))
            {
                $id = "pxe-".IdmlPxeHelper::getPxeHash($element);
            }
            else
            {
                $id = "pxe-".IdmlPxeHelper::getNewTagHash();
            }
        }
        $element->idAttribute = $id;
    }


    public function verifyParentIds($parents)
    {
        $parentList = explode(" ",$parents);
        $parents = array();
        if(!is_object($this->pxeRules))
        {
            $this->pxeRules = new PxeRules();
        }
        foreach($parentList as $parent)
        {
            if($this->pxeRules->requiresAttribute($parent,'id'))
            {
                $hashValue = substr($parent,strpos($parent,"#")+1);
                $jsonData = '{"id":"'.$hashValue.'"}';
                //the ENT_QUOTES flag converts both " and ' to entities
                $parents[] = IdmlPxeHelper::stripHash($parent).htmlentities($jsonData, ENT_QUOTES)."#$hashValue";
            }
            else
            {
                $parents[] = $parent;
            }
        }
        return implode(' ',$parents);
    }

    public function processElement($element, $depth)
    {
        if(IdmlPxeHelper::hasPxeParents($element) || IdmlPxeHelper::elementHasContent($element))
        {
            $cssClassNames = array();

            IdmlPxeHelper::setPxeParents($element, $this->verifyParentIds(IdmlPxeHelper::getPxeParents($element)));
            $tagName = IdmlPxeHelper::getPxeTag($element);
            if(IdmlPxeHelper::hasPxeClass($element))
            {
                $tagName .= ".".IdmlPxeHelper::getPxeClass($element);
            }
            if(!is_object($this->pxeRules))
            {
                $this->pxeRules = new PxeRules();
            }

            if($this->pxeRules->requiresAttribute($tagName,"id"))
            {
                $this->verifyElementId($element, true);
            }
            else
            {
                $this->verifyElementId($element);
            }
            if(strlen($element->idAttribute)>0)
            {
                $element->attribs["id"] = $element->idAttribute;
            }
            $element->attribs['class'] = implode(' ', $cssClassNames);
            $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
            if($this->hasDivParent)
            {
                $this->addPageElement("<span $strAttr>", $depth);
                $element->idmlTag = "span";
            }
            else
            {
                $this->addPageElement("<div $strAttr>", $depth);
                $element->idmlTag = "div";
                $this->hasDivParent = true;
            }
        }
        else
        {
            $element->idmlTag = "(empty)";
        }
    }

    public function closeElement($element, $depth)
    {
        if($element->idmlTag != "(empty)" && strlen($element->idmlTag)>0)
        {
            $this->addPageElement("</".$element->idmlTag.">", $depth);
            if($element->idmlTag=="div")
            {
                $this->hasDivParent = false;
            }
        }
    }

    /**
     * Overrides base class to determine if the content has pxe context assigned, and if so
     * process it accordingly.  If the content element has a pxe tag (which would only be a span) then process the tag,
     * add the content, and close the tag.. otherwise just add the content normally
     * and let the pxe producer handle wrapping tags
     * @param IdmlContent $element
     * @param int $depth
     */
    public function visitContent(IdmlContent $element, $depth = 0)
    {
        if(!$element->hasContent())
        {
            //skip empty content tags
            return;
        }
        if(IdmlPxeHelper::hasPxeTag($element))
        {
            $this->processElement($element, $depth);
            parent::visitContent($element, $depth);
        }
        elseif(IdmlPxeHelper::hasPxeParents($element))
        {
            //get last parent and put this content inside it by assigning it
            $parents = IdmlPxeHelper::getPxeParents($element, true);
            if(count($parents)>0)
            {
                $parts = explode('#',$parents[count($parents)-1]);
                IdmlPxeHelper::setPxeTag($element, $parts[0]);
                IdmlPxeHelper::setPxeHash($element, $parts[1]);
                IdmlPxeHelper::setPxeParents($element, implode(' ',  array_slice($parents, 0, count($parents)-1)));
                $this->processElement($element, $depth);
                parent::visitContent($element, $depth);
            }
        }
        else
        {
            parent::visitContent($element, $depth);
        }
    }

    /**
     * Overrides base class to determine if the content has pxe context assigned, and if so
     * process it accordingly.  If the content element has a pxe tag (which would only be a span) then process the tag,
     * add the content, and close the tag.. otherwise just add the content normally
     * and let the pxe producer handle wrapping tags
     * @param IdmlContent $element
     * @param int $depth
     */
    public function visitContentEnd(IdmlContent $element, $depth = 0)
    {
        if(!$element->hasContent())
        {
            //skip empty content tags
            return;
        }
        if(IdmlPxeHelper::hasPxeTag($element))
        {
            parent::visitContentEnd($element, $depth);
            $this->closeElement($element, $depth);
        }
        elseif(IdmlPxeHelper::hasPxeParents($element))
        {
            //get last parent and put this content inside it by assigning it
            $parents = IdmlPxeHelper::getPxeParents($element, true);
            if(count($parents)>0)
            {
                parent::visitContentEnd($element, $depth);
                $this->closeElement($element, $depth);
            }
        }
        else
        {
            parent::visitContentEnd($element, $depth);
        }
    }

    public function visitParagraphRange(IdmlParagraphRange $element, $depth = 0)
    {
    }

    public function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0)
    {
    }

    public function visitCharacterRange(IdmlCharacterRange $element, $depth = 0)
    {
    }

    public function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0)
    {
    }

    public function visitHyperlink(\IdmlHyperlink $element, $depth = 0)
    {
        if(IdmlPxeHelper::hasPxeData($element))
        {
            $this->processElement($element, $depth);
        }
        else
        {
            parent::visitHyperlink($element, $depth);
        }
    }

    public function visitHyperlinkEnd(\IdmlHyperlink $element, $depth = 0)
    {
        if(IdmlPxeHelper::hasPxeData($element))
        {
            $this->closeElement($element, $depth);
        }
        else
        {
            parent::visitHyperlinkEnd($element, $depth);
        }
    }

    public function visitXmlElement(IdmlXmlElement $element, $depth = 0)
    {
        if(IdmlPxeHelper::hasPxeData($element))
        {
            $this->processElement($element, $depth);
        }
    }

    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0)
    {
        if(IdmlPxeHelper::hasPxeData($element))
        {
            $this->closeElement($element, $depth);
        }
    }

    public function visitBrContent(IdmlBrContent $element, $depth = 0)
    {
    }

    /**
     * Visit image.
     * <img> tag
     * @param IdmlImage $element
     * @param int $depth
     */
    public function visitImage(IdmlImage $element, $depth = 0)
    {
        $element->attribs['class'] = $this->setDimensionThresholds($element->width, $element->height);

        $this->verifyElementId($element, true);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }

        $element->attribs['src'] = 'images/' . $element->mediaFilename;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = "<img $strAttr />";
        $this->addPageElement($html, $depth);
    }

    /**
     * @param int $width
     * @param int $height
     * @return string
     */
    public function setDimensionThresholds($width, $height)
    {
        $dimensionThresholdClass = '';  // If no threshold class, an empty string will simply not designate a class

        if ($width  <= IdmlImage::IMAGE_WIDTH_THRESHOLD &&
            $height <= IdmlImage::IMAGE_HEIGHT_THRESHOLD)
        {
            // keep full size
        }
        else if ($width  > IdmlImage::IMAGE_WIDTH_THRESHOLD &&
            $height > IdmlImage::IMAGE_HEIGHT_THRESHOLD)
        {
            $dimensionThresholdClass = 'img-wh-threshold';
        }
        else if ($width > IdmlImage::IMAGE_WIDTH_THRESHOLD)
        {
            $dimensionThresholdClass = 'img-w-threshold';
        }
        else if ($height > IdmlImage::IMAGE_HEIGHT_THRESHOLD)
        {
            $dimensionThresholdClass = 'img-h-threshold';
        }
        return $dimensionThresholdClass;
    }

    /**
     * Overrides base class to remove the thead/tbody/tfoot tags as these are set in the pxe structure
     * @param IdmlTableRow $element
     * @param type $depth
     */
    public function visitTableRow(IdmlTableRow $element, $depth = 0)
    {
        $this->verifyElementId($element);
        $cssClassNames = array();
        $element->attribs['class'] = implode(' ', $cssClassNames);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $this->addPageElement( "<tr $strAttr>", $depth);
        $element->idmlTag = 'tr';
    }

    /** Overrides base class to remove the thead/tbody/tfoot tags as these are set in the pxe structure
     *
     * @param IdmlTableRow $element
     * @param type $depth
     */
    public function visitTableRowEnd(IdmlTableRow $element, $depth = 0)
    {
        $this->addPageElement("</tr>", $depth);
    }

    public function getPageContent()
    {
        $html = parent::getBodyContent();
        if(Configure::read('processor.IdmlGeneratePxeOutput')==true)
        {
            $pxeExporter = new IdmlPxeExporter("<html><head></head><body>$html</body></html>");
            $pxeExporter->process();
            $html = $pxeExporter->getContent();
        }
        return $html;
    }

    public function writeSectionBegin($element, $depth)
    {
        $cssClassNames = array();
        $element->attribs['class'] = implode(' ', $cssClassNames);

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
            $element->attribs['id'] = $element->idAttribute;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<div ' . $strAttr . '>';

        $this->addPageElement($html, $depth);
    }


    public function writeSectionEnd(IdmlTextFrame $element, $depth)
    {
        $html = '</div>';
        $this->addPageElement($html, $depth);
    }


    public function writeAsideBegin($element, $depth)
    {
        $cssClassNames = array();
        $element->attribs['class'] = implode(' ', $cssClassNames);

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
            $element->attribs['id'] = $element->idAttribute;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<div ' . $strAttr . '>';

        $this->addPageElement($html, $depth);
    }


    public function writeAsideEnd($depth)
    {
        $html = '</div>';
        $this->addPageElement($html, $depth);
    }



}
