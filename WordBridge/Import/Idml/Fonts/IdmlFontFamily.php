<?php

App::uses('IdmlFont',       'Import/Idml/Fonts');
App::uses('IdmlAttributes', 'Import/Idml');


/**
 * Description of IdmlFontFamily
 *
 */
class IdmlFontFamily
{
    /**
     * Idml UID for this font family.
     * @var string
     */
    public $UID;

    /**
     * Name of the font family.
     * @var string
     */
    public $name;

    /**
     * Array of idml fonts.
     * @var array<IdmlFont>
     */
    public $fonts = array();

    /**
     * Constructor.
     */
    public function __construct()
    {

    }

    /**
     * Parse font family.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->UID = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : '';
        $this->name = $node->hasAttribute(IdmlAttributes::Name) ? $node->getAttribute(IdmlAttributes::Name) : '';

        $fontNodes = $node->getElementsByTagName('Font');
        foreach($fontNodes as $fontNode)
        {
            $font = new IdmlFont();
            $font->parse($fontNode);
            $font->fontFamily = $this;
            $this->fonts[] = $font;
        }
    }

      /**
     * Get the font with specific post script name.
     *
     * @param string $postScriptName
     *
     * @return IdmlFont
     */
    public function findFontByPsName($postScriptName)
    {
        foreach($this->fonts as $font)
        {
            if ($font->psName == $postScriptName)
            {
                return $font;
            }
        }

        return null;
    }

    /**
     * Get the font with full name. E.g. "Minion Pro Bold Cond"
     *
     * @param string $name
     *
     * @return IdmlFont
     */
    public function findFontByName($name)
    {
        foreach($this->fonts as $font)
        {
            if ($font->name == $name)
            {
                return $font;
            }
        }

        return null;
    }
}
