<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTextVariableInstance.php
 *
 * @class   IdmlTextVariableInstance
 *
 * @description Parser for InDesign TextVariableInstance node.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */


/**
 * This is real content of an InDesign TextVariableInstance.
 * At first writing, we're only saving the 'ResultText' attribute and treating it as text.
 * Parsing/processing may become more sophisticated in future versions.
 */
class IdmlTextVariableInstance extends IdmlElement
{
    /**
     * Textual content, based on the ResultText attribute.
     * @var string
     */
    public $content = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->usesNestedClass = true;
    }

    /**
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->content = $node->hasAttribute('ResultText') ? $node->getAttribute('ResultText') : '';
    }

    /**
     * Getter method for text resultText
     * @return string $resultText
     */
    public function getTextContent()
    {
        return $this->content;
    }

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitTextVariableInstance($this, $depth);
    }
}
