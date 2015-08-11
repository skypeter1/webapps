<?php
/**
 * @package /app/Import/Idml/IdmlTags.php
 * 
 * @class   IdmlTage
 * 
 * @description There should always be exactly one Tags.xml file. It contains the names of tags that appear as
 *              supplemental identifiers wrapping <CharacterRange> inside stories. These optional supplemental 
 *              names are useful to the PXE processor, but are otherwise not needed for a typical ePub.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlTags
{
    /** @var IdmlPackage $idmlPackage is the parent object */
    private $idmlPackage;

    /** @var array[string] $tags An associative array of tags where the key is the InDesign <XMLTag Self=''> and the
     *  value is the <XMLTag Name=''>
     */
    public $tags;

    /**
     * Filename.
     * @var string
     */
    public $filename;
    
    /**
     * The constructor.
     * @param IdmlPackage $idmlPackage is the parent object
     */
    public function __construct($idmlPackage)
    {
        $this->idmlPackage = $idmlPackage;
        $this->tags = array();
    }
    
    /**
     *  The parse function is the starting point for parsing the Tags XML file.
     * 
     *  @param string $filename is a fully qualified filename of the Tags XML file. If empty it will use internal propery.
     */
    public function load($filename = '')
    {
        if (empty($filename))
        {
            $filename = $this->filename;
        }
        
        $doc = new DomDocument();
        $b = $doc->load($filename);
        if ($b === false)
        {
            return false;
        }

        $this->parse($doc);
    }

    /**
     * Parse document.
     * @param DOMDocument $doc
     */
    private function parse(DOMDocument $doc)
    {
        $xpath = new DOMXPath($doc);

        $items = $xpath->query('//idPkg:Tags//XMLTag');
        foreach($items as $item)
        {
            $attributes = $item->attributes;
            $key = $attributes->getNamedItem('Self')->value;        // like 'XMLTag/accompaniment'
            $value = $attributes->getNamedItem('Name')->value;      // like 'accompaniment"'

            $this->tags[$key] = $value;
        }
    }

    /* This accept function is called by the parent IdmlPackage.
     * 
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth)
    {
        $visitor->visitTags($this, $depth);
    }
}

?>
