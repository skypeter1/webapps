<?php
/**
 * @package /app/Import/Idml/IdmlFont.php
 * 
 * @class   IdmlFont
 * 
 * @description 
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
class IdmlFont
{
    /**
     * Name of the font.
     * @var string
     */
    public $name;

    /**
     * Font family object. Note. This is not a string.
     * @var IdmlFontFamily
     */
    public $fontFamily;

    /**
     * Ps name of the font.
     * Eg. Myriad-Bold
     * @var type
     */
    public $psName;

    /**
     * Font style name.
     * @var string
     */
    public $fontStyleName;

    /** The constructor */
    public function __construct()
    {
        
    }

    /**
     * Get font family name or empty string in case there is no font family.
     * @return string
     */
    public function getFontFamilyName()
    {
        if ($this->fontFamily)
        {
            return $this->fontFamily->name;
        }
        
        return '';
    }

    /**
     * Parse element.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->name = $node->hasAttribute(IdmlAttributes::Name) ? $node->getAttribute(IdmlAttributes::Name) : '';
        $this->psName = $node->hasAttribute(IdmlAttributes::PostScriptName) ? $node->getAttribute(IdmlAttributes::PostScriptName) : '';
        $this->fontStyleName = $node->hasAttribute(IdmlAttributes::FontStyleName) ? $node->getAttribute(IdmlAttributes::FontStyleName) : '';

    }
}

?>
