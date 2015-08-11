<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlText.php
 *
 * @class   IdmlText
 *
 * @description Parser for InDesign text node.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',    'Import/Idml/PageElements');


/**
 * This is real content of element. Contains just text.
 * NOTE: This element does not have a parse function, since parsing is managed by the constructor.
 */
class IdmlText extends IdmlElement
{
    /**
     * Textual content.
     * @var string
     */
    public $content = '';

    /**
     * Constructor.
     * @param string $content
     * @param IdmlContent $parent
     */
    public function __construct($content, IdmlContent $parent)
    {
        parent::__construct();

        $this->content = $content;
        $this->parentElement = $parent;
        $parent->childrenElements[] = $this;
        $this->usesNestedClass = true;
    }

    /**
     * Getter method for text content
     * @return string $content
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
        $visitor->visitText($this, $depth);
    }
    
    /**
     * checks if the content element has any text 
     * @return boolean
     */
    public function hasContent()
    {
        return ($this->content == '') ? false : true;
/*
        //before we test the string, remove the BOM character (U+FEFF)
        $bom = pack('H*','EFBBBF');
        $content = trim(preg_replace("/^$bom/", '', $this->content));
        
        return (mb_strlen($content, "UTF-8") > 0);
*/
    }
}
