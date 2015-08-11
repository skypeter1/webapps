<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTableCell.php
 * 
 * @class   IdmlTableCell
 * 
 * @description Parser for <Cell>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement', 'Import/Idml/PageElements');


class IdmlTableCell extends IdmlElement
{
    /**
     * colspan and rowspan values for this cell
     * @var int
     */
    public $colspan = -1; // apparently 0 is a valid value ... which means to span to end of current table row
    public $rowspan = -1; // 0 is a valid value
    public $colnumber = -1;
    public $rownumber = -1;
    /**
     * Constructor.
     * @param IdmlPage $page Could be null
     * @param IdmlStory $story Could be null if rectangle is not part of story.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitTableCell($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitTableCellEnd($this, $depth);
    }


    public function parse(DOMElement $node)
    {
        parent::parse($node);
        
        //we must set the <col style='width:...' /> for this column in the table
        $this->rowspan = $node->hasAttribute('RowSpan')    ? intval($node->getAttribute('RowSpan'))    : -1;
        $this->colspan = $node->hasAttribute('ColumnSpan') ? intval($node->getAttribute('ColumnSpan')) : -1;

        // Right now the parent is a table element, but we need the parent to be the corresponding row for this cell.
        // Find the right row in the table array and assign it as this cell's parent ImdlElement.
        $colrow = $node->hasAttribute('Name') ? $node->getAttribute('Name') : '0:0';

        $colrowArray = explode(":", $colrow); // <col>:<row>
        $this->colnumber = intval($colrowArray[0]);
        $this->rownumber = intval($colrowArray[1]);
        $row = $this->parentElement->rows[$this->rownumber];
        if(IdmlParserHelper::getIdmlObjectType($row) == "TableRow")
        {
            /*@var $row IdmlTableRow*/
            $row->childrenElements[] = $this;
            $this->parentElement = $row;  //assign this cell as child of the table row
        }
        else
        {
            CakeLog::debug("[IdmlTableCell::parse] cannot find parent row in table for cell? ".__FILE__." Line ".__LINE__);
        }
        $this->parseChildren($node);
    }
    
}

?>
