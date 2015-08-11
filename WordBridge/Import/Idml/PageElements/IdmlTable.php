<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTable.php
 * 
 * @class   IdmlTable
 * 
 * @description Parser for <Table>.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlParserHelper',       'Import/Idml');
App::uses('IdmlAttributes',         'Import/Idml');
App::uses('IdmlElement',            'Import/Idml/PageElements');
App::uses('IdmlElementFactory',     'Import/Idml/PageElements');


class IdmlTable extends IdmlElement
{
    /**
     * this will hold <col> style values (width) for each column of the table 
     * so we can create a colgroup and set column widths appropriately with IdmlHtmlProducer
     * @var array
     */
    public $colGroupStyles = array();
    //arrays to hold pointers to children idml elements by type
    public $cols = array();
    public $rows = array();
    
    public $headerRowCount = 0;
    public $footerRowCount = 0;
    public $bodyRowCount = 0;
    public $columnCount = 0;
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
        $visitor->visitTable($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }

        $visitor->visitTableEnd($this, $depth);
    }

    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->headerRowCount = $node->hasAttribute('HeaderRowCount') ? intval($node->getAttribute('HeaderRowCount')) : 0;
        $this->footerRowCount = $node->hasAttribute('FooterRowCount') ? intval($node->getAttribute('FooterRowCount')) : 0;
        $this->bodyRowCount = $node->hasAttribute('BodyRowCount')     ? intval($node->getAttribute('BodyRowCount'))   : 0;
        $this->columnCount = $node->hasAttribute('ColumnCount')       ? intval($node->getAttribute('ColumnCount'))    : 0;

        $this->parseChildren($node);
    }

    /**
     * Parse children. We override this because the xml DOM hierarchy has rows, columns, and cells as children of table
     * we need to change the idml element hierarchy so tables are parents of rows which are parents of cells
     * @param DOMElement $parentNode
     */
    protected function parseChildren($parentNode)
    {
        foreach ($parentNode->childNodes as $childNode)
        {
            if (IdmlParserHelper::isParsableChildIdmlObjectNode($childNode))
            {
                $parsableObject = IdmlElementFactory::createFromNode($childNode);
                if(is_object($parsableObject))
                {
                    //set the parent first since sometimes parse requires or sets parent data
                    $parsableObject->parentElement = $this;
                    if(!IdmlParserHelper::isIdmlTagNode($childNode) 
                            && IdmlParserHelper::getIdmlObjectType($parsableObject) != "TableCell")
                    {
                        $this->childrenElements[] = $parsableObject; //don't set IdmlTable as parent for IdmlTableCell
                    }
                    $parsableObject->parse($childNode);                
                }
            }
        }
    }
}

?>
