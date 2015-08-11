<?php
/**
 * @package /app/Import/Idml/IdmlProduceHtmlBase.php
 * 
 * @class   IdmlProduceHtmlBase
 * 
 * @description Base class for IdmlProduceHtml (non-PXE) and IdmlProduceHtmlPxe.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::import('Vendor', 'Ganon', 'ganon.php');

App::uses('IdmlVisitor',            'Import/Idml');
App::uses('IdmlPostProcessor',      'Import/Idml');
App::uses('IdmlDeclarationManager', 'Import/Idml/Styles/Declarations');

define('MAIN_TEXT_FRAME_TAG', 'section');
define('EMBEDDED_TEXT_FRAME_TAG', 'aside');


abstract class IdmlProduceHtmlBase implements IdmlVisitor
{
    /**
     * Page elements are snippets of HTML added in consecutive order, and assembled as a document in getBodyContent()
     * @var array[string]
     */
    protected $pageElements;

    /**
     * Diagnostic page elements are snippets of HTML added in consecutive order, and assembled as a document in getDiagnosticContent()
     * @var array[string]
     */
    protected $diagnosticPageElements;

    /**
     * Page Number.
     * @var int
     */
    protected $pageNumber;

    /**
     * @var IdmlElement $pageObject - identifies the object (either an IdmlPage or an IdmlTextFrame) which represents the current xhtml page
     */
    protected $pageObject;

    /**
     * pageNameXref is an array containing filenames for each page, indexed by the element's UID
     * @var array $pageNameXref
     */
    protected $pageNameXref;

    /**
     * currSvgUID indicates the UID of the current first open ancesotor UID element
     * When null, no svg element is open; when not null, close the svg and set to null when that svg closes
     * @var $currSvgUID
     */
    public $currSvgUID = null;
    /**
     * Producer instance.
     * @var IdmlProduceHtmlBase $instance
     */
    protected static $instance;

    /**
     * Declaration Manager
     * @var IdmlDeclarationManager
     */
    protected $declarationMgr;

    /**
     * Constructor.
     * @var integer $currentRecursivePageNumber since this class is used recursively, we need to keep track
     *              of which page we are on when we construct a new instance. Use 1 for the outermost instance.
     */
    public function __construct($currentRecursivePageNumber = 1)
    {
        $this->pageElements = array();
        $this->diagnosticPageElements = array();
        $this->pageNumber = $currentRecursivePageNumber;
        $this->declarationMgr = IdmlDeclarationManager::getInstance();
        $this->pageNameXref = array();
        $this->currSvgUID = null;
    }

    /**
     * Initialize producer first time.
     */
    public function init()
    {
        $this->pageElements = array();
        $this->diagnosticPageElements = array();
    }

    /**
     * @param IdmlElement $element - IdmlParagraphRange, IdmlCharacterRange, etc.
     * @return string $styleAttrib - the full string to be assigned to the HTML style='' attribute, including fixed layout styles.
     */
    public function getStyleAttrib(IdmlElement $element)
    {
        $styleAttribs = array();

        $styleAttribs[] = $element->getStyleOverrides();
        $styleAttribs[] = $this->getDimensionCSS($element);
        $styleAttribs[] = $this->getPositionCSS($element);
        $styleAttribs[] = $this->getTransformationCSS($element);

        $s = implode(' ', $styleAttribs);
        $s = trim($s);
        return $s;
    }

    /**
     * The getDimensionCSS function should return a CSS declaration for the element's width and height.
     * This should be overridden by all producers to provide width and height to elements that need it (rectangles, groups, images)
     */
    public function getDimensionCSS(IdmlElement $element)
    {
        return '';
    }

    /**
     * The getPositionCSS function should return a CSS declaration for the element's top and left.
     * This should be overridden by producers that create fixed layout.
     */
    public function getPositionCSS(IdmlElement $element)
    {
        return '';
    }

    /**
     * The getTransformationCSS function should return a CSS declaration for the element's rotation or skew.
     * This should be overridden by producers that want to support rotation and skew.
     */
    public function getTransformationCSS(IdmlElement $element)
    {
        return '';
    }

    abstract function verifyElementId($element);

    /**
     * Utility function to convert an array of html attributes to a string.
     * Merges any attributes passed in with those set already
     *
     * @param IdmlElement element object being parsed
     * @param array optional hash of html attributes and values
     *
     * @return string html representation of attributes
     */
    public function getHTMLAttributesString(IdmlElement $element, $attribs=array())
    {
        $allAttributes = array_merge($element->attribs, $attribs);
        $str = "";
        foreach($allAttributes as $name=>$value)
        {
            if(strlen($value)>0)
            {
                $str .= sprintf( "%s=\"%s\" ", $name, $value);
            }
        }
        return trim($str);
    }      
    
    /**
     * Add page element.
     * @param string $content contains an HTML snippet
     * @param int $depth
     * @param string $diagnosticContent is approximately the same thing as $content but formatted for
     *   easy human reading, this parameter is only used by visitTextFrame.
     */
    public function addPageElement($content, $depth, $diagnosticContent = null)
    {
       $this->pageElements[] = array($content, $depth);

        if (Configure::read("dev.idmlHtmlDebugOutput") == true)
            CakeLog::debug(sprintf("[IdmlProduceHtml::addPageElement] %40s --> %s (%6.2fMb)", substr($content,0,37)."...", memory_get_usage(true), round(memory_get_usage(true)/1024/1024,2)));
       
        if ($diagnosticContent != null)
        {
            $this->diagnosticPageElements[] = array($diagnosticContent, $depth);
        }
        else
        {
            $this->diagnosticPageElements[] = array($content, $depth);
        }
    }


    /**
     * Get current body content.
     * @return string containing HTML
     */
    public function getBodyContent()
    {
        $html = array();
        foreach($this->pageElements as $item)
        {
            $html[] = $item[0];
        }
        return implode('',$html);
    }

    /**
     * This function is used for retrieving the array of page elements, used for retrieving embedded text frame content.
     * @return array
     */
    public function getPageElements()
    {
        return $this->pageElements;
    }

    /**
     * @return string $html - page html, including head and body tags.
     */
    public function getPageContent()
    {
        $htmlBody = $this->getBodyContent();
        $html = '<html><head/><body>' . $htmlBody . '</body></html>';
        return $html;
    }


    /**
     * return true if page has elements
     * @return boolean
     */
    public function hasPageContent()
    {
        return (count($this->pageElements)>0);
    }
    
    /**
     * Get current page content formatted for human readability.
     * This cannot be used as the real output because it introduces spaces between elements that HTML should not render.
     * @return string containing HTML
     */
    public function getDiagnosticContent()
    {
        $html = array();
        foreach($this->diagnosticPageElements as $item)
        {
            $depth = $item[1];
            $html[] = str_repeat(' ',$depth*4) . $item[0];
        }
        return implode("\n",$html);
    }

    /**
     * Get array of page elements for diagnostic content, used for adding embedded text frame content
     * @return array
     */
    public function getDiagnosticPageElements()
    {
        return $this->diagnosticPageElements;
    }

    /** A function to view each pages output in pretty format and "real" output
     */
    public function diagnosticTool($content)
    {
        $pagename = "page" . str_pad($this->pageNumber, 3, "0", STR_PAD_LEFT);

        $diagnosticContent = $this->getDiagnosticContent();
        $tmpPath = Configure::read('dev.idmlHtmlDebugPath');
//        $tmpPath = '/var/share/tmp/pages';
        if(strlen($tmpPath))
        {
            if(!is_dir($tmpPath))
            {
                mkdir($tmpPath, 0777, true);
            }
            if (get_class($this) == 'IdmlProduceHtmlDiagnostic')
            {
                file_put_contents("$tmpPath/dom_$pagename", $diagnosticContent);
            }
            else // IdmlProduceReflowable || IdmlProduceFixedLayout
            {
                file_put_contents("$tmpPath/pretty_$pagename", $diagnosticContent);
                file_put_contents("$tmpPath/real_$pagename", $content);
            }
        }
    }

    /**
     * Save page.
     * @return Page $pageFile
     */
    protected function savePage()
    {
        $content = $this->getPageContent();

        if(Configure::read("dev.idmlHtmlDebugOutput"))
        {
            // DEBUG
            $this->diagnosticTool($content);        // <-- Turn off in production
            // DEBUG
        }

        $processor = IdmlAssembler::getProcessor();
        if ($processor)
        {
            $currentPageToSave = $this->pageNumber;
            if(!Configure::read("dev.idmlPostProcessing")) // for debugging, keep styles inline
            {
                $pageFile = $processor->savePageHTML($content, $currentPageToSave, '', 0, null, $this->pageObject->properties);
                $processor->savePageCSS('', $currentPageToSave);
            }
            else // for production, remove any truly empty elements, and put styles into separate page-level CSS files
            {
                $postProcessor = new IdmlPostProcessor();

                $cleanHtml = $postProcessor->removeEmptyElements($content);
                // $num = $postProcessor->numElementsDeleted;

                $dual = $postProcessor->segregateCssFromHtml($cleanHtml);
                $html = $dual['html'];
                $css = $dual['css'];

                $pageFile = $processor->savePageHTML($html, $currentPageToSave, '', 0, null, $this->pageObject->properties);
                $processor->savePageCSS($css, $currentPageToSave);
            }

            IdmlAssembler::getInstance()->actualPages++;
        }
        
        $this->pageNumber++;

        return $pageFile->getIdentifier();
    }

    /**
     * Clear Page. This is called when new page starts.
     * @return none
     */
    public function clearPage()
    {
        // Clear the page content since we are starting new page.
        $this->pageElements = array();
        $this->diagnosticPageElements = array();
    }
    

    /**
     * @param IdmlElement $element
     * @return string containing the canonical CSS classname applied to the given element,
     * suitable for use in an HTML class='' attribute
     */
    public function getCssClassname($element)
    {
        return $element->getCssClassname();
    }


    /**
     * @param IdmlAssembler $item
     * @param int $depth
     */
    public function visitAssembler(IdmlAssembler $item, $depth = 0)
    {
        $this->pageNumber = 1;
    }

    /**
     * For code not inside an svg tag, creates a div tag or a span tag.
     * For code inside an svg tag, creates a g tag.
     * @param IdmlGroup $element
     * @param int $depth
     */
    public function visitGroup(IdmlGroup $element, $depth = 0)
    {
        $element->attribs['style'] = $this->getStyleAttrib($element);

        // Remove the background color style, which should only apply to child elements
        $element->attribs['style'] = $this->stripStyles($element->attribs['style'], array('background-color'));

        if (is_null($this->currSvgUID))
        {
            $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
            $element->tagName = 'div';

            $html = '<' . $element->tagName . ' ' . $strAttr . '>';
        }
        else
        {
            $element->tagName = 'g';
            $html = '<' . $element->tagName . ' data-id="' . $element->UID . '">';
        }

        $this->addPageElement($html, $depth);
    }

    /**
     * Certain styles should not be applied to certain elements (e.g. background color does not apply to the group parent).
     * This function removes unwanted styles, given the elements style attribute string and an array of styles to remove.
     * @param array $stylesToStrip - array of style properties to strip
     * @param string $styleString - style string to be processed
     * @return string $newStyleString - string of styles stripped of indicated values
     */
    protected function stripStyles($styleString, $stylesToStrip)
    {
        $styleArray = explode(';', $styleString);
        $newStyleString = '';

        foreach ($styleArray as $style)
        {
            $style = trim($style);
            if (!in_array(substr($style, 0, strpos($style, ':')), $stylesToStrip))
            {
                $newStyleString .= $style . ';';
            }
        }

        return $newStyleString;
    }

    public function visitGroupEnd(IdmlGroup $element, $depth = 0)
    {
        $this->addPageElement('</' . $element->tagName . '>', $depth);

        if (!is_null($element->rotationOffset))
        {
            $this->addPageElement('<div style="height:' . $element->rotationOffset . 'px;">', 0);
            $this->addPageElement('</div>', 0);
            $element->rotationOffset = null;
        }
    }

    /**
     * This method is does nothing but is overwritten for non-PXE IDML in IdmlProduceHtml
     */
    public function closeFinalElements($depth)
    {
    }

    abstract function visitImage(IdmlImage $element, $depth = 0);

   /*
     * The following four functions are defined abstractly.
     * They are all defined abstractly in the parent class (IdmlVisitor) and must be redeclared here
     * These four functions must be coded in any subclass.
    */
    public abstract function visitParagraphRange(IdmlParagraphRange $element, $depth = 0);
    public abstract function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0);
    public abstract function visitCharacterRange(IdmlCharacterRange $element, $depth = 0);
    public abstract function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0);

    /**
     * Visit textual content element.
     * Content elements' text content is managed by their children elements, text and processing instructions
     * For PXE, tag management takes place in the derived classes.
     * Nothing needs to be done here.
     * @param IdmlContent $element
     * @param int $depth
     */
    public function visitContent(IdmlContent $element, $depth = 0)
    {   
    }

    /**
     * Visit textual content element end.
     * All processing, PXE or non, is managed in the derived classes.
     * @param IdmlContent $element
     * @param int $depth
     */
    public function visitContentEnd(IdmlContent $element, $depth = 0)
    {
    }

    /**
     * Visit textual content element.
     * @param IdmlText $element
     * @param int $depth
     */
    public function visitText(IdmlText $element, $depth = 0)
    {
        /*  The only escapes performed are:
                '&' (ampersand) becomes '&amp;'
                '<' (less than) becomes '&lt;'
                '>' (greater than) becomes '&gt;'
         */
        $escaped = htmlspecialchars($element->getTextContent(), ENT_NOQUOTES, 'UTF-8', false);
        $this->addPageElement($escaped, $depth);
    }

    /**
     * Visit tab object. This is overwritten for non-PXE reflowable.
     * @param IdmlTab $element
     * @param int $depth
     */
    public function visitTab(IdmlTab $element, $depth = 0){}

    /**
     * Visit text variable instance element.
     * @param IdmlTextVariableInstance $element
     * @param int $depth
     */
    public function visitTextVariableInstance(IdmlTextVariableInstance $element, $depth = 0)
    {
        $this->addPageElement($element->getTextContent(), $depth);
    }

    /**
     * This method generates the appropriate content indicated by an IDML processing instruction ('<?ACE n ?>').
     * NOTE: This should not do anything for PXE content, since it only applies to fixed layout.
     * @param IdmlProcessingInstruction $element
     * @param int $depth
     * @throws exception
     */
    public function visitProcessingInstruction(IdmlProcessingInstruction $element, $depth = 0)
    {
        // Convert the processing instruction to text content.
        $type = $element->getInstructionType();

        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');

        if (is_a($paragraph, 'IdmlParagraphRange') && $paragraph->hasNestedStyle)
        {
            $currNestedStyle = $this->getCurrentNestedStyles($paragraph->nestedStyleHelpers);

            if (!is_null($currNestedStyle))
            {
                $currNestedStyle->processContent($type, $element, $this, $depth);
                return;
            }

        }

        // No nested styles to process; render without additional character style
        $this->visitProcessingInstructionCases($element, $type, $depth);
    }

    public function visitProcessingInstructionCases($element, $type, $depth=0)
    {
        switch ($type)
        {
            case '18':

                // This instruction is a page number; retrieve it from the current page
                if (strpos(get_class($this), 'Fixed') !== false)
                {
                    $page = $this->getCurrentPage($element);

                    if (is_null($page) && isset($this->pageObject))
                    {
                        $page = $this->pageObject;
                    }

                    if (is_null($page))
                    {
                        throw new Exception('No page data found for content. Class: ' . get_class($element) . ' Value: ' . $type);
                    }

                    $textContent = $page->inDesignPageName;

                    // Add the content
                    $this->addPageElement($textContent, $depth);
                }

                break;

            default:

                break;
        }
    }

    public function visitTable(IdmlTable $element, $depth = 0)
    {
        $cssClassNames = array();
        $cssClassNames[] = 'table'; // this works for 'inline tables' but if table is part of figcaption must be handled differently

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }
        $element->attribs['class'] = implode(' ', $cssClassNames);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $this->addPageElement("<table $strAttr>", $depth);
        $element->idmlTag = 'table';

        //output the column group
        if(is_array($element->colGroupStyles) && count($element->colGroupStyles) > 0)
        {
            $this->addPageElement("<colgroup>", $depth+1);
            foreach ($element->colGroupStyles as $colstyle)
            {
                $this->addPageElement('<col style="' . $colstyle . '"/>', $depth+2);
            }
            $this->addPageElement("</colgroup>", $depth+1);
        }
    }
    
    public function visitTableEnd(IdmlTable $element, $depth = 0)
    {
        $this->addPageElement("</table>", $depth); 
    }

    /* All derived classes *must* define visitTableRow and visitTableRowEnd (they all do) */
    abstract function visitTableRow(IdmlTableRow $element, $depth = 0);
    abstract function visitTableRowEnd(IdmlTableRow $element, $depth = 0);

    public function visitTableCell(IdmlTableCell $element, $depth = 0)
    {
        $cssClassNames = array();
        $element->attribs['class'] = implode(' ', $cssClassNames);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);

        $span = '';
        if ($element->rowspan == 0 || $element->rowspan > 1)
        {
            $span .= 'rowspan="' . $element->rowspan . '"';       // rowspan of 0 tells the browser to span the cell to the last row
        }
        if ($element->colspan == 0 || $element->colspan > 1)
        {
            $span .= ' colspan="' . $element->colspan . '"';      // colspan of 0 tells the browser to span the cell to the last column of the colgroup
        }

        $row = $element->parentIdmlObject();
        if(IdmlParserHelper::getIdmlObjectType($row) == "TableRow")
        {
            /*@var $row IdmlTableRow*/
            if($row->rowType == 'thead')
            {
                $html = "<th $span $strAttr>";
                $element->idmlTag = 'th';
            }
            else
            {
                $html = "<td $span $strAttr>";
                $element->idmlTag = 'td';
            }
            $this->addPageElement($html, $depth);
        }
        else
        {
            CakeLog::debug("[IdmlProduceHtml::visitTableCell] parent element is not a TableRow ");
        }
    }
    
    public function visitTableCellEnd(IdmlTableCell $element, $depth = 0)
    {
        $this->addPageElement('</' . $element->idmlTag . '>', $depth);
    }
    
    public function visitHyperlink(IdmlHyperlink $element, $depth = 0)
    {
        $cssClassNames = array();
        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }
        if(strlen($element->href)>0)
        {
            $element->attribs['href'] = $element->href;
        }
        else
        {
            $element->attribs['name'] = $element->name;
        }
        $element->attribs['class'] = implode(' ', $cssClassNames);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $this->addPageElement("<span $strAttr>", $depth);   
    }

    /**
     * Hyperlink href attributes must be set before all the pages are named.
     * Go through the pages and replace the page UIDs in all hrefs with the actual page name.
     * Use Ganon to target only hyperlink href attributes
     * @param EpubManager $epubManager
     */
    public function fixHyperlinkPageNames(EpubManager $epubManager)
    {
        $pages = $epubManager->pageList();

        foreach ($pages as $pageFile)
        {
            $content = $epubManager->getPageContent($pageFile->relativePath);

            $dom = new HTML_Parser($content);

            $hyperlinks = $dom('a');

            if (count($hyperlinks) > 0)     // We only need to process hyperlinks on a page if a hyperlink exists.
            {
                foreach($hyperlinks as $hyperlink)
                {
                    $href = $hyperlink->getAttribute('href');
                    $correctedHref = str_replace(array_keys($this->pageNameXref), array_values($this->pageNameXref), $href);
                    $hyperlink->setAttribute('href', $correctedHref);
                }

                $epubManager->updatePageContent($pageFile->relativePath, (string)$dom);
            }

            unset($dom);
        }
    }

    public function visitHyperlinkEnd(IdmlHyperlink $element, $depth = 0)
    {
        $this->addPageElement("</span>", $depth);
    }


    public function visitTextFrame(IdmlTextFrame $element, $depth = 0)
    {
        $this->writeSectionBegin($element, $depth);
    }


    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0)
    {
        $this->writeSectionEnd($element, $depth);
    }

    /**
     * if the IdmlTextFrame has children, it should be converted to an HTML aside element.
     * If the IdmlTextFrame has no children, it's being used to create an inline rectangular block: treat it as a span.
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    public function visitEmbeddedTextFrame(IdmlTextFrame $element, $depth = 0)
    {
        if ($element->hasContent($element->story))
        {
            // Call function to create an aside
            $this->createAside($element, $depth);
        }
        else
        {
            // Call function to create a span
            $this->makeTextFrameASpan($element, $depth);
        }
    }

    /**
     * Create an HTML aside from the IDML TextFrame.
     * @param IdmlTextFrame $element
     * @param int $outer_depth
     */
    protected function createAside(IdmlTextFrame $element, $outer_depth = 0)
    {
        $inner_depth = (get_class($element->parentIdmlObject() ) == 'IdmlCharacterRange')  ? $outer_depth-3 : $outer_depth;
        $this->addPageElement("<!-- ANCHOR {$element->story->UID} -->", $inner_depth+1, '');
        $this->writeAsideBegin($element, $inner_depth+1);

        // Create a new producer for recursion . . .
        // NOTE: Logic using a 2nd producer has been eliminated. We think it is no longer effective
        //       Code is left in place in case things go horribly awry.
//        $thisClass = get_class($this);
//        $currentRecursivePageNumber = $this->pageNumber;
//        $htmlProducer = new $thisClass($currentRecursivePageNumber);

        // . . . then walk the tree starting from the current text frame's story . . .
        if ($element->story && $element->visible)
        {
            $element->story->accept($this, $outer_depth+1);
//            $element->story->accept($htmlProducer, $inner_depth+1);
        }

        // . . . and finally stuff the content that _it_ produces back into this object
        // Copy the page elements to a new array first to prevent side effects
//        $innerContent = $htmlProducer->getPageElements();
//        $pageElements = $this->pageElements;
//        $this->pageElements = array_merge($pageElements, $innerContent);

//        $diagnosticContent = $htmlProducer->getDiagnosticPageElements();
//        $diagnosticPageElements = $this->diagnosticPageElements;
//        $this->diagnosticPageElements = array_merge($diagnosticPageElements, $diagnosticContent);

        $this->writeAsideEnd($outer_depth+1);
        $this->addPageElement("<!-- /ANCHOR {$element->story->UID} -->", $inner_depth+1, '');

        if (!is_null($element->rotationOffset))
        {
            $this->addPageElement('<div style="height:' . $element->rotationOffset . 'px;">', 0);
            $this->addPageElement('</div>', 0);
            $element->rotationOffset = null;
        }
    }

    /**
     * Create a span representing the empty text frame
     * This function covers the edge case of a text frame used to create an inline rectangular block.
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    protected function makeTextFrameASpan(IdmlTextFrame $element, $depth=0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
        $element->attribs['style'] .= 'display:inline-block';

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<span ' . $strAttr . '>';
        $this->addPageElement($html, $depth);

        $this->addPageElement('</span>', $depth);
    }

    public function writeSectionBegin($element, $depth)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
//DEBUG
//        $element->attribs['style'] .= 'outline:#00f 2px dashed';
//DEBUG

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
            $element->attribs['id'] = $element->idAttribute;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<' . MAIN_TEXT_FRAME_TAG . ' ' . $strAttr . '>';
        $this->addPageElement($html, $depth);
    }


    public function writeSectionEnd(IdmlTextFrame $element, $depth)
    {
        $html = '</' . MAIN_TEXT_FRAME_TAG  . '>';
        $this->addPageElement($html, $depth);
    }


    public function writeAsideBegin($element, $depth)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
        $element->attribs['style'] .= 'display:inline-block';
//DEBUG
//        $element->attribs['style'] .= 'outline:#f00 2px solid';
//DEBUG

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
            $element->attribs['id'] = $element->idAttribute;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<' . EMBEDDED_TEXT_FRAME_TAG . ' ' . $strAttr . '>';
        $this->addPageElement($html, $depth);
    }


    public function writeAsideEnd($depth)
    {
        $html = '</' . EMBEDDED_TEXT_FRAME_TAG  . '>';
        $this->addPageElement($html, $depth);
    }


    /**
     * Visit Idml Sound or Movie element.
     * @param IdmlElement $element
     * @param int $depth
     */
    public function visitMedia(IdmlElement $element, $depth = 0)
    {
        $tagName = isset($element->tag) ? $element->tag : '';
        $element->attribs['style'] = $this->getStyleAttrib($element);
        if ($element->controls) $element->attribs['controls'] = 'controls';
        if ($element->loop) $element->attribs['loop'] = 'loop';
        if ($element->autoplay) $element->attribs['autoplay'] = 'autoplay';

        $element->attribs['src'] = '../' . strtolower($element->tag) . '/' . $element->mediaFilename;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);

        $html = '<' . $tagName . ' ' . $strAttr . '>' . strtolower($tagName) . ' file not available</' . $tagName . '>';
        $this->addPageElement($html, $depth);

        // If the media file is a remote resource, add a property to page object, to be used in the manifest
        if (substr($element->attribs['src'], 0, 4) == 'http')
        {
            $this->pageObject->properties[] = 'remote-resource';
        }
    }

    /**
     * Walk the IDML tree back to the IdmlPage, getting all ancestral objects
     * @param IdmlElement $idmlObject
     * @param string $stopAt The classname of the highest Idml object that we are interested in, should be 'IdmlPage' or 'IdmlStory'
     * @return array[IdmlElement] 
     */
    public function getAncestors($idmlObject, $stopAt)
    {
        $stack = array();
        // start by skipping the current element
        $obj = $idmlObject->parentIdmlObject();
        
        // now collect all ancestors: parent, grandparent, ... back to (but not including) the IdmlPage/IdmlStory
        while(!is_null($obj) && get_class($obj) != $stopAt)
        {
            $stack[] = $obj;
            $obj = $obj->parentIdmlObject();
        }
        return $stack; 
    }

    /**
     * Retrieves and returns the most proximate object ancestor of $element matching the provided classname.
     * Returns null if no such ancestor exists.
     * @param IdmlElement $element
     * @param string $className
     * @return mixed - IdmlParagraph or null
     */
    protected function getAncestor(IdmlElement $element, $className)
    {
        $parentElement = $element->parentElement;

        while (!is_null($parentElement) && get_class($parentElement) != $className)
        {
            $parentElement = $parentElement->parentElement;
        }

        return $parentElement;
    }

    /**
     * Rewind the stack back to the top closing each open tag. This is needed for the artificial 'ChaucerEditorBreak'
     * which breaks the hierarchical model.
     * 
     * @param array[IdmlElement] $stack
     * @param int $depth
     */
    public function rewind($stack, $depth)
    {
        foreach ($stack as $idmlObj)
        {
            $whoami = get_class($idmlObj);
            $depth--;
            switch ($whoami)
            {
                case 'IdmlTextFrame':
                    //$this->visitTextFrameEnd($idmlObj, $depth);
                    break;
                case 'IdmlStory':
                    $this->visitStoryEnd($idmlObj, $depth);
                    break;
                case 'IdmlParagraphRange':
                    // Rewind only if the paragraph is not a list
                    if ($idmlObj->listType == 'NoList')
                    {
                        $this->visitParagraphRangeEnd($idmlObj, $depth);
                    }
                    break;
                case 'IdmlCharacterRange':
                    // Rewind only if the closest paragraph ancestor is not a list
                    $paragraph = $this->getAncestor($idmlObj, 'IdmlParagraphRange');
                    if (!is_null($paragraph) && $paragraph->listType == 'NoList')
                    {
                        $this->visitCharacterRangeEnd($idmlObj, $depth);
                    }
                    break;
                case 'IdmlGroup':
//                    $this->visitGroupEnd($idmlObj, $depth);
                    break;
                case 'IdmlRectangle':
                    $this->visitRectangleEnd($idmlObj, $depth);
                    break;
                case 'IdmlTable':
                    $this->visitTableEnd($idmlObj, $depth);
                    break;
                case 'IdmlTableRow':
                    $this->visitTableRowEnd($idmlObj, $depth);
                    break;
                case 'IdmlTableColumn':
                    $this->visitTableColumnEnd($idmlObj, $depth);
                    break;
                case 'IdmlTableCell':
                    $this->visitTableCellEnd($idmlObj, $depth);
                    break;
                case 'IdmlXmlElement':
                    $this->visitXmlElementEnd($idmlObj, $depth);
                    break;
                case 'IdmlHyperlink':
                    $this->visitHyperlinkEnd($idmlObj, $depth);
                    break;

                case 'IdmlImage':
                case 'IdmlContent':
                case 'IdmlBrContent':
                    // these don't enclose anything and should not have children
                    break;

                case 'IdmlAssembler':
                case 'IdmlPackage';
                case 'IdmlSpread':
                case 'IdmlPage':
                    // these do not produce HTML
                    break;

                default:
                    error_log( "IdmlHtmlProducer::rewind() encountered an unexpected visitor class '$whoami'");
                    break;
            }
        }
    }

    /**
     * Fast Forward the stack back to the tag that was interrupted by the artificial 'ChaucerEditorBreak'.
     * 
     * @param array[IdmlElement] $stack
     * @param int $depth
     */
    public function fastForward($stack, $depth)
    {
        $rstack = array_reverse($stack);
        $depth = $depth - count($rstack) - 1;
        
        foreach ($rstack as $idmlObj)
        {
            $whoami = get_class($idmlObj);
            $depth++;
            switch ($whoami)
            {
                case 'IdmlTextFrame':
                    //$this->visitTextFrame($idmlObj, $depth);
                    break;
                case 'IdmlStory':
                    $this->visitStory($idmlObj, $depth);
                    break;
                case 'IdmlParagraphRange':
                    // Fast forward only if the paragraph is not a list
                    if ($idmlObj->listType == 'NoList')
                    {
                        $this->visitParagraphRange($idmlObj, $depth);
                    }
                    break;
                case 'IdmlCharacterRange':
                    // Fast forward only if the closest paragraph ancestor is not a list
                    $paragraph = $this->getAncestor($idmlObj, 'IdmlParagraphRange');
                    if (!is_null($paragraph) && $paragraph->listType == 'NoList')
                    {
                        $this->visitCharacterRange($idmlObj, $depth);
                    }
                    break;
                case 'IdmlGroup':
//                    $this->visitGroup($idmlObj, $depth);
                    break;
                case 'IdmlRectangle':
                    $this->visitRectangle($idmlObj, $depth);
                    break;
                case 'IdmlTable':
                    $this->visitTable($idmlObj, $depth);
                    break;
                case 'IdmlTableRow':
                    $this->visitTableRow($idmlObj, $depth);
                    break;
                case 'IdmlTableColumn':
                    $this->visitTableColumn($idmlObj, $depth);
                    break;
                case 'IdmlTableCell':
                    $this->visitTableCell($idmlObj, $depth);
                    break;
                case 'IdmlXmlElement':
                    $this->visitXmlElement($idmlObj, $depth);
                    break;
                case 'IdmlHyperlink':
                    $this->visitHyperlink($idmlObj, $depth);
                    break;

                case 'IdmlImage':
                case 'IdmlContent':
                case 'IdmlBrContent':
                    // these don't enclose anything and should not have children
                    break;

                case 'IdmlAssembler':
                case 'IdmlPackage';
                case 'IdmlSpread':
                case 'IdmlPage':
                    // these do not produce HTML
                    break;

                default:
                    error_log( "IdmlHtmlProducer::fastForward() encountered an unexpected visitor class '$whoami'");
                    break;
            }
        }
    }

    /** 
     * The remaining visitors do not need to be called
     */
    public function visitAssemblerEnd(IdmlAssembler $item, $depth = 0){}
    public function visitPackage(IdmlPackage $item, $depth = 0){}
    public function visitTags(IdmlTags $item, $depth = 0){}
    public function visitSpread(IdmlSpread $item, $depth = 0){}
    public function visitSpreadEnd(IdmlSpread $item, $depth = 0){}
    public function visitStory(IdmlStory $item, $depth = 0){}
    public function visitStoryEnd(IdmlStory $item, $depth = 0){}
    public function visitRectangle(IdmlRectangle $element, $depth = 0){}
    public function visitRectangleEnd(IdmlRectangle $element, $depth = 0){}
    public function visitTableColumn(IdmlTableColumn $element, $depth = 0){}
    public function visitTableColumnEnd(IdmlTableColumn $element, $depth = 0){}
    public function visitChange(IdmlChange $element, $depth = 0){}
    public function visitChangeEnd(IdmlChange $element, $depth = 0){}

    
    /**
     * Implement these five in IdmlProduceReflowable and IdmlProduceFixedLayout, not here in the base class.
     *  
     * Declare them here so that PHP doesn't produce the fatal error "Class IdmlProduceHtml contains 5 abstract methods
     *  and must therefore be declared abstract or implement the remaining methods"
     *
     * TESTING NOTE: These five methods cannot be unit tested without reflection methods:
     *   This class is abstract, so can't be instantiated, and the child classes do not call the parent.
     */
    public function visitPage(IdmlPage $item, $depth = 0)
    {
    }
    public function visitPageEnd(IdmlPage $page, $depth = 0)
    {
    }
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0)
    {
    }
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0)
    {
    }
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
    }

    // Getter/setter functions for unit testing
    public function getPageNumber()
    {
        return $this->pageNumber;
    }

    public function setPageObject($pageObject)
    {
        $this->pageObject = $pageObject;
        $this->pageObject->properties = array();
    }
}
