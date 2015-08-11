<?php
/**
 * @package /app/Import/Idml/IdmlProduceHtmlDiagnostic.php
 * 
 * @class   IdmlProduceHtmlDiagnostic
 * 
 * @description A diagnostic helper that produces HTML DOM with comments and indents, instead of true HTML.
 *              This is for debugging use only.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlProduceReflowable', 'Import/Idml/Html');


class IdmlProduceHtmlDiagnostic extends IdmlProduceReflowable
{
    public function visitAssembler(IdmlAssembler $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='IdmlAssembler' -->", $depth);
    }
    public function visitAssemblerEnd(IdmlAssembler $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='/IdmlAssembler' -->", $depth);
    }
    public function visitPackage(IdmlPackage $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='IdmlPackage' -->", $depth);
    } 
    public function visitPackageEnd(IdmlPackage $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='/IdmlPackage' -->", $depth);
        parent::visitPackageEnd($item, $depth);
    } 
    public function visitSpread(IdmlSpread $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='IdmlSpread' -->", $depth);
    } 
    public function visitSpreadEnd(IdmlSpread $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='/IdmlSpread' -->", $depth);
    } 
    public function visitPage(IdmlPage $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='IdmlPage' -->", $depth);
    } 
    public function visitPageEnd(IdmlPage $item, $depth = 0)
    {
        $this->addPageElement("<!-- data-idml='/IdmlPage' -->", $depth);
    } 
    public function visitTextFrame(IdmlTextFrame $element, $outer_depth = 0)
    {
        // For the outermost TextFrame whose parent is IdmlPage, do not do the special anchor processing
        $parentElement = $element->parentIdmlObject();
        if($element->parentIdmlObject() == null)
        {
            $this->addPageElement("<!-- OUTERTEXTFRAME {$element->story->UID} -->", $outer_depth, '');
            return; // yes visit children
        }

        // Perform special anchor processing for TextFrames that are embedded in another story
        else
        {            
            $stack = $this->getAncestors($element,'IdmlPage');

            // close all open tags back to the root
            $this->rewind($stack, $outer_depth);

            $inner_depth = (get_class($element->parentIdmlObject() ) == 'IdmlCharacterRange')  ? $outer_depth-3 : $outer_depth;
            $this->addPageElement("<!-- ANCHORED TEXTFRAME {$element->story->UID} -->", $inner_depth, '');
            $this->addPageElement("<div data-idml='IdmlTextFrame {$element->UID}'>", $inner_depth);
            $this->addPageElement("<!-- ANCHOR {$element->story->UID} -->", $inner_depth+1, '');

            // Create a new producer for recursion . . .
            $thisClass = get_class($this);
            assert($thisClass == 'IdmlProduceFixedLayout' || $thisClass == 'IdmlProduceReflowable' || $thisClass == 'IdmlProduceHtmlDiagnostic');
            $currentRecursivePageNumber = $this->pageNumber;
            $htmlProducer = new $thisClass($currentRecursivePageNumber);

            // . . . then walk the tree starting from the current text frame's story . . .
            if ($element->story && $element->visible)
            {
                $element->story->accept($htmlProducer, $inner_depth+1);
            }

            // . . . and finally stuff the content that _it_ produces back into this object. . .
            $innerContent = $htmlProducer->getBodyContent();
            $diagnosticContent = $htmlProducer->getDiagnosticContent();
            $this->addPageElement($innerContent, 0, $diagnosticContent);

            // . . . and copy the page number of the inner object to the outer object.
            $this->pageNumber = $htmlProducer->pageNumber;

            $this->addPageElement("<!-- /ANCHOR {$element->story->UID} -->", $inner_depth+1, '');
            $this->addPageElement("</div><!-- IdmlTextFrame {$element->UID }-->", $inner_depth);
            $this->addPageElement("<!-- /ANCHORED TEXTFRAME {$element->story->UID} -->", $inner_depth, '');

            // reopen all tags from the root down to where we were before we were interrupted
            $this->fastForward($stack, $outer_depth);          

            // Important: Instruct IdmlTextFrame not to continue any deeper
            return 'do not visit children';
        }
    } 
    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0)
    {
    } 
    public function visitStory(IdmlStory $item, $depth = 0)
    {
        $this->addPageElement("<div data-idml='IdmlStory {$item->UID}'>", $depth); 
    } 
    public function visitStoryEnd(IdmlStory $item, $depth = 0)
    {
        $this->addPageElement("</div><!-- IdmlStory {$item->UID} -->", $depth); 
    } 
    public function visitParagraphRange(IdmlParagraphRange $element, $depth = 0)
    {
        $this->addPageElement("<div data-idml='IdmlParagraphRange'>", $depth); 
    } 
    public function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0)
    {
        $this->addPageElement("</div><!-- IdmlParagraphRange -->", $depth); 
    } 
    public function visitCharacterRange(IdmlCharacterRange $element, $depth = 0)
    {
        $this->addPageElement("<span data-idml='IdmlCharacterRange'>", $depth); 
    } 
    public function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0)
    {
        $this->addPageElement("</span><!-- IdmlCharacterRange -->", $depth); 
    }
    public function visitContentEnd(IdmlContent $element, $depth = 0)
    {
        $paragraph = $this->getAncestor($element, 'IdmlParagraphRange');
        if (is_null($paragraph)) throw new Exception('Content tag has no paragraph ancestor. Contact tech support');

        if ($paragraph->listType == 'NumberedList' && !is_null($this->numberingLevel))
        {
            $this->appliedNumberingData[$this->numberingLevel][$this->currentNumberingList]++;
        }
    }

    public function visitImage(IdmlImage $element, $depth = 0)
    {
        $this->addPageElement("<div data-idml='IdmlImage' />", $depth); 
    } 
    public function visitRectangle(IdmlRectangle $element, $depth = 0)
    {
        $this->addPageElement("<div data-idml='IdmlRectangle'>", $depth); 
    } 
    public function visitRectangleEnd(IdmlRectangle $element, $depth = 0)
    {
        $this->addPageElement("</div><!-- IdmlRectangle -->", $depth); 
    } 
    public function visitGroup(IdmlGroup $element, $depth = 0)
    {
        $this->addPageElement("<div data-idml='IdmlGroup'>", $depth); 
    } 
    public function visitGroupEnd(IdmlGroup $element, $depth = 0)
    {
        $this->addPageElement("</div><!-- IdmlGroup -->", $depth); 
    } 
    //public function visitContent(IdmlContent $element, $depth = 0)
    //public function visitBrContent(IdmlBrContent $element, $depth = 0)
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0)
    {
        parent::visitXmlElement($element, $depth);
        $this->addPageElement("<code data-idml='IdmlXmlElement' data-markup-tag='{$element->markupTag}'>", $depth); 
    } 
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0)
    {
        $this->addPageElement("</code><!-- data-markup-tag='{$element->markupTag} -->", $depth); 
    } 
    public function visitTable(IdmlTable $element, $depth = 0)
    {
        $this->addPageElement("<table data-idml='IdmlTable'>", $depth); 
    } 
    public function visitTableEnd(IdmlTable $element, $depth = 0)
    {
        $this->addPageElement("</table>", $depth); 
    } 
    public function visitTableRow(IdmlTableRow $element, $depth = 0)
    {
        $this->addPageElement("<tr data-idml='IdmlTableRow'>", $depth); 
    } 
    public function visitTableRowEnd(IdmlTableRow $element, $depth = 0)
    {
        $this->addPageElement("</tr>", $depth); 
    } 
    public function visitTableColumn(IdmlTableColumn $element, $depth = 0)
    {
        $this->addPageElement("<col data-idml='IdmlTableColumn' />", $depth); 
    } 
    public function visitTableColumnEnd(IdmlTableColumn $element, $depth = 0)
    {
    } 
    public function visitTableCell(IdmlTableCell $element, $depth = 0)
    {
        $this->addPageElement("<td data-idml='IdmlTableCell'>", $depth); 
    } 
    public function visitTableCellEnd(IdmlTableCell $element, $depth = 0)
    {
        $this->addPageElement("</td>", $depth); 
    } 
    public function visitHyperlink(IdmlHyperlink $element, $depth = 0)
    {
        $this->addPageElement("<a data-idml='IdmlHyperlink' />", $depth); 
    } 
    public function visitHyperlinkEnd(IdmlHyperlink $element, $depth = 0)
    {
    }         
    protected function savePage()
    {
        $content = $this->getPageContent();
        $this->diagnosticTool($content);
        $this->pageNumber++;

        return '';  // Brings this savePage() into sync with other producers.
    }
}
?>
