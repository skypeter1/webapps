<?php

App::uses('IdmlFontFamily',     'Import/Idml/Fonts');
App::uses('IdmlFont',           'Import/Idml/Fonts');

/**
 * IdmlFontManager is used to load and create all the fonts.
 *
 */
class IdmlFontManager
{
    protected static $instance;

    /**
     * Get instance.
     */
    public static function getInstance()
    {
        if (!self::$instance)
        {
            self::$instance = new IdmlFontManager();
        }
        
        return self::$instance;
    }

    /**
     * Array of font families.
     * @var array<IdmlFontFamily>
     */
    public $fontFamilies = array();

    /**
     * Create idml font manager.
     */
    public function __construct()
    {
    }

    /**
     * Load full list of fonts.
     *
     * @param string $filename Full path to the fonts.xml resource file.
     * @retur none
     */
    public function loadFonts($filename)
    {
        if (!file_exists($filename))
        {
            $progress->setWarning("Story with file {$filename} is not found.");
            return;
        }

        $domDocument = new DOMDocument();
        if ($domDocument->load($filename) === false)
        {
            $progress->setWarning("Could not load xml for story {$filename}");
        }

        $this->isLoaded = true;
        $this->parse($domDocument);
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
        foreach($this->fontFamilies as $fontFamily)
        {
            $result = $fontFamily->findFontByPsName($postScriptName);
            if ($result)
            {
                return $result;
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
        foreach($this->fontFamilies as $fontFamily)
        {
            $result = $fontFamily->findFontByName($name);
            if ($result)
            {
                return $result;
            }
        }

        return null;
    }

    /**
     * Parse xml.
     * @param DOMDocument $domDocument
     */
    public function parse(DOMDocument $domDocument)
    {
        $this->fontFamilies = array();
        
        $fontFamilyNodes = $domDocument->getElementsByTagName('FontFamily');
        foreach($fontFamilyNodes as $fontFamilyNode)
        {
            $fontFamily = new IdmlFontFamily();
            $fontFamily->parse($fontFamilyNode);
            $this->fontFamilies[] = $fontFamily;
        }
    }
}
