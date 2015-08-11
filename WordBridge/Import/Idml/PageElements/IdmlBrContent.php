<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlBrContent.php
 *
 * @class   IdmlBrContent
 *
 * @description Parser for InDesign <Br />.
 *
 * Careful: an InDesign BR is not really the same as an HTML BR.  See IdmlProduceHTML for the gory details.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement', 'Import/Idml/PageElements');

/**
 * BR content element. Found inside CharacterStyleRange.
 */
class IdmlBrContent extends IdmlElement
{
    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /**
     * Constructor.
     * @param IdmlStory $story
     */
    public function __construct(IdmlStory $story = null)
    {
        parent::__construct();
        $this->story = $story;
    }

    /**
     * While no parsing of the Br element per se is required, we need to reset the tab counters in the parent paragraph.
     * Parse from DOM node.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $paragraph = $this->getParagraph();

        $paragraph->tabIndex = 0;
    }

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitBrContent($this, $depth);
    }
}
