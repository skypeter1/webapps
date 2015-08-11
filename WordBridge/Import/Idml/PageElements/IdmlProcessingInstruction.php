<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlProcessingInstruction.php
 *
 * @class   IdmlProcessingInstruction
 *
 * @description Parser for InDesign Processing Instruction.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',    'Import/Idml/PageElements');


class IdmlProcessingInstruction extends IdmlElement
{
    /**
     * Numeric indicator for the instruction (e.g. the 18 in '<?ACE 18 ?>')
     * @var string
     */
    protected $type = '';

    /**
     * Constructor.
     * @param int $type
     */
    public function __construct($type, IdmlContent $parent)
    {
        parent::__construct();

        $this->type = $type;
        $this->parentElement = $parent;
        $parent->childrenElements[] = $this;
        $this->usesNestedClass = true;
    }

    /**
     * Getter function for instruction type
     */
    public function getInstructionType()
    {
        return $this->type;
    }

    /**
     * Visit this processing instruction.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitProcessingInstruction($this, $depth);
    }
}
