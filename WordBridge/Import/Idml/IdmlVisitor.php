<?php

/**
 * @package /app/Import/Idml/IdmlVisitor.php
 *
 * @class   IdmlVisitable
 *
 * @description Just a simple visitor interface for the classic "visitor pattern".
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
interface IdmlVisitor
{
    public function visitAssembler(IdmlAssembler $item, $depth = 0);
    public function visitAssemblerEnd(IdmlAssembler $item, $depth = 0);
    public function visitPackage(IdmlPackage $item, $depth = 0);
    public function visitPackageEnd(IdmlPackage $item, $depth = 0);
    public function visitTags(IdmlTags $item, $depth = 0);
    public function visitSpread(IdmlSpread $item, $depth = 0);
    public function visitSpreadEnd(IdmlSpread $item, $depth = 0);
    public function visitPage(IdmlPage $item, $depth = 0);
    public function visitPageEnd(IdmlPage $item, $depth = 0);
    public function visitTextFrame(IdmlTextFrame $element, $depth = 0);
    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0);
    public function visitEmbeddedTextFrame(IdmlTextFrame $element, $depth = 0);
    public function visitStory(IdmlStory $item, $depth = 0);
    public function visitStoryEnd(IdmlStory $item, $depth = 0);
    public function visitParagraphRange(IdmlParagraphRange $element, $depth = 0);
    public function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0);
    public function visitCharacterRange(IdmlCharacterRange $element, $depth = 0);
    public function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0);
    public function visitImage(IdmlImage $element, $depth = 0);
    public function visitRectangle(IdmlRectangle $element, $depth = 0);
    public function visitRectangleEnd(IdmlRectangle $element, $depth = 0);
    public function visitGroup(IdmlGroup $element, $depth = 0);
    public function visitGroupEnd(IdmlGroup $element, $depth = 0);
    public function visitChange(IdmlChange $element, $depth = 0);
    public function visitChangeEnd(IdmlChange $element, $depth = 0);
    public function visitContent(IdmlContent $element, $depth = 0);
    public function visitBrContent(IdmlBrContent $element, $depth = 0);
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0);
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0);
    public function visitTable(IdmlTable $element, $depth = 0);
    public function visitTableEnd(IdmlTable $element, $depth = 0);
    public function visitTableRow(IdmlTableRow $element, $depth = 0);
    public function visitTableRowEnd(IdmlTableRow $element, $depth = 0);
    public function visitTableColumn(IdmlTableColumn $element, $depth = 0);
    public function visitTableColumnEnd(IdmlTableColumn $element, $depth = 0);
    public function visitTableCell(IdmlTableCell $element, $depth = 0);
    public function visitTableCellEnd(IdmlTableCell $element, $depth = 0);
    public function visitHyperlink(IdmlHyperlink $element, $depth = 0);
    public function visitHyperlinkEnd(IdmlHyperlink $element, $depth = 0);    
}

?>
