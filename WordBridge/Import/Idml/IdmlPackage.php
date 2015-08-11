<?php

/**
 * @package /app/Import/Idml/IdmlPackage.php
 * 
 * @class   IdmlPackage
 * 
 * @description This class unzips an IDML file (which is a collection of XML files) and provides entry points for
 *          parsing the styles, spreads and stories contained within it.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::import('Vendor',   'Chaucer/Common/FileManager');
App::import('Vendor',   'Chaucer/Common/ChaucerDomDocument');
App::uses('IdmlMasterSpread',       'Import/Idml');
App::uses('IdmlStory',              'Import/Idml');
App::uses('IdmlSpread',             'Import/Idml');
App::uses('IdmlFontManager',        'Import/Idml/Fonts');
App::uses('IdmlHyperlinkManager',   'Import/Idml/PageElements');
App::uses('IdmlLayerManager',       'Import/Idml');
App::uses('IdmlPreferences',        'Import/Idml/Resources');
App::uses('IdmlDeclarationParser',  'Import/Idml/Styles/Declarations');
App::uses('IdmlDeclaredColors',     'Import/Idml/Styles/Declarations');
App::uses('IdmlTags',               'Import/Idml/Tags');


class IdmlPackage
{
    /** 
     * IdmlAssembler object.
     * @var IdmlAssembler
     */
    private $idmlAssembler;
    
    /**
     * is the zipped IDML file
     * @var string
     */
    public $idmlFilename;

    /** 
     * is the location where the unzipped XML files are kept.
     * @var string
     */
    public $tempDir;

    /**
     * 
     * @var IdmlPreferences 
     */
    public $preferences;
    
    /**
     * Is a reference to the object containing the parsed contents of the XML file containing <idPkg:Graphics>.
     * @var IdmlDeclaredColors
     */
    public $graphics;
    
    /**
     * is a reference to the XML file containing <idPkg:Tags>.
     * @var IdmlTags
     */
    public $tags;    
    
    /**
     * is an associative array of <idPkg:MasterSpread> references. The key to the array is the MasterSpread UID.
     * @var IdmlSpread
     */
    public $masterSpreads;    

    /**
     * is an indexed array of <idPkg:Spread> references to IdmlSpread objects,
     * which are XML documents that contain pages and page-level frames. These are ordered consecutively. 
     * @var array<IdmlSpread>
     */
    public $spreads;

    /** 
     * is an associative array of <idPkg:Story> references for IdmlStory objects,
     * which are XML documents that contain paragraph and character ranges that contain text. The key to the
     * associative array is the story's UID. 
     * @var array<IdmlStory>
     */
    public $stories;
    
    /**
     * is an associative array of shared links, a list of IdmlHyperlink objects
     * @var array<IdmlHyperlink>
     */
    public $hyperlinks;

    /**
     * an array whose keys will hold ids for elements that should be 'hidden' or ignored entirely during processing
     * based upon designer's setting of the 'ChaucerHidden' Tag
     * @var array 
     */
    public $chaucerHidden;
    
    /** The constructor
     *  @param IdmlAssembler $idmlAssembler
     *  @param string $idmlFilename is the fully qualified filename of the IDML file
     */
    public function __construct($idmlAssembler, $idmlFilename)
    {
        $this->idmlAssembler    = $idmlAssembler;
        $this->idmlFilename     = $idmlFilename;
        $this->style            = null;
        $this->tags             = null;
        $this->graphics         = null;
        $this->masterSpreads    = array();
        $this->spreads          = array();
        $this->stories          = array();
        $this->chaucerHidden    = array();

        $this->tempDir = $this->idmlAssembler->FileManager()->getTmpPath();
    }
    

    /**
     * The destructor deletes all unzipped files
     */
    public function __destruct()
    {
        $dir = $this->tempDir;
        assert($dir != null && $dir != '' && $dir != '/');
        shell_exec( "rm -rf $dir" );
    }

    /**
     * Return parent object.
     * @return IdmlAssembler
     */
    public function parentIdmlObject()
    {
        return $this->idmlAssembler;
    }
    
    /**
     * The unzip function creates a temporary location for the enclosed XML files, and unzips them
     * making them available to processPackage.
     *
     * @throws Exception
     * 
     * @return boolean true on success, false on failure
     */
    public function unzip()
    {
        if (!file_exists($this->idmlFilename))
        {
            throw new Exception("Filename: {$this->idmlFilename} not found.");
        }

        $zip = new ZipArchive();
        if (!$zip->open($this->idmlFilename))
        {
            $this->idmlAssembler->getProgressUpdater()->setWarning("Unzipping '{$this->idmlFilename}' encountered a problem.");
            return false;
        }

        $zip->extractTo($this->tempDir);
        $zip->close();

        return true;
     }  
    
    /** The readDesignMap function obtains the filename pointers to the MasterSpreads, Resources, Spreads,
     *  Stories, and XML from the designmap, which is the manifest that contains references to the names and locations
     *  of the package's XML files. 
     * 
     * @return boolean true on success, false on failure.
     */
    public function readDesignMap()
    {
        //first read the BackStory.xml file to see if any Stories, Hyperlinks, etc. should be ignored ...
        $this->readBackingStoryAndSetHiddenIds();
        
        // The designmap contains references to everything in the package
        $designmap = $this->tempDir . '/designmap.xml';
        if (!file_exists($designmap))
        {
            $this->idmlAssembler->getProgressUpdater()->setWarning("$designmap does not exist.");
            return false;
        }
        
        // create DOMDocument and DOMXPath objects
        $doc = new DomDocument();
        $b = $doc->load($designmap);
        if ($b === false)
        {
            return false;
        }
        $xpath = new DOMXPath($doc);
        
        // Parse the preferences file, which contains the width and height of our pages
        $tags = $xpath->query('//idPkg:Preferences');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        $this->preferences = new IdmlPreferences();
        $this->preferences->load($filename);
        IdmlAssembler::updateBookSize($this->preferences->pageWidth, $this->preferences->pageHeight);

        // The Declaration Manager deals with declared Colors, declared Styles, and declared Style Groups
        $manager = IdmlDeclarationManager::getInstance();

        // Parse the graphics file which contains color declarations (this must be done before parsing the Styles)
        $tags = $xpath->query('//idPkg:Graphic');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        $manager->loadDeclaredColors($filename);

        // There should always be exactly one Styles file   *** Version 2: non-PEARSON ***
        $tags = $xpath->query('//idPkg:Styles');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        $manager->loadDeclaredStyles($filename);

/*        // There should always be exactly one Styles file    *** Version 1: PEARSON PXE ***
        $tags = $xpath->query('//idPkg:Styles');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        $this->style = new IdmlStyles($this);
        $this->style->filename = $filename;
        $this->style->load();
*/
        // Parse fonts file.
        $tags = $xpath->query('//idPkg:Fonts');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        IdmlFontManager::getInstance()->loadFonts($filename);
        
        // There should always be exactly one Tags file
        $tags = $xpath->query('//idPkg:Tags');
        assert( $tags->length == 1 );
        $attr = $tags->item(0)->attributes->getNamedItem('src');
        $filename = "{$this->tempDir}/{$attr->value}";
        $this->tags = new IdmlTags($this);
        $this->tags->filename = $filename;
        $this->tags->load();

        // There are possibly several MasterSpread files, which are referenced by real spreads and accessed by their UID's.
        $tags = $xpath->query('//idPkg:MasterSpread');
        foreach($tags as $tag)
        {
            $attr = $tag->attributes->getNamedItem('src');
            $filename = "{$this->tempDir}/{$attr->value}";
            $matches = null;
            $found = preg_match( '|MasterSpreads/MasterSpread_(.*)\.xml|', $attr->value, $matches );       // u154 <-- MasterSpreads/MasterSpread_u154.xml
            if($found == 1)
            {
                $UID = $matches[1];
                $masterSpread = new IdmlMasterSpread($this);
                $masterSpread->filename = $filename;
                $this->masterSpreads[$UID] = $masterSpread;
            }
            else
            {
                $this->idmlAssembler->getProgressUpdater()->setWarning("Unable to determine UID for MasterSpread '{$attr->value}'.");
            }
        }
               
        // There are most likely many Spread files, and they are ordered in the designmap
        // according to the book's page ordering.
        $tags = $xpath->query('//idPkg:Spread');
        foreach($tags as $tag)
        {
            $attr = $tag->attributes->getNamedItem('src');
            $filename = "{$this->tempDir}/{$attr->value}";
            $spread = new IdmlSpread($this);
            $spread->filename = $filename;
            $this->spreads[] = $spread;
        }

        // There are most likely many Story files, which are referenced by TextFrames and accessed by a parentStoryUID.
        $tags = $xpath->query('//idPkg:Story');
        foreach($tags as $tag)
        {
            $attr = $tag->attributes->getNamedItem('src');
            $filename = "{$this->tempDir}/{$attr->value}";
            $matches = null;
            $found = preg_match( '|Stories/Story_(.*)\.xml|', $attr->value, $matches );       // u154 <-- Stories/Story_u154.xml
            if($found == 1)
            {
                $UID = $matches[1];
                if (!array_key_exists($UID, $this->chaucerHidden)) {//create/store the story if it is not hidden by a designer
                    $story = new IdmlStory($UID);
                    $story->filename = $filename;
                    $this->stories[$UID] = $story;
                }
            }
            else
            {
                $this->idmlAssembler->getProgressUpdater()->setWarning("Unable to determine UID for Story '{$attr->value}'.");
            }
        }
        
        //Parse hyperlink information
        $this->hyperlinks = array();
        $tags = $xpath->query('//Hyperlink');
        $hyperlinkMgr = IdmlHyperlinkManager::getInstance();

        foreach($tags as $tag)
        {
            $properties = IdmlParserHelper::getAllDomNodeAttributesAndProperties($tag);

            // This code is (we think) PXE processing code
            if (!array_key_exists($properties['Self'], $this->chaucerHidden))
            {
                // create/store the hyperlink if it is not hidden by a designer
                // The $destNode can be either a HyperlinkURLDestination or a HyperlinkPageDestination in the IDML,
                //   and is referenced by the Hyperlink element.
                $destId = $properties['Destination'];
                $destNode = $xpath->query('//*[@Self="'.$destId.'"]');

                if($destNode->length>0)
                {
                    $destNode = $destNode->item(0);
                    $properties['Destination'] =  IdmlParserHelper::getAllDomNodeAttributesAndProperties($destNode);
                    $properties['Destination']['DestinationType'] = $destNode->nodeName;                
                }
                $this->hyperlinks[$properties['Self']] = $properties;
            }

            // The remainder of this foreach is specifically written for non-PXE IDML
            $hyperlinkSource = $properties['Source'];
            $external = false;  // unless the link goes to a page outside the epub

            // If the Destination property is an array, the destination is a link to either
            // a page within the epub (with the page UID in the data), or or an external link.
            // So the destination can be set here.
            if (is_array($properties['Destination']))
            {
                $anchor = false;  // link is to a page, not an anchor

                if ($properties['Destination']['DestinationType'] == 'HyperlinkURLDestination')
                {
                    // External page: use as is
                    $hyperlinkDestination = $properties['Destination']['DestinationURL'];
                    $external = true;  // This links to a page outside the epub
                }
                else
                {
                    // Internal page. Use the page UID; add the package index and the .html suffix
                    $hyperlinkDestination = $properties['Destination']['DestinationPage'];
                }
            }

            // If the Destination property is not an array, the link goes to an anchor tag.
            // The destination will need to be determined during HTML production.
            else
            {
                $anchor = true;  // link is to an anchor on a page.
                $hyperlinkDestination = 'Hyperlink_' . $properties['DestinationUniqueKey'];
            }
            $hyperlinkMgr->setSourceDestination($hyperlinkSource, $hyperlinkDestination, $anchor, $external);
        }

        //Parse layer information
        $layers = $xpath->query('//Layer');
        $layerManager = IdmlLayerManager::getInstance();

        foreach($layers as $layer)
        {
            $layerManager->addLayer($layer);
        }

        return true;
    }

    /**
     * inside the BackingStory there may be a reference to a story which contains information about elements that should be hidden ... 
     * we need to keep track of these to avoid processing hidden stories and other elements that we want to hide
     * 
     * overall strategy: build a list of Ids of idml package parts that should be hidden (Stories, TextFrames, Groups, etc.) so the list can be 
     * referenced as needed before we parse the various parts
     * 
     * @return boolean
     */
    public function readBackingStoryAndSetHiddenIds()
    {
        $backingStory = $this->tempDir . '/XML/BackingStory.xml';
        
        if (!file_exists($backingStory))
        {
            $this->idmlAssembler->getProgressUpdater()->setWarning($backingStory." does not exist.");
            CakeLog::debug("[IdmlPackage::readBackingStoryAndSetHiddenIds] File $backingStory does not exist");
            return false;
        }

        // create DOMDocument and DOMXPath objects
        $doc = new ChaucerDomDocument();
        $b = $doc->load($backingStory);
        if ($b === false)
        {
            return false;
        }
        $xpath = new DOMXPath($doc);
        
        $referenceTags = array('XMLTag/etmfile', 'XMLTag/pxe', 'XMLTag/PXE'); //use an array because we might add to this
        $t = 0;
        $tags = $xpath->query("//XMLElement[@MarkupTag='".$referenceTags[$t]."']"); 
        $t++;
        while(($tags->length < 1) && ($t < count($referenceTags)))
        {
            $tags = $xpath->query("//XMLElement[@MarkupTag='".$referenceTags[$t]."']"); 
            $t++;
        }
        
        for($i = 0; $i < $tags->length; $i++)
        {
            $tag = $tags->item($i);
            $xmlContent = $tag->attributes->getNamedItem('XMLContent');
            if($xmlContent != null)
            {
                $hiddenInfoStory = $xmlContent->nodeValue;
                $this->readXmlContentStory($hiddenInfoStory);
            }
        }

        return true;
    }
    
    /**
     * read the story that contains the information about hidden content
     * @param string $backingStoryId
     * @return boolean
     */
    private function readXmlContentStory($backingStoryId)
    {
        $xmlContentStory = $this->tempDir . '/Stories/Story_'.$backingStoryId.'.xml';
        if (!file_exists($xmlContentStory))
        {
            $this->idmlAssembler->getProgressUpdater()->setWarning($xmlContentStory." does not exist.");
            return false;
        }
        // create ChaucerDOMDocument and DOMXPath objects
        $doc = new ChaucerDomDocument();
        $b = $doc->load($xmlContentStory);
        if ($b === false)
        {
            return false;
        }
        $xpath = new DOMXPath($doc);
        
        $hiddentags = $xpath->query("//XMLElement[@MarkupTag='XMLTag/ChaucerHidden']");
        
        for($i = 0; $i < $hiddentags->length; $i++) 
        {
            $tag = $hiddentags->item($i);
            $this->iterativelyGetHiddenIds($tag, $this->chaucerHidden);
        }
        return true;
    }
    
    /**
     * iterate through all child nodes of an XMLTag/ChaucerHidden XMLElement and grab their Ids so we can hide them by Id
     * this is necessary at this level for stories and items that may not be placed in the pages ...
     * @param DomElement $node
     * @param array $chaucerHidden
     */
    private function iterativelyGetHiddenIds($node, &$chaucerHidden) 
    {
        /*@var $node DomElement*/
        $ignoreId = $node->attributes->getNamedItem('XMLContent');
        if($ignoreId)
        {
            //we have an id to add to the ignored object ids ...
            $chaucerHidden[$ignoreId->nodeValue] = 1;
        }
        if($node->nodeName != 'XMLElement' || 
                ($node->nodeName == 'XMLElement' && $node->attributes->getNamedItem('MarkupTag')->nodeValue != 'XMLTag/ChaucerHidden') )
        {
            $nodeSelfId = $node->attributes->getNamedItem('Self');
            if($nodeSelfId)
            {
                $chaucerHidden[$nodeSelfId->nodeValue] = 1;
            }
        }
        foreach($node->childNodes as $childNode) {
            if($childNode->nodeType == XML_ELEMENT_NODE) {
                $this->iterativelyGetHiddenIds($childNode, $chaucerHidden);
            }
        }
    }

    /**
     * get Hyperlink based on the source attribute
     * @param string $source
     * @return array
     */
    public function getHyperlinkBySource($source)
    {
        foreach($this->hyperlinks as $id=>$properties)
        {
            if(array_key_exists('Source',$properties) && $properties['Source']==$source)
            {
                return $this->hyperlinks[$id];
            }
        }
        return array();
    }
    
    /** Quickly scan the spreads to determine how many pages are in this package. This is suitable for use
     *  by IdmlAssembler::preparation(), but not by IdmlAssembler::assembler().
     * 
     *  @return int $pageCount
     */
    public function determinePageCount()
    {
        $pageCount = 0;
        
        // The designmap contains references to everything in the package
        $designmap = $this->tempDir . '/designmap.xml';
        if (!file_exists($designmap))
        {
            $this->idmlAssembler->getProgressUpdater()->setWarning("$designmap does not exist.");
            return false;
        }
        
        // create DOMDocument and DOMXPath objects
        $doc = new DomDocument();
        $b = $doc->load($designmap);
        if ($b === false)
        {
            return false;
        }
        $xpath = new DOMXPath($doc);

        // There are most likely many Spread files, and they are ordered in the designmap
        // according to the book's page ordering.
        $tags = $xpath->query('//idPkg:Spread');
        foreach($tags as $tag)
        {
            $attr = $tag->attributes->getNamedItem('src');
            $filename = "{$this->tempDir}/{$attr->value}";
            $obj = new IdmlSpread($this);
            
            $pageCount += $obj->determinePageCount($filename);
        }
        
        return $pageCount;
    }
      
    
    /** The parse function is the starting point for parsing the package.
     */
    public function parse()
    {
        // Read the master spreads
        foreach( $this->masterSpreads as $idmlSpread )
        {
            $idmlSpread->load();
        }
        
        // Read the spreads and construct the pages (and determine their position on the spread).
        // Iterate over each page determining which frames and stories fall within the page bounds, add the
        // master spread items, then chase the spread links into the stories, reading the individual
        // stories (where the stories themself are responsible for paragraph and character ranges)
        foreach( $this->spreads as $idmlSpread)
        {
            $idmlSpread->load();
        }
    }
    
    /**
     * Load story with specific id.
     *
     * @param $storyUID is the InDesign unique ID of the story to be parsed
     *
     * @return IdmlStory returns a pointer to the story object, parsed into memory. 
     */
    public function loadStory($storyUID)
    {
        $story = $this->lookupStory($storyUID);
        if ($story == null)
        {
            return null;
        }
        
        $story->load();
        return $story;
    }

    /** The lookupStory function is called by IdmlSpread when it encounters a <TextFrame> in a <idPkg:Spread>.
     *  Use this function to find the IdmlStory that has the given $UID.
     * 
     * @param string $UID is the InDesign unique identifier given to the story, like 'ue5'
     * 
     * @return IdmlStory|null Returns the IdmlStory with the given $UID.
     * This function typically will not return null and those cases should be treated as runtime errors.
     */
    public function lookupStory($UID)
    {
        if(array_key_exists($UID, $this->stories))
        {
            $idmlStory = $this->stories[$UID];
            return $idmlStory;
        }
        else
        {
            CakeLog::debug("[IdmlPackage::lookupStory] Unable to find story '$UID'");
            return null;
        }
    }

    
    /* This accept function is called by the parent IdmlAssembler.
     * 
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth)
    {
        $visitor->visitPackage($this, $depth);


        // DEBUG only:  This will produce a single page per masterspread page
/*
        foreach($this->masterSpreads as $masterSpread)
        {
            if (IdmlAssembler::getInstance()->isFixedLayout())
                $masterSpread->accept($visitor, $depth+1);
        }
*/
        // end DEBUG

        foreach($this->spreads as $spread)
        {
            $spread->accept($visitor, $depth+1);
        }

        $visitor->visitPackageEnd($this, $depth);
    }

    /**
     * @param $UID string the InDesign unique ID of the applied master to look for
     * @return IdmlSpread a pointer to the master page for this page, or null
     */
    public function getMasterSpread($UID)
    {
        if ($UID == 'n')
            return null;

        if (array_key_exists($UID, $this->masterSpreads))
            return $this->masterSpreads[$UID];
        else
            return null;
    }
}

?>
