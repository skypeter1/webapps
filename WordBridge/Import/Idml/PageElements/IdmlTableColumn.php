<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTableColumn.php
 * 
 * @class   IdmlTableColumn
 * 
 * @description Parser for <Column>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement', 'Import/Idml/PageElements');


class IdmlTableColumn extends IdmlElement
{
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
        // Group is also visitable just in case it can have a style.
        $visitor->visitTableColumn($this, $depth);

        /*foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }*/

        $visitor->visitTableColumnEnd($this, $depth);
    }

    /**
     * Parse from DOM node.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);
        
//        $this->styleInfo['inlineStyle']->translateSingleColumnWidth(); //set Css width for column

/*
        if($this->hasIdmlProperty('SingleColumnWidth'))
        {
            $val = $this->getIdmlProperty('SingleColumnWidth');
            if(is_numeric($val))
            {
                $val = round($val);
                $this->addCssProperty('width', $val . 'pt');
            }
        }
*/

/*
        //add needed column info to parent table because IdmlHtmlProducer needs this at the IdmlTable element level
        if($this->styleInfo["inlineStyle"]->hasCssProperty('width'))
        {
            $table = $this->parentIdmlObject();
            if(IdmlParserHelper::getIdmlObjectType($table) == "Table")
            {
                $table->cols[] = $this;
                $colwidth = $this->styleInfo["inlineStyle"]->getCssPropertyString('width');
                if($colwidth) {
                    $table->colGroupStyles[] = $colwidth;
                }
            }
        }
        else
        {
            CakeLog::debug("[IdmlTableColumn::parse] column width not found? ".__FILE__." Line ".__LINE__);
        }
*/


        if ($node->hasAttribute('SingleColumnWidth'))
        {
            $singleColumnWidth = round($node->getAttribute('SingleColumnWidth'));

            $table = $this->parentIdmlObject();
            if (IdmlParserHelper::getIdmlObjectType($table) == "Table")
            {
                $table->cols[] = $this;
                $colwidth = 'width:' . $singleColumnWidth . 'pt;';
                $table->colGroupStyles[] = $colwidth;
            }
        }
        else
        {
            CakeLog::debug("[IdmlTableColumn::parse] column width not found? ".__FILE__." Line ".__LINE__);
        }

        //IdmlTableColumn has no children to parse
    }
    
}

?>
