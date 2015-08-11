<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTableRow.php
 * 
 * @class   IdmlTableRow
 * 
 * @description Parser for <Row>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',    'Import/Idml/PageElements');
App::uses('IdmlTable',      'Import/Idml/PageElements');


class IdmlTableRow extends IdmlElement
{
    /**
     * used for producing html to know if this row is first and/or last in a group
     * @var bool
     */
    public $isFirstRow = false;
    public $isLastRow = false;
    /**
     * used for producing html
     * @var string
     */
    public $rowType = '';
    
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
         $visitor->visitTableRow($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitTableRowEnd($this, $depth);
    }


    public function parse(DOMElement $node)
    {
        parent::parse($node);
        
        //parse and set row attributes that are based upon parent table
        $rownum = $node->hasAttribute('Name') ? intval($node->getAttribute('Name')) : 0;

        $table = $this->parentIdmlObject();
        if(IdmlParserHelper::getIdmlObjectType($table) == "Table")
        {
            //set this row in the table rows array by index
            $table->rows[$rownum] = $this;
            //from Indesign the order of rows is header, body, footer
            
            //we must set what type of row this is and whether it is first and/or last in its group
            if($table->headerRowCount && $rownum < $table->headerRowCount) {
                $this->rowType = 'thead';
                $rowGroupStart = 0;
                $rowGroupEnd = $table->headerRowCount - 1;
            }
            elseif ($table->bodyRowCount && $rownum < ($table->headerRowCount + $table->bodyRowCount)) 
            {
                $this->rowType = 'tbody';
                $rowGroupStart = $table->headerRowCount;
                $rowGroupEnd = $table->headerRowCount + $table->bodyRowCount - 1;
            }
            elseif ($table->footerRowCount && $rownum < ($table->headerRowCount + $table->bodyRowCount + $table->footerRowCount)) 
            {
                $this->rowType = 'tfoot';
                $rowGroupStart = $table->headerRowCount + $table->bodyRowCount;
                $rowGroupEnd = $table->headerRowCount + $table->bodyRowCount + $table->footerRowCount - 1;
            }
            else
            {
                //this is a problem as we can't place the row?
                CakeLog::debug("[IdmlTableRow::parse] unable to identify tablerow type and place in group ".__FILE__." Line ".__LINE__);
            }
            
            if($rownum == $rowGroupStart)
            {
                $this->isFirstRow = true;
            }
            if($rownum == $rowGroupEnd)
            {
                $this->isLastRow = true;
            }
        }
        else
        {
            //something is wrong so log this?
            CakeLog::debug("[IdmlTableRow::parse] parent is not an IdmlTable?".__FILE__." Line ".__LINE__);
        }
        
        //TableRow has no children to parse
        
    }

    
}

?>
