<?php
/**
 * @package /app/Import/Idml/IdmlProduceHtml.php
 * 
 * @class   IdmlProduceHtml
 * 
 * @description Base class for IdmlProduceReflowable (non_PXE), and IdmlProduceFixedLayout (non-PXE)
 * *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlParserHelper',       'Import/Idml');
App::uses('IdmlProduceHtmlBase',    'Import/Idml/Html');
App::uses('IdmlSvgStyleManager',    'Import/Idml/Styles');


abstract class IdmlProduceHtml extends IdmlProduceHtmlBase
{
    /**
     * @var $currHyperLink contains the contents of a hyperlink, which is added to the page in visitContent
     */
    protected $currHyperlink;

    /**
     * @var $currentPageUID contains the UID of either the current IdmlPage (fixed) or the current IdmlTextFrame (reflowable)
     * This is only used if the last page processed has no Br elements and must be force closed at the end of production.
     */
    public $currentPageUID;

    /**
     * $appliedNumberingData - IDML uses the AppliedNumberingList property to identify lists whose numbering
     * should persist between lists. This array stores the current position for each of those properties.
     * $currentNumberingList stores the name of the list in progress for easy reference.
     */
    protected $appliedNumberingData = array();
    protected $numberingLevel;
    protected $currentNumberingList;

    /**
     * The next three variables manage columnar layout.
     * $columnBreak represents a condition set by the character range and acted upon by the paragraph range.
     * $textColumnCount is the number of columns. This is important: if it is 1, columnization should be ignored
     * $columnWidth is set by the text frame and acted upon by the paragraph range.
     */
    protected $columnBreak = false;
    protected $textColumnCount = 0;
    protected $columnWidth = 0;

    /**
     * Constructor.
     * @var integer $currentRecursivePageNumber since this class is used recursively, we need to keep track
     *              of which page we are on when we construct a new instance. Use 1 for the outermost instance.
     */
    public function __construct($currentRecursivePageNumber = 1)
    {
        parent::__construct($currentRecursivePageNumber);
        $this->initData();
    }

    /**
     * Initializes all data for storing list information
     */
    public function initData()
    {
        $this->appliedNumberingData = array();
        $this->currHyperlink = null;
    }

    /**
     * @param IdmlElement $element
     * @param string $class
     * @return boolean
     */
    private function hasDescendant(IdmlElement $element, $class)
    {
        if (is_null($element->childrenElements) || count($element->childrenElements) == 0)
        {
            return false;
        }

        foreach ($element->childrenElements as $child)
        {
            if (is_a($child, $class))
            {
                return true;
            }

            if ($this->hasDescendant($child, $class))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Overrides superclass method, which prepends 'chauc-' to id attribute, and does nothing
     * @param $element
     */
    public function verifyElementId($element){}

    /**
     * Finds the value associated with the style key, either within the Story element itself or in DeclaredStyles.
     * Returns style value string, or null if no style match is found.
     * @param IdmlElement $element
     * @param string $styleKey
     * @param string $default
     * @return null
     */
    protected function getStyleByKey(IdmlElement $element, $styleKey, $default = null)
    {
        $style = isset($element->contextualStyle->idmlKeyValues[$styleKey]) ? $element->contextualStyle->idmlKeyValues[$styleKey]: null;

        if (is_null($style))
        {
            $styleValue = $this->getDeclaredStyle($element, $styleKey);

            if (!is_null($styleValue))
            {
                $style = $styleValue;
            }
        }
        if (is_null($style))
        {
            $style = $default;
        }
        return $style;
    }

    /**
     * Finds the IdmlDeclaredStyle value for the specified IDML element, tag type, and key value
     * @param IdmlElement $element
     * @param string $styleKey
     * @return null
     */
    protected function getDeclaredStyle(IdmlElement $element, $styleKey)
    {
        $appliedStyle = $element->appliedStyleName;
        $declaredStyle = $this->declarationMgr->declaredStyles[$appliedStyle];

        if (isset($declaredStyle->idmlKeyValues[$styleKey]))
        {
            $styleValue = $declaredStyle->idmlKeyValues[$styleKey];
        }
        else
        {
            $styleValue = null;
        }
        return $styleValue;
    }

    /**
     * The getTransformationCSS function should return a CSS declaration for the element's rotation or skew.
     * @param IdmlElement $element
     * @return string
     */
    public function getTransformationCSS(IdmlElement $element)
    {
        switch( get_class($element) )
        {
            case 'IdmlTextFrame':
            case 'IdmlRectangle':
            case 'IdmlGroup':
                $a = $element->transformation->getA();
                $b = $element->transformation->getB();
                $c = $element->transformation->getC();
                $d = $element->transformation->getD();

                // If the element is embedded and has been rotated, we need to create a containing div with the elements new dimensions.
                if ($element->isEmbedded() && ($b != 0 || $c != 0))
                {
                    $this->offsetTransform($element);
                }

                if ($element->transformation->isIdentity() || $element->isEmbedded())
                {
                    $origin = 'center center';
                }
                else
                {
//                    $origin = 'left top';
                    $origin = 'center center';
                }

                if ($a != 1 || $b != 0 || $c != 0 || $d != 1)
                    return sprintf('-webkit-transform-origin: ' . $origin . '; -webkit-transform: matrix(%f,%f,%f,%f,0,0);', $a, $b, $c, $d);

            default:
                return '';
        }
    }

    /**
     * Adds a vertical offset to the page to compensate for an element with a transformation
     * @param IdmlElement $element
     * @TODO - this needs to be moved to the parent!
     */
    private function offsetTransform($element)
    {
//        // First close any open spans
//        for ($i = $this->numberOfOpenElements('span', 'div'); $i>0; $i--)
//        {
//            $this->addPageElement('</span>', 0);
//        }
//
//        $element->rotationOffset = IdmlBoundary::getOffsetHeight($element);
//        $this->addPageElement('<div style="height:' . $element->rotationOffset . 'px;">', 0);
//        $this->addPageElement('</div>', 0);
    }

    /**
     * Returns the CSS transformation string for an svg element, or one of its children
     * The svg tag must have the $b and $c elements assigned like any other transformation.
     * But the children are already rotated, so the $b and $c values need to be 0.
     * @param IdmlElement $element
     * @param $tag
     * @return string
     */
    protected function getSvgTransformationCSS(IdmlElement $element, $tag)
    {

        if ($tag == 'svg')
        {
            $a = $element->transformation->getA();
            $b = $element->transformation->getB();
            $c = $element->transformation->getC();
            $d = $element->transformation->getD();
        }
        else  // polygon or oval
        {
            // In testing, we determined that we need to assign the default transform CSS, event though theoretically it shouldn't be needed.
            $a = 1;
            $b = 0;
            $c = 0;
            $d = 1;
        };

//        $origin = ($element->transformation->isIdentity()) ? 'center center' : 'left top';
        $origin = 'center center';

        return sprintf('-webkit-transform-origin: ' . $origin . '; -webkit-transform: matrix(%f,%f,%f,%f,0,0);', $a, $b, $c, $d);
    }

    /**
     * Flush all remaining elements when we reach the end of each package.
     * Recall that a "package" is our name for a single InDesign document.
     * This means that if the user has not specified any 'ChaucerEditorBreak' tags,
     * the output will all be in a single ePub chapter.
     * NOTE: This logic is overridden in fixed layout, where the end of the page is determined by the page element.
     */
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
        if ($this->pageElements && is_array($this->pageElements) && count($this->pageElements)>0)
        {
            $pageFilename = $this->savePage();

            $this->pageNameXref[$this->pageObject->UID] = $pageFilename . '.xhtml';

            $this->clearPage();
        }
    }

    /**
     * Nothing needs to be done here except update the progress; pages are terminated elsewhere.
     * @param IdmlPage $item
     * @param int $depth
     */
    public function visitPage(IdmlPage $item, $depth = 0)
    {
        // This progress indicator is simply for user feedback, it has nothing to do with book construction or page breaks.
        $progressUpdater = IdmlAssembler::getProgressUpdater();
        if ($progressUpdater)
        {
            $progressUpdater->incrementStep();
        }
    }

    /**
     * Visit page end has no special meaning in non-PXE books
     */
    public function visitPageEnd(IdmlPage $page, $depth = 0)
    {
    }

    /**
     * This method is a failsafe for when a content unit (page in fixed or story in reflowable)
     *   ends without closing the final paragraph or list.
     */
    public function closeFinalElements($depth)
    {
        // Failsafe tag closing. Driven by produced edge case.
        $divsToClose = $this->numberOfOpenElements('div', 'body');

        while ($divsToClose > 0)
        {
            // First close any open spans inside the div
            $this->closeOpenSpans('div', $depth);

            $this->addPageElement('</div>', $depth);
            $divsToClose--;
        }
    }

    /**
     * Calls parent class's function to visit text frame, then manages the special case of a text frame with columns.
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    public function visitTextFrame(IdmlTextFrame $element, $depth = 0)
    {
        parent::visitTextFrame($element, $depth);

        // If this is a multi-column text frame, set up the opening div
        if (isset($element->textColumnCount) && isset($element->columnWidth) && $element->textColumnCount > 1)
        {
            $divHtml = '<div style="width:' . $element->columnWidth . 'px;display:inline-block;vertical-align:top;">';
            $this->addPageElement($divHtml, $depth + 1);

            $this->columnWidth = $element->columnWidth;
        }

        // textColumnCount of 1 indicates we should ignore column breaks, which are set at the character level.
        $this->textColumnCount = $element->textColumnCount;
    }

    /**
     * Manages the special case of a text frame with columns, then calls parent class's function to close the text frame
     * @param IdmlTextFrame $element
     * @param int $depth
     */
    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0)
    {
        // If this is a multi-column text frame, close the final div
        if (isset($element->textColumnCount) && isset($element->columnWidth) && $element->textColumnCount > 1)
        {
            $this->addPageElement('</div>', $depth + 1);
        }

        // Then call the parent class to close the text frame.
        parent::visitTextFrameEnd($element, $depth);
    }

    /**
     * Obtains and stores class and style data. Generating tags is done elsewhere.
     * @param IdmlParagraphRange $element
     * @param int $depth
     */
    public function visitParagraphRange(IdmlParagraphRange $element, $depth = 0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);

        // Get the list type. If the paragraph is a list, it requires special handling.
        $element->listType = $this->getStyleByKey($element, 'BulletsAndNumberingListType', 'NoList');

        if ($element->listType == 'NoList')
        {
            $this->processParagraph($element);
        }
        else
        {
            $this->processList($element);
        }
    }

    /**
     * @param IdmlParagraphRange $element
     */
    protected function processParagraph(IdmlParagraphRange $element)
    {
        $element->attribs['style'] = $this->getStyleAttrib($element);
        $element->tagName = 'div';
    }

    /**
     * @param IdmlParagraphRange $element
     * @param int $depth
     * @return string
     */
    protected function processList(IdmlParagraphRange $element, $depth=0)
    {
        // In order to visually align lists, the text-indent set in IDML must be overridden.
        // Do this by resetting the FirstLineIndent idml key value.
        // This must be done *before* obtaining inline styles
        $element->contextualStyle->idmlKeyValues['FirstLineIndent'] = '0';

        $element->attribs['style'] = $this->getStyleAttrib($element);

        $element->tagName = $this->getListTag($element->listType);

        $this->setListIndentation($element->attribs, $element);

        // Set start attribute and list-style-type style property, for ordered lists only
        if ($element->tagName == 'ol')
        {
            $element->attribs['start'] = $this->getStartAttrib($element);
            $element->start = $element->attribs['start'];
            $element->attribs['style'] .= 'list-style-type:' . $this->getListStyleType($element) . ';';
        }

        // Always start a new list *except* when there's an open <li> tag.
        if ($this->theElementIsClosed('li'))
        {
            $strAttribs = $this->getHTMLAttributesString($element, $element->attribs);
            $html = '<' . $element->tagName . ' ' . $strAttribs . '>';
            $this->addPageElement($html, $depth);
        }
    }

    /**
     * Set the indentation, based on the LeftIndent IDML property
     * The list is pushed right 2 ems for every 18 points specified by the LeftIndent property.
     * Note: default is specified here as 18 points - 0 would push the numbers/bullets off the page.
     * @param array $attribs - array of element's attributes
     * @param IdmlParagraphRange $element - idml paragraph range for this list
     */
    protected function setListIndentation(&$attribs, IdmlParagraphRange $element)
    {
        $rawLeftIndent = (int)$this->getStyleByKey($element, 'LeftIndent', '18');
        $rawLeftIndent = ($rawLeftIndent == 0) ? 18 : $rawLeftIndent;
        $leftIndent = ($rawLeftIndent - 18) / 9;
        $leftIndent += 2;
        $leftIndent = round($leftIndent);

        $attribs['style'] .= 'padding-left:' . ($leftIndent) . 'em;';
    }

    /**
     * @param string $listType - Value of IDML property BulletsAndNumberingListType
     * @return string $listTag - HTML tag name: ol, ul, or p
     * @throws Exception
     */
    protected function getListTag($listType)
    {
        switch ($listType)
        {
            case 'BulletList':
                $listTag = 'ul';
                break;
            case 'NumberedList':
                $listTag = 'ol';
                break;
            case 'NoList':
                $listTag = 'p';
                break;
            default:
                throw new Exception('Invalid list type: ' . $listType);
        }
        return $listTag;
    }

    /**
     * Finds and returns an ordered list's start attribute
     * @param IdmlParagraphRange $element
     * @return int
     */
    protected function getStartAttrib(IdmlParagraphRange $element)
    {
        // Find this paragraph's data: the AppliedNumberingList and the level in the list hierarchy
        $this->currentNumberingList = $this->getStyleByKey($element, 'Properties::AppliedNumberingList', 'current');
        $this->numberingLevel = (int) $this->getStyleByKey($element, 'NumberingLevel', '1');

        // By default, numbering continues with the next level. But if NumberingContinue is set to 'false' or
        // this AppliedNumberingList has not yet been used at this level in the hierarchy, we start at 1.

        /*
         * The rules for determining the start number is as follows:
         *
         *     1. If NumberingContinue is false and NumberingStartAt is set, use NumberingStartAt
         *     2. If NumberingContinue is false and NumberingStartAt is not set, start at 1
         *     3. If NumberingContinue is true and there's an existing list with the same style and level, continue that list
         *     4. If NumberingContinue is true but no such list exists, start at 1
         *
         * Case 3 uses data values that are already set.
         */

        // If NumberingContinue is 'false' we must either using the 'NumberingStartAt' value or start the list over at 1.
        if ($this->getStyleByKey($element, 'NumberingContinue', 'true') == 'false')
        {
            // If the NumberingStartAt attribute was set, that overrides all other considerations:
            $startNumber = $this->getStyleByKey($element, 'NumberingStartAt', null);
            if (!is_null($startNumber))
            {
                $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList] = $startNumber;
            }
            else
            {
                $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList] = 1;
            }
        }

        // If NumberingContinue is 'true' but this list has not yet been used at this level, initialize position to 1.
        elseif (!isset($this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList]))
        {
            $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList] = 1;
        }

        // Now purge all lower level position data. Numbering always starts over at 1 after any item in a higher level.
        foreach ($this->appliedNumberingData as $level => $list)
        {
            if ($level > $this->numberingLevel)
            {
                unset($this->appliedNumberingData[$level]);
            }
        }

        // Return start attribute value.
        return $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList];
    }

    /**
     * Returns the CSS list-style-type property value based on the paragraph's numbering format property
     * @param IdmlParagraphRange $element
     * @return string $marker - CSS equivalent of list's IDML numbering format
     */
    protected function getListStyleType(IdmlParagraphRange $element)
    {
        $idmlListType = $this->getStyleByKey($element, 'Properties::NumberingFormat');

        $listTypeChar = (string) substr($idmlListType,0,strpos($idmlListType, ','));

        // PHP will convert '01' and '1' to ints in a switch, so this test must precede the switch.
        if ($listTypeChar === '01')
        {
            return 'decimal-leading-zero';
        }

        switch ($listTypeChar)
        {
            case '1':
                $marker = 'decimal';
                break;
            case 'a':
                $marker = 'lower-latin';
                break;
            case 'A':
                $marker = 'upper-latin';
                break;
            case 'I':
                $marker = 'upper-roman';
                break;
            case 'i':
                $marker = 'lower-roman';
                break;
            default:
                $marker = 'decimal';
                break;
        }

        return $marker;
    }

    /**
     * If the paragraph range is a list, close it.
     * Paragraphs are closed by IDML br tags, which close list items but not the ol or ul.
     * @param IdmlParagraphRange $element
     * @param int $depth
     */
    public function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0)
    {
        // Close only list tags. Paragraph tags s/b closed as needed in visitBrContent.
        // Also close any open li and span tag.
        if ($element->listType != 'NoList')
        {
            if ($this->theElementIsOpen('li'))
            {
                $this->closeOpenSpans('li', $depth);
                $this->addPageElement('</li>', $depth);
            }

            $html = '</' . $element->tagName . '>';
            $this->addPageElement($html, $depth);
        }

        //  But also manage the edge case where a paragraph range contains no idml br elements...
        if ($this->theElementIsOpen('div', $element->getCssClassname(), true))
        {
            // If the paragraph uses tab stops, initialize the data
            if ($element->tabSpansToClose > 0)
            {
                $element->initTabData();
            }

            $this->closeOpenSpans('div', $depth);
            $this->addPageElement('</div>', $depth);
        }

        // Finally, if a column break is indicated, start a new div (after closing the current one).
        if ($this->columnBreak)
        {
            $this->addPageElement('</div>', $depth);

            $divHtml = '<div style="width:' . $this->columnWidth . 'px;display:inline-block;vertical-align:top;">';
            $this->addPageElement($divHtml, $depth + 1);

            $this->columnBreak = false;
        }
    }

    /**
     * Store class and style data. Tags are generated later by content elements.
     * @param IdmlCharacterRange $element
     * @param int $depth
     */
    public function visitCharacterRange(IdmlCharacterRange $element, $depth = 0)
    {
        // Start a new column, if that's indicated, by closing and reopening the <div>
        if ($element->paragraphBreakType == 'NextColumn' && $this->textColumnCount > 1)
        {
            $this->columnBreak = true;
        }

        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);

        if ($element->hasInlinePositionedChild())
        {
            $element->attribs['style'] .= 'position:relative;';
        }
    }

    /**
     * This method does nothing; it simply meets the requirements of the abstract parent class that the method exist.
     * @param IdmlCharacterRange $element
     * @param int $depth
     */
    public function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0)
    {
    }

    /**
     * Hyperlink data is saved in the object, and written by the content.
     * @param IdmlHyperlink $element
     * @param int $depth
     */
    public function visitHyperlink(IdmlHyperlink $element, $depth = 0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }

        $element->attribs['href'] = $this->setHyperlinkHref($element);

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $this->currHyperlink = '<a ' . $strAttr . '>';
    }

    /**
     * Generates the href attribute of the hyperlink
     * @param IdmlHyperlink $element
     * @return string $href - href attribute value
     */
    protected function setHyperlinkHref(IdmlHyperlink $element)
    {
        // Get the destination data from the hyperlink manager
        $hyperlinkMgr = IdmlHyperlinkManager::getInstance();
        $destinationData = $hyperlinkMgr->getSourceDestination($element->contextualStyle->idmlKeyValues['Self']);

        // If the anchor is not set, use the destination in the retrieved data.
        if(!$destinationData['anchor'])
        {
            $href = $destinationData['destination'];
        }

        // Otherwise, jump through hoops...
        else
        {
            // $uidArray contains stored page and text frame UIDs; one will be used for the destination filename.
            $uidArray = $hyperlinkMgr->getDestinationPages($destinationData['destination']);

            // $destinationUID contains the appropriate id for fixed (page) or reflowable (text frame);
            // getHyperlinkPage is coded in both fixed and reflowable producers
            $destinationUID = $this->getHyperlinkPage($uidArray);

            // Assemble the full href, using both filename and anchor.
            $href = $destinationUID . '#' . $destinationData['destination'];
        }

        return $href;
    }

    /**
     * Close the hyperlink, but only if it's still open: a br tag can also close a hyperlink before the ending idml tag is encountered.
     * @param IdmlHyperlink $element
     * @param int $depth
     */
    public function visitHyperlinkEnd(IdmlHyperlink $element, $depth = 0)
    {
        if ($this->theElementIsOpen('a'))
        {
            $this->addPageElement("</a>", $depth);
        }
    }

    /**
     * Produce code for an anchor (<a> tag with id attribute)
     * @param IdmlHyperlinkDestination $element
     * @param int $depth
     */
    public function visitHyperlinkDestination(IdmlHyperlinkDestination $element, $depth = 0)
    {
        $element->attribs['id'] = $element->id;
        $element->attribs['name'] = $element->id;

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);

        // Separate page elements are needed in order to find open tags
        $this->addPageElement('<a ' . $strAttr . '>', $depth);
        $this->addPageElement('</a>', $depth);
    }

    /**
     * If a rectangle is *not* inside an svg element, it is represented by a span, and always styled as display inline-block.
     * However, it must always be enclosed in a block tag (like a <p>)
     *   in order to manage cases where rectangles and content are contained within the same paragraph range.
     * If a rectangle *is* inside an svg element, it must be represented by a rect svg element, due to incompatibilities between svg and xml.
     * @param IdmlRectangle $element
     * @param int $depth
     */
    public function visitRectangle(IdmlRectangle $element, $depth = 0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
        $element->attribs['style'] .= ' display:inline-block; overflow:hidden;';

        if (is_null($this->currSvgUID))
        {
            $element->idmlTag = 'span';

            $this->openEnclosingTags($element, false, false, $depth);

            $this->verifyElementId($element);
            if (!empty($element->idAttribute))
            {
                $element->attribs['id'] = $element->idAttribute;
            }
        }
        else
        {
            $element->idmlTag = 'rect';

            $element->attribs['width'] = round($element->boundary->getWidth());
            $element->attribs['height'] = round($element->boundary->getHeight());

            list($element->attribs['x'], $element->attribs['y']) = $this->getElementPosition($element);

            // Get the fill and stroke information, which is a different syntax than other HTML elements
            $svgStyleMgr = new IdmlSvgStyleManager();
            $element->attribs['style'] .= $svgStyleMgr->convertSvgStyles($element);
        }

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<' . $element->idmlTag . ' ' . $strAttr . '>';
        $this->addPageElement($html, $depth);
    }

    /**
     * @param IdmlRectangle $element
     * @param int $depth
     */
    public function visitRectangleEnd(IdmlRectangle $element, $depth = 0)
    {
        $this->addPageElement('</' . $element->idmlTag . '>', $depth);

        if (!is_null($element->rotationOffset))
        {
            $this->addPageElement('<div style="height:' . $element->rotationOffset . 'px;">', 0);
            $this->addPageElement('</div>', 0);
            $element->rotationOffset = null;
        }
    }

    /**
     * @param IdmlGraphicLine $element
     * @param int $depth
     */
    public function visitGraphicLine(IdmlGraphicLine $element, $depth = 0)
    {
        $this->openSvgElement($element, 1, $depth);

        $element->attribs = array_merge($element->attribs, $element->getAnchorPoints());

        $this->addSvgContent($element, 'line', $depth);
    }

    /**
     * @param IdmlPolygon $element
     * @param int $depth
     */
    public function visitPolygon(IdmlPolygon $element, $depth = 0)
    {
        $this->openSvgElement($element, 2, $depth);

        // Set the tag name and the attribute defining the object's points
        $pathOpen = $this->getStyleByKey($element, 'Properties::PathGeometry::GeometryPathType->PathOpen', 'true');
        $strokeWidth = $this->getStyleByKey($element, 'StrokeWeight', 0);

        if ($pathOpen == 'true')
        {
            $tagName = 'path';
            $element->attribs['d'] = $element->getPathPoints();
        }
        else
        {
            $tagName = 'polygon';
            $element->attribs['points'] = $element->getVertexPoints($strokeWidth);
        }

        $this->addSvgContent($element, $tagName, $depth);
    }

    /**
     * @param IdmlOval $element
     * @param int $depth
     */
    public function visitOval(IdmlOval $element, $depth = 0)
    {
        $this->openSvgElement($element, 2, $depth);

        // Set up the oval tag to go inside the svg tag
        $element->attribs['cx'] = ($element->boundary->getWidth() / 2);
        $element->attribs['cy'] = ($element->boundary->getHeight() / 2);
        $element->attribs['rx'] = $element->boundary->getWidth() / 2;
        $element->attribs['ry'] = $element->boundary->getHeight() / 2;

        $this->addSvgContent($element, 'ellipse', $depth);
    }

    /**
     * Create the opening svg tag to contain a graphic line, oval, or polygon
     * @param IdmlSvgShape $element
     * @param int $dimensions
     * @param int $depth
     */
    protected function openSvgElement(IdmlSvgShape $element, $dimensions, $depth=0)
    {
        if (get_class($element->parentElement) == 'IdmlCharacterRange')
        {
            $this->openEnclosingTags($element, true, true, $depth);
        }

        $strokeWidth = $this->getStyleByKey($element, 'StrokeWeight', 0);

        $svgAttribs['height'] = max($element->boundary->getHeight() + ($dimensions * $strokeWidth), 1);
        $svgAttribs['width'] = max($element->boundary->getWidth() + ($dimensions * $strokeWidth), 1);

        $svgAttribs['style'] = $this->getStyleAttrib($element);
        $svgAttribs['style'] .= $this->getSvgTransformationCSS($element, 'svg');

        // Add attributes to the svg object for use by child elements, then continue managing parent svg styles
        $element->attribs = array_merge($element->attribs, $svgAttribs);

        // Since Bezier curvature may cause the curve to overflow the svg box, we need to make that overflow is visible
        if ($dimensions > 1)
        {
            $svgAttribs['style'] .= 'overflow:visible;';
        }

        $svgAttribs['xmlns'] = 'http://www.w3.org/2000/svg';
        if ($this->hasDescendant($element, 'IdmlImage'))
        {
            $svgAttribs['xmlns:xlink'] = 'http://www.w3.org/1999/xlink';
        }

        // Not all styles can apply to the parent, so they need to be stripped before creating the parent
        $svgAttribs['style'] = $this->stripParentSvgStyles($svgAttribs['style']);

        $svgStrAttr = $this->getHTMLAttributesString($element, $svgAttribs);
        $this->addPageElement('<svg ' . $svgStrAttr . '>', $depth);

        // Set the current svg UID to this element's UID to indicate standard HTML must be namespaced.
        if (is_null($this->currSvgUID))
        {
            $this->currSvgUID = $element->UID;
        }

        // Add 'svg' to the page's properties, used in the item tag in the manifest
        $this->pageObject->properties[] = 'svg';
    }

    /**
     * Strips certain values from the string of styles to be applied to an svg parent element
     * @param string $styleString
     * @return string
     */
    private function stripParentSvgStyles($styleString)
    {
        $stylesToBeRemoved = array('background-color', 'border', 'box-shadow', 'opacity');
        $adjustedStyleString = '';

        $styles = explode(';', $styleString);

        foreach ($styles as $style)
        {
            if ($style == '') continue;

            $styleName = substr($style, 0, strpos($style, ':'));

            if (in_array(trim($styleName), $stylesToBeRemoved))
            {
                continue;
            }
            $adjustedStyleString .= $style . ';';
        }

        return $adjustedStyleString;
    }

    /**
     * Create the element--line, oval, or polygon--inside the svg element, and close the svg element.
     * @param IdmlSvgShape $element
     * @param string $tagName
     * @param int $depth
     */
    protected function addSvgContent(IdmlSvgShape $element, $tagName, $depth=0)
    {
        $attribs = $element->attribs;

        $attribs['style'] = $this->getStyleAttrib($element);

        if ($tagName != 'line')
        {
            $attribs['style'] .= $this->getSvgTransformationCSS($element, $tagName);
        }

        // Get the fill and stroke information, which is a different syntax than other HTML elements
        $svgStyleMgr = new IdmlSvgStyleManager();
        $attribs['style'] .= $svgStyleMgr->convertSvgStyles($element);

        // Set up and insert the polygon tag
        $strAttr = $this->getHTMLAttributesString($element, $attribs);
        $html = '<' . $tagName . ' ' . $strAttr . ' />';
        $this->addPageElement($html, $depth);

        $this->closeSvgElement($element, $depth);
    }

    /**
     * Closes the current svg element and, if it has no svg element ancestor, set the current svg UID to null.
     * @param IdmlSvgShape $element
     * @param int $depth
     */
    public function closeSvgElement(IdmlSvgShape $element, $depth = 0)
    {
        if ($this->currSvgUID == $element->UID)
        {
            $this->currSvgUID = null;
        }

        $this->addPageElement('</svg>', $depth);
    }

    /**
     * This code copied from IdmlProduceHtmlBase, where it now resides as an abstract method.
     * @param IdmlImage $element
     * @param int $depth
     */
    public function visitImage(IdmlImage $element, $depth = 0)
    {
    /*
        // IDML images have an actual ppi and an effective ppi. Use these properties to determine the CSS width and height.
        $properties = $element->contextualStyle->idmlKeyValues;
        $actualPpi = array_key_exists('ActualPpi', $properties) ? $properties['ActualPpi'] : '72 72';
        list($actualX, $actualY) = explode(' ', $actualPpi);
        $effectivePpi = array_key_exists('EffectivePpi', $properties) ? $properties['EffectivePpi'] : '72 72';
        list($effectiveX, $effectiveY) = explode(' ', $effectivePpi);
        $scaledWidth = round(($actualX / $effectiveX) * $element->width);
        $scaledHeight = round(($actualY / $effectiveY) * $element->height);
*/
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
        // $element->attribs['style'] .= "width:{$scaledWidth}px; height:{$scaledHeight}px;";

        // use the width and height set on the containing rectangle.
        if (is_a($element->parentElement, 'IdmlPolygon'))
        {
            $svgAttribs = $this->getSvgAttribs($element->parentElement);
            // use height - 4, width - 4, top + 2, left + 2
            // OR, Better: make image background transparent
            $element->attribs['height'] = $svgAttribs['height'];
            $element->attribs['width'] = $svgAttribs['width'];
            $element->attribs['style'] .= $svgAttribs['style'];
        }
        else
        {
            $element->attribs['style'] .= "width:100%; height:100%;";
        }

        $element->mediaFilename = str_replace('&', '&amp;', $element->mediaFilename);

        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }


        if (is_null($this->currSvgUID))
        {
            $tagname = 'img';
            $element->attribs['src'] = '../images/' . $element->mediaFilename;
        }
        else
        {
            $element->attribs['width'] = round($element->boundary->getWidth());
            $element->attribs['height'] = round($element->boundary->getHeight());

            list($element->attribs['x'], $element->attribs['y']) = $this->getElementPosition($element);

            // Get the fill and stroke information, which is a different syntax than other HTML elements
            $svgStyleMgr = new IdmlSvgStyleManager();
            $element->attribs['style'] .= $svgStyleMgr->convertSvgStyles($element);

            $element->attribs['xlink:href'] = '../images/' . $element->mediaFilename;
            $tagname = 'image';
        }

        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $html = '<' . $tagname . ' ' . $strAttr . ' />';

        $this->addPageElement($html, $depth);
    }

    /**
     * Called by visitImage, this function generates the height, width, and positioning styles for images embedded within Rectangles
     * @param $element
     * @return array
     */
    protected function getSvgAttribs($element)
    {
        $parentAttribs = $element->attribs;
        $svgAttribs = array();
        $styles = array();

        $svgAttribs['height'] = $parentAttribs['height'];
        $svgAttribs['width'] = $parentAttribs['width'];

        $styleAttribs = explode(';', str_replace(' ','',$parentAttribs['style']));
        foreach($styleAttribs as $style)
        {
            $colonPos = strpos($style, ':');
            $styles[substr($style, 0, $colonPos)] = substr($style, $colonPos+1);
        }

        $svgAttribs['style'] = 'position:absolute;';
        if (isset($styles['z-index'])) $svgAttribs['style'] .=  'z-index:' . $styles['z-index'] . ';';
        if (isset($styles['left'])) $svgAttribs['style'] .=  'left:' . $styles['left'] . ';';
        if (isset($styles['top'])) $svgAttribs['style'] .=  'top:' . $styles['top'] . ';';

        return $svgAttribs;
    }

    public function visitTable(IdmlTable $element, $depth = 0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);

        $this->verifyElementId($element);
        if (!empty($element->idAttribute))
        {
            $element->attribs['id'] = $element->idAttribute;
        }
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);
        $this->addPageElement("<table $strAttr>", $depth);
        $element->idmlTag = 'table';

        //output the column group
        if(is_array($element->colGroupStyles) && count($element->colGroupStyles) > 0)
        {
            $this->addPageElement("<colgroup>", $depth+1);
            foreach ($element->colGroupStyles as $colstyle)
            {
                $this->addPageElement('<col style="' . $colstyle . '" />', $depth+2);
            }
            $this->addPageElement("</colgroup>", $depth+1);
        }
    }

    public function visitTableEnd(IdmlTable $element, $depth = 0)
    {
        $this->addPageElement("</table>", $depth);
    }

    public function visitTableRow(IdmlTableRow $element, $depth = 0)
    {
        if ($element->isFirstRow)
        {
            $this->addPageElement("<$element->rowType>", $depth); // thead, tbody, tfoot
        }

        $element->attribs['style'] = $this->getStyleAttrib($element);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);

        $this->addPageElement( '<tr ' . $strAttr . '>', $depth);
        $element->idmlTag = 'tr';
    }

    public function visitTableRowEnd(IdmlTableRow $element, $depth = 0)
    {
        $this->addPageElement("</tr>", $depth);
        if ($element->isLastRow)
        {
            $this->addPageElement("</$element->rowType>", $depth); // thead, tbody, tfoot
        }
    }

    /**
     * @param IdmlTableCell $element
     * @param int $depth
     * @throws Exception
     */
    public function visitTableCell(IdmlTableCell $element, $depth = 0)
    {
        $element->attribs['class'] = $this->getCssClassname($element);
        $element->attribs['style'] = $this->getStyleAttrib($element);
        $strAttr = $this->getHTMLAttributesString($element, $element->attribs);

        $span = '';
        if ($element->rowspan == 0 || $element->rowspan > 1)
        {
            $span .= 'rowspan="' . $element->rowspan .'"';       // rowspan of 0 tells the browser to span the cell to the last row
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
            throw new Exception("[IdmlProduceHtml::visitTableCell] parent element is not a TableRow ");
        }
    }

    public function visitTableCellEnd(IdmlTableCell $element, $depth = 0)
    {
        if ($this->theElementIsOpen('div'))
        {
            $this->addPageElement('</span>', $depth);
            $this->addPageElement('</div>', $depth);
        }

        $this->addPageElement('</' . $element->idmlTag . '>', $depth);
    }

    public function visitTableColumn(IdmlTableColumn $element, $depth = 0){}
    public function visitTableColumnEnd(IdmlTableColumn $element, $depth = 0){}

    /**
     * A content element in IDML indicates the start of a new span.
     * This method manages creating the new span tag; text content is handled by child elements.
     * It also handles use cases where prior tags need to be closed.
     * @param IdmlContent $element
     * @param int $depth
     * @throws Exception
     */
    public function visitContent(IdmlContent $element, $depth = 0)
    {
        $this->openEnclosingTags($element, true, true, $depth);
    }

    /**
     * This method manages opening of enclosing content (<p>, <span>, and list tags).
     * It is called by any page element which creates content, a list which will continue to grow:
     * Called by visitContent, visitRectangle, visitTextVariableInstance
     * @param IdmlElement $element
     * @param boolean $needsAncestors
     * @param boolean $usesAttribs
     * @param int $depth
     * @return void
     * @throws Exception
     */
    protected function openEnclosingTags(IdmlElement $element, $needsAncestors, $usesAttribs, $depth)
    {
        // Get the enclosing tag data (list or paragraph)
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');

        if (is_null($paragraph))
        {
            // Some elements (e.g. Character Range) must have ancestors, so we should throw an Exception for them.
            if ($needsAncestors) throw new Exception('Content tag has no paragraph ancestor. Contact tech support');

            // Others (e.g. Rectangle) do not need ancestors, but without ancestors the remainder of this method does not apply.
            return;
        }

        // Assign the tag name of the enclosing tag and its attributes.
        if ($paragraph->listType == 'NoList')
        {
            $enclosingTag = 'div';
            $enclosingAttribs = $paragraph->attribs;
            $includedContent = $paragraph->getCssClassname();
            $parseFullDoc = true;
        }
        else
        {
            $enclosingTag = 'li';
            $enclosingAttribs = array();
            $includedContent = null;
            $parseFullDoc = false;
        }

        if ($this->theElementIsOpen($enclosingTag, $includedContent, $parseFullDoc))
        {
            $this->closeOpenSpans($enclosingTag, $depth);
        }
        else
        {
            // We need to open a new enclosing element, with the appropriate attributes.
            $openingTag = '<' . $enclosingTag . ' ' . $this->convertAttribsToString($enclosingAttribs) . '>';
            $this->addPageElement($openingTag, $depth);
        }

        // If the paragraph's first tab hasn't been processed, insert the opening tab span in fixed layout content.
        $this->processFirstTab($paragraph);

        // If the element will be a span and it's positioned inline, add a wrapping span with position:relative as an anchor
        $this->addPositionWrapper($element, $depth);

        // Rectangles write a span to the page, so don't need the additional span
        // Groups' containing elements write spans if required.
        if (!is_a($element, 'IdmlRectangle') && !is_a($element, 'IdmlGroup'))
        {
            $this->writeWrappingSpan($element, $usesAttribs, $depth);
        }

        // Create an opening hyperlink tag if one is present.
        if (!is_null($this->currHyperlink))
        {
            // create hyperlink and unset object property
            $this->addPageElement($this->currHyperlink, $depth);
            $this->currHyperlink = null;
        }
    }

    /**
     * If the element is positioned inline, create a wrapper span to which it will be anchored
     * @param IdmlElement $element
     * @param int $depth=0
     */
    protected function addPositionWrapper(IdmlElement $element, $depth=0)
    {
        $anchoredPosition = isset($element->contextualStyle->idmlKeyValues['AnchoredObjectSetting->AnchoredPosition']) ?
            $element->contextualStyle->idmlKeyValues['AnchoredObjectSetting->AnchoredPosition'] : '';

        if ($anchoredPosition == 'InlinePosition')
        {
            // svg elements have no width and height styles, so we need to handle them differently
            if (isset($element->attribs['style']))
            {
                $styles = $this->unpackStyles($element->attribs['style']);
                $width = (isset($styles['width'])) ? 'width:' . $styles['width'] . ';' : '';
                $height = (isset($styles['height'])) ? 'height:' . $styles['height'] . ';' : '';
            }
            else
            {
                $dimensions =  (get_class($element) == 'IdmlGraphicLine') ? 1 : 2;
                $strokeWidth = $this->getStyleByKey($element, 'StrokeWeight', 0);
                $width = max($element->boundary->getWidth() + ($dimensions * $strokeWidth), 1);
                $height = max($element->boundary->getHeight() + ($dimensions * $strokeWidth), 1);
            }

            $html = '<span style="position:relative;display:inline-block;';

            $html .= 'width:' . $width . 'px;';
            $html .= 'height:' . $height . 'px;';

            $html .= '">';

            $this->addPageElement($html, $depth);
        }
    }

    /**
     * Takes a semicolon delimited string of name/value style pairs and converts it to a single 2D array
     * @param string $styleString
     * @return array $styleArray
     */
    private function unpackStyles($styleString)
    {
        $styleList = explode(';', $styleString);
        $styleArray = array();

        foreach ($styleList as $style)
        {
            if (strpos($style,':') !== false)
            {
                list($name, $value) = explode(':', $style);
                $styleArray[trim($name)] = trim($value);
            }
        }

        return $styleArray;
    }

    /**
     * Assembles and writes a span tag wrapping content, based on current character range
     * @param IdmlElement $element
     * @param bool $usesAttribs
     * @param int $depth
     * @throws Exception
     */
    private function writeWrappingSpan(IdmlElement $element, $usesAttribs, $depth=0)
    {
        // Find the element's containing character range
        $charRange = $this->getAncestor($element, 'IdmlCharacterRange');
        if (is_null($charRange)) throw new Exception('Content tag has no character range ancestor. Contact tech support');

        // Assemble the span tag
        if ($usesAttribs)
        {
            $tagHtml = '<span ' . $this->convertAttribsToString($charRange->attribs) . '>';
        }
        elseif ($charRange->hasInlinePositionedChild())
        {
            $tagHtml = '<span style="position:relative">';
        }
        else
        {
            $tagHtml = '<span>';
        }

        // Now open the new span.
        $this->addPageElement($tagHtml, $depth);
    }

    /**
     * This method manages special characters and adds text content to the page.
     * It is also responsible for managing nested styles in the ancestor paragraph's applied style
     * @param IdmlText $element
     * @param int $depth
     */
    public function visitText(IdmlText $element, $depth = 0)
    {
        $this->addTextContent($element, $element->content, $depth);
    }

    /**
     * This method adds text from an InDesign TextVariableInstance node.
     * Since it contained in a Character Range (not a Content node), we must manage wrapping tags (p's and spans)
     * @param IdmlTextVariableInstance $element
     * @param int $depth
     */
    public function visitTextVariableInstance(IdmlTextVariableInstance $element, $depth = 0)
    {
        if (get_class($element->parentElement) == 'IdmlCharacterRange')
        {
            $this->openEnclosingTags($element, false, false, $depth);
        }

        $this->addTextContent($element, $element->content, $depth);
    }

    /**
     * This function manages adding text content from text nodes and text variable nodes to the page
     * It replaces control characters and considers the case of nested styles
     * @param IdmlElement $element
     * @param string $content - usually the element's content, but for nested styles can be a substring
     * @param int $depth
     */
    public function addTextContent(IdmlElement $element, $content, $depth)
    {
        /*  The only escapes performed are:
                '&' (ampersand) becomes '&amp;'
                '<' (less than) becomes '&lt;'
                '>' (greater than) becomes '&gt;'
         */
        $escaped = htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8', false);

        $textContent = $this->replaceControlChars($escaped);

        // Add the content
        if ($textContent)
        {
            $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');

            if (is_a($paragraph, 'IdmlParagraphRange') && $paragraph->hasNestedStyle)
            {
                $currNestedStyle = $this->getCurrentNestedStyles($paragraph->nestedStyleHelpers);

                if (!is_null($currNestedStyle))
                {
                    $currNestedStyle->processContent($textContent, $element, $this, $depth);
                    return;
                }
            }

            // No nested styles to process; render without additional character style
            $textContent = str_replace("\xe2\x80\xa8", '<br />', $textContent);
            $this->addPageElement($textContent, $depth);
        }
    }

    /**
     * Obtains the nested style that should be used for styling the current content.
     * If what would be the current style has a 'Repeat' delimiter, it backtracks the correct
     * number of styles in the array, resetting all intervening styles for reuse by setting timesfound to zero.
     * If the nested styling is finished, it returns null.
     * @param array $nestedStyles
     * @return null|IdmlNestedStyleHelper $currNestedStyle
     */
    protected function getCurrentNestedStyles($nestedStyles)
    {
        foreach ($nestedStyles as $ndx => $nestedStyle)
        {
            if ($nestedStyle->isActive())
            {
                if ($nestedStyle->getDelimiter() == 'Repeat')
                {
                    $numStylesToRepeat = $nestedStyle->getNumTimes();
                    for ($i=0; $i<$numStylesToRepeat; $i++)
                    {
                        $nestedStyles[$ndx-($i+1)]->resetTimesFound();
                    }
                    return $nestedStyles[$ndx-$numStylesToRepeat];
                }
                else
                {
                    return $nestedStyle;
                }
            }
        }

        // All the styles are completed; return null
        return null;
    }

    /**
     * @param IdmlContent $element
     * @param int $depth
     * @throws Exception
     */
    public function visitContentEnd(IdmlContent $element, $depth = 0)
    {
    }

    /**
     * If a group is inside a list, we must first call openEnclosingTags. Then call the parent's visitGroup.
     * @param IdmlGroup $element
     * @param int $depth
     */
    public function visitGroup(IdmlGroup $element, $depth=0)
    {
        // If the group is inside a paragraph range, we must first open the enclosing tags
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');
        if (!is_null($paragraph))
        {
            $this->openEnclosingTags($element, false, false, $depth);
        }

        parent::visitGroup($element, $depth);
    }

    /**
     * This is a more generic version of getHTMLAttributesString, which requires an Idml element
     * @param array $attribs
     * @return string
     */
    protected function convertAttribsToString($attribs)
    {
        $str = "";

        if (count($attribs) > 0)
        {
            foreach($attribs as $name=>$value)
            {
                if(strlen($value)>0)
                {
                    $str .= sprintf( "%s=\"%s\" ", $name, $value);
                }
            }
        }
        return trim($str);
    }

    /**
     * @param IdmlElement $element
     * @return mixed page|null
     */
    protected function getCurrentPage(IdmlElement $element)
    {
        $searchObject = $element;

        while (isset($searchObject->parentElement))
        {
            if (isset($searchObject->parentElement->page) && is_a($searchObject->parentElement->page, 'IdmlPage'))
            {
                return $searchObject->parentElement->page;
            }

            $searchObject = $searchObject->parentElement;
        }

        return null;
    }

    /**
     * Converts hexadecimal control characters into corresponding html entities.
     * At this time, conversions include only em dash and en dash characters.
     * NOTE: Line feeds need to be kept in the text for nested styles; they are handled elsewhere
     * The method stores the hex characters and the entities in matching arrays, which can be modified independent of executable code.
     * @param string $stringIn - string containing hexadecimal characters to be converted
     * @return string $stringOut - string with hex characters replaced by html entities
     */
    protected function replaceControlChars($stringIn)
    {
        $controlChars = array(
            "\xe2\x80\x93",  // En dash
            "\xe2\x80\x94",  // Em dash
            "  ",            // Two spaces - replace 2nd with non-breaking space so browser doesn't compress it
        );

        $entities = array(
            '&#8211;',      // En dash
            '&#8212;',      // Em dash
            ' &#160;',
        );

        $stringOut = str_replace($controlChars, $entities, $stringIn);

        return $stringOut;
    }

    /**
     * This method returns a boolean indicating whether there is an open tag for the specified tag name.
     * Primarily used in several places to determine if the prior enclosing tag (p or li) was closed,
     * It's also used for detecting open hyperlinks.
     * @param string $enclosingTag - the tag name (p or li) of the possible enclosing tag.
     * @param string $includedContent=null - a string that must be matched in the tag; typically a style
     * @param boolean $parseFullDoc=false - if true, parse entire document (don't stop if a closure is found).
     *        Typically true if the tag can be nested (e.g. a div)
     * @return boolean $open - indicates whether the most recent enclosing tag is open or closed.
     */
    protected function theElementIsOpen($enclosingTag, $includedContent=null, $parseFullDoc=false)
    {
        $pageElements = $this->pageElements;
        $elementIndex = count($pageElements) -1;
        $levelOfClosure = 0;
        $asideCounter = 0;

        // Loop through the elements, last to first, looking for an opening or closing of $enclosingTag.
        // Loop terminates when tag is found, OR when index < 0 (meaning there were no tags found).
        while ($elementIndex >= 0)
        {
            $tag = $pageElements[$elementIndex][0];

            // We're only interested in html tags, not in text content
            if (substr($tag,0,1) != '<')
            {
                $elementIndex--;
                continue;
            }

            // Get the tag name: add a space before the closing '>', then the tag name will be the substring between the 2nd character and the 1st space
            $modifiedTag = str_replace('>', ' >', $tag);
            $tagContent = substr($modifiedTag, 1, (strpos($modifiedTag, ' ') - 1));

            if ($tagContent == '/' . $enclosingTag) // This is a closing tag for the searched element
            {
                if ($parseFullDoc)  // Add to the level of closure and continue
                {
                    $levelOfClosure++;
                }
                else                // Last item was closed: set index = -1 and break.
                {
                    $elementIndex = -1;
                    break;
                }
            }

            // If we're inside an aside and have not found a div, we must consider the current enclosing div unopened
            if ($enclosingTag == 'div')
            {
                if ($tagContent == '/aside')
                {
                    $asideCounter++;
                }
                elseif ($tagContent == 'aside')
                {
                    if ($asideCounter > 0)
                    {
                        $asideCounter--;
                    }
                    else
                    {
                        $elementIndex = -1;
                        break;
                    }
                }
            }

            if ($tagContent == $enclosingTag) // This is an instance of the searched element
            {
                if ($levelOfClosure <= 0 || !$parseFullDoc)   // This is an open instance of the searched element
                {
                    if (is_null($includedContent) || strpos($tag, $includedContent) !== false)
                    {
                        break;
                    }
                    else
                    {
                        $levelOfClosure--;
                    }
                }
                else
                {
                    $levelOfClosure--;
                }
            }

            $elementIndex--;
        }

        $open = ($elementIndex < 0) ? false : true;

        return $open;
    }


    /**
     * Returns the boolean opposite of theElementIsOpen()
     * Used for making the meaning of code more semantically obvious
     * @param string $enclosingTag
     * @param string $includedContent
     * @param boolean $parseFullDoc=false - if true, parse entire document (don't stop if a closure is found).
     *        Typically true if the tag can be nested (e.g. a div)
     * @return bool
     */
    protected function theElementIsClosed($enclosingTag, $includedContent=null, $parseFullDoc=false)
    {
        return !$this->theElementIsOpen($enclosingTag, $includedContent, $parseFullDoc);
    }

    /**
     * An idml <br> will always close the current span and the current list item or paragraph.
     * The outlier case: there is no enclosing element open, which only happens in paragraphs.
     * In that case, create an empty span (with nbsp entity) inside an empty paragraph.
     * @param IdmlBrContent $element
     * @param int $depth
     * @throws Exception
     */
    public function visitBrContent(IdmlBrContent $element, $depth = 0)
    {
        // Assign the tag name of the enclosing tag and its attributes.
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');
        if (is_null($paragraph)) throw new Exception('BrContent tag has no paragraph ancestor. Contact tech support');

        if ($paragraph->listType == 'NoList')
        {
            $enclosingTag = 'div';
            $enclosingAttribs = $paragraph->attribs;
            $includedContent = $paragraph->getCssClassname();
            $parseFullDoc = true;
        }
        else
        {
            $enclosingTag = 'li';
            $enclosingAttribs = array();
            $includedContent = null;
            $parseFullDoc = false;
        }

        // Get the span attributes from object property
        $charRange = $this->getAncestor($element, 'IdmlCharacterRange');
        if (is_null($charRange)) throw new Exception('Br tag has no character range ancestor. Contact tech support');

        $charAttribs = $charRange->attribs;

        // If the enclosing element is not open, we need to open a new enclosing element and span,
        // with the appropriate attributes, and add a non-breaking space entity.
        if ($this->theElementIsClosed($enclosingTag, $includedContent, $parseFullDoc))
        {
            $openingTag = '<' . $enclosingTag . ' ' . $this->convertAttribsToString($enclosingAttribs) . '>';
            $this->addPageElement($openingTag, $depth);

            $openingSpan = '<span ' . $this->convertAttribsToString($charAttribs) . '>';
            $this->addPageElement($openingSpan, $depth);

            $this->addPageElement("&#160;", $depth);
        }

        // If a hyperlink is open, close it
        if ($this->theElementIsOpen('a'))
        {
            $this->addPageElement('</a>', $depth);
        }

        // Now close any existing span and enclosing element
        $this->closeOpenSpans($enclosingTag, $depth);

        $this->addPageElement('</' . $enclosingTag . '>', $depth);

        // Reset paragraph-level settings for tab data and nested styles
        $paragraph->initTabData();
        $paragraph->resetNestedStyles();

        if ($paragraph->listType == 'NumberedList')
        {
            $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList]++;
        }
    }

    /**
     * @param string $enclosingTag - indicates the context in which we're searching
     * @param int $depth=0
     */
    private function closeOpenSpans($enclosingTag, $depth=0)
    {
        for ($i = $this->numberOfOpenElements('span', $enclosingTag); $i>0; $i--)
        {
            $this->addPageElement('</span>', $depth);
        }
    }

    /**
     * This is a special case function for determining how many open spans (if any) must be closed at the end of a paragraph
     * Use case: if $enclosingTag == 'body', the while loop will terminate when $elementIndex == 0
     * @param string $searchTag - the open tag we're searching for
     * @param string $enclosingTag - indicates the context in which we're searching
     * @return int
     * @throws Exception
     */
    private function numberOfOpenElements($searchTag, $enclosingTag)
    {
        $pageElements = $this->pageElements;
        $elementIndex = count($pageElements) -1;
        $numOpenElems = 0;
        $searchLen = strlen($searchTag) + 2; // length of '<' plus tagname plus space
        $enclosingLen = strlen($enclosingTag) + 2; // length of '<' plus tagname plus space

        // Loop through the elements, last to first, tracking opening and closing spans.
        // Loop terminates when the enclosing tag is found--no open search tags should exist beyond there
        //  --or when the beginning of the page element array is reached (see use case in comment block above).
        while ($elementIndex >= 0 && substr($pageElements[$elementIndex][0],0,$enclosingLen) != '<' . $enclosingTag . ' ')
        {
            // When getting the tag, add a space before the closing '>'. That way, '<span>' will get a space inside the tag,
            // We can then determine the tag name by finding the substring up to the first space.
            $tag = str_replace('>', ' >', $pageElements[$elementIndex][0]);

            if (substr($tag, 0, $searchLen) == '<' . $searchTag . ' ')
            {
                $numOpenElems++;
            }

            if (substr($tag, 0, $searchLen+1) == '</' . $searchTag . ' ')
            {
                $numOpenElems--;
            }

            $elementIndex--;
        }

        // If $numOpenElems < 0, it means there are unmatched *closing* elements, which is an error
        if ($numOpenElems < 0)
        {
            throw new Exception('You have an unmatched closing ' . $searchTag . ' on page ' . $this->pageNumber);
        }

        return $numOpenElems;
    }

    /**
     * Setter function for applied numbering lists
     * For unit testing purposes; not currently used elsewhere
     * @param $numberingLevel
     * @param $numberingList
     * @param $position
     */
    public function setAppliedNumberingListElement($numberingLevel, $numberingList, $position)
    {
        $this->appliedNumberingData[$numberingLevel][$numberingList] = $position;
        $this->currentNumberingList = $numberingList;
        $this->numberingLevel = $numberingLevel;
    }

    /**
     * Getter function for applied numbering lists
     * For unit testing purposes; not currently used elsewhere
     * @return array
     */
    public function getAppliedNumberingListElement()
    {
        return $this->appliedNumberingData;
    }

    /**
     * Getter function used in unit testing
     * @return string
     */
    public function getCurrHyperlink()
    {
        return $this->currHyperlink;
    }

    /**
     * Setter function used in unit testing
     * @param string $tag
     */
    public function setCurrHyperlink($tag)
    {
        $this->currHyperlink = $tag;
    }
}
