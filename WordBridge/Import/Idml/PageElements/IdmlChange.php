<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlChange.php
 *
 * @class   IdmlChange
 *
 * @description Parser for <Change> elements. This is the Track Changes feature in InDesign.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlAttributes', 'Import/Idml');
App::uses('IdmlElement', 'Import/Idml/PageElements');


class IdmlChange extends IdmlElement
{
    /*
     * 'DeletedText', 'InsertedText', 'MovedText'
     */
    public $changeType;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->changeType = 'undefined';
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        switch($this->changeType)
        {
            case 'DeletedText':
                return;

            case 'InsertedText':
            case 'MovedText':
                $visitor->visitChange($this, $depth);
                foreach($this->childrenElements as $child)
                {
                    $child->accept($visitor, $depth+1);
                }
                $visitor->visitChangeEnd($this, $depth);
                return;

            case 'undefined':
            default:
                CakeLog::debug("[IdmlChange::accept] Unexpected changeType '" . $this->changeType . "'");
                return;
        }
    }

    /**
     * Parse function.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->changeType = $node->hasAttribute(IdmlAttributes::ChangeType) ? $node->getAttribute(IdmlAttributes::ChangeType) : 'undefined';

        $this->parseChildren($node);
    }
}

?>
