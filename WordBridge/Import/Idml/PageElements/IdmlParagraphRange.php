<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlParagraphRange.php
 * 
 * @class   IdmlParagraphRange
 * 
 * @description Parser for Story <ParagraphRange>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlPage',               'Import/Idml');
App::uses('IdmlStory',              'Import/Idml');
App::uses('IdmlAttributes',         'Import/Idml');
App::uses('IdmlElement',            'Import/Idml/PageElements');
App::uses('IdmlCharacterRange',     'Import/Idml/PageElements');
App::uses('IdmlNestedStyleHelper',  'Import/Idml/PageElements');
App::uses('IdmlDeclarationParser',  'Import/Idml/Styles/Declarations');


class IdmlParagraphRange extends IdmlElement
{
    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /**
     * Text-align. Valid values are 'left', 'right', 'center', 'justify'
     * @var string Defaults to 'left'
     */
    public $textAlign = 'left';

    /**
     * The following three variables are used during production.
     * Values are saved while processing $this, and retrieved for styling its children.
     */
    public $tagName = '';
    public $listType = 'NoList';
    public $start = 1;

    /**
     * The following properties help manage IDML tab stops.
     * $tabIndex is a sequence number of the tab stop data on a line. It gets reset to 0 by Br elements, which start a new line.
     * $tabCount is the number of tabs used in the paragraph. Br elements set it to the maximum for all lines in the paragraph.
     * $tabSpansToClose indicates how many tab-related spans are open on a line: they will need to be closed at paragraph end.
     * @var int $tabIndex
     * @var int $tabSpansToClose
     */
    public $tabIndex;
    public $tabCount = 0;
    public $tabSpansToClose;

    /**
     * The position and alignment property for the paragraph's first tab stop, whose parent element may not be
     *   easily recalled at the beginning of the paragraph during production.
     * The remaining tab stops in the paragraph are visited during production inside their parent content elements.
     * @var string $firstTabPosition
     * @var string $firstTabAlignment
     */
    public $firstTabPosition = null;
    public $firstTabAlignment = null;

    /**
     * @var boolean $hasNestedStyle - indicates a nested style is applied. Defaults to false; set to true in parsing if nested styles are applied.
     * @var IdmlNestedStyleHelper - helper class to manage the nested style
     * A nested style also triggers the instantiation of an IdmlNestedStyleHelper object, which manages storage of parameters and methods
     */
    public $hasNestedStyle = false;
    public $nestedStyleHelpers = array();

    /**
     * Constructor.
     * @param IdmlStory $story
     */
    public function __construct(IdmlStory $story = null)
    {
        parent::__construct();
        
        $this->story = $story;
        $this->hasNestedStyle = false;
        $this->initTabData();
    }

    /**
     * Initialize all TabList data
     */
    public function initTabData()
    {
        $this->tabIndex = 0;
        $this->tabSpansToClose = 0;
    }

    /**
     * Reset the number of items found in all the paragraph's nested style helpers to zero
     */
    public function resetNestedStyles()
    {
        foreach ($this->nestedStyleHelpers as $nestedStyleHelper)
        {
            $nestedStyleHelper->resetTimesFound();
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

        $justification = $node->hasAttribute(IdmlAttributes::Justification) ? $node->getAttribute(IdmlAttributes::Justification): 'left';
        if (isset($this->story->page))
        {
            $pagePosition = $this->story->page->pagePosition;
        }
        else
        {
            $pagePosition = 'left';
        }

        $this->textAlign = IdmlParserHelper::convertJustification($justification, $pagePosition);

        // Change the TabList property cluster into an array for easier handling
        IdmlDeclarationParser::arrayifyDupes($this->contextualStyle->idmlContextualElement, $this->contextualStyle);

        // Check for nested styles; if any exist, set up the helper class object
        $this->parseNestedStyle();

        $this->parseChildren($node);
    }

    /**
     * If the paragraph has a nested style, set up the necessary helper class object here
     */
    private function parseNestedStyle()
    {
        $appliedParagraphStyleName = $this->appliedStyleName;
        $declarationMgr = IdmlDeclarationManager::getInstance();
        $appliedParagraphStyle = $declarationMgr->declaredStyles[$appliedParagraphStyleName];

        if (array_key_exists('Properties::AllNestedStyles', $appliedParagraphStyle->idmlKeyValues) &&
            count($appliedParagraphStyle->idmlKeyValues['Properties::AllNestedStyles']) > 0)
        {
            $this->nestedStyleHelpers = array();

            foreach ($appliedParagraphStyle->idmlKeyValues['Properties::AllNestedStyles'] as $nestedStyle)
            {
                $this->nestedStyleHelpers[] = new IdmlNestedStyleHelper($nestedStyle, $this);
                $this->hasNestedStyle = true;
            }
        }
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitParagraphRange($this, $depth);

        // Reset the tab index to zero; this property is used to determine when the last tab on a row is reached.
        $this->tabIndex = 0;

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitParagraphRangeEnd($this, $depth);
    }    
}

?>
