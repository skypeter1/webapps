<?php

/**
 * @package /app/Import/Idml/IdmlAssembler.php
 *
 * @class   IdmlAssembler
 *
 * @description This class assembles one or more InDesign Markup Language files into a Chaucer-ready book
 *          stored in the database as a collection of HTML documents with one master CSS file.
 *          External resources like fonts, images, etc., provided through the user-interface, are cataloged
 *          and saved to the database, and references to these catalog entries are available via this assembler.
 *          Conceptually this class is an in-memory representation of the IDML files and associated resources as they
 *          are being parsed.
 *
 *          The bulk of the process here is concerned with parsing the XML spreads and stories that comprise
 *          the book. Before that begins, a pre-processing stage does a quick scan of the spreads to determine
 *          the overall dimensions of each page, and the total number of pages in the final book.
 *
 *          When parsing is finished, and everything is in a complete state, the in-memory structures are used to
 *          produce HTML and CSS.
 *
 *          Finally, before the memory structures are torn down, the post-assembly stage will request the creation of
 *          thumbnail images for each page.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('ProgressUpdater', 'Lib/Common');
App::uses('IdmlProcessor',                  'Import');
App::uses('IdmlVisitable',                  'Import/Idml');
App::uses('IdmlPackage',                    'Import/Idml');
App::uses('IdmlResourceManager',            'Import/Idml/Resources');
App::uses('IdmlPxeProducer',                'Import/Idml/Pxe');
App::uses('IdmlDeclarationManager',         'Import/Idml/Styles/Declarations');
App::uses('IdmlVisitor',                    'Import/Idml');
App::uses('IdmlProduceFixedLayout',         'Import/Idml/Html');
App::uses('IdmlProduceFixedLayoutPxe',      'Import/Idml/Html');
App::uses('IdmlProduceReflowable',          'Import/Idml/Html');
App::uses('IdmlProduceReflowablePxe',       'Import/Idml/Html');
App::uses('IdmlProduceHtmlDiagnostic',      'Import/Idml/Html');


class IdmlAssembler implements IdmlVisitable
{
    /** @var IdmlAssembler $instance */
    private static $instance = null;

    /** @var IdmlProcessor instance of IDML processor */
    private $processor;
    
    /** @var ProgressUpdater $progressUpdater is used to report status information to the user */
    private $progressUpdater;

    /** @var array[IdmlPackage] $idmlPackages : An indexed collection of IdmlPackage objects, which are IDML files.
     *  There may only be one of these, or there may several of these if the author has chosen to separate
     *  each individual chapter into its own IDML file. */
    public $idmlPackages;

    /**
     * Num pages.
     * @var integer $numPages which is the number of IdmlPage objects initially found when reading the manifest.
     */
    public $numPages;

    /**
     * Actual number of pages found in idml. Might not be 1-1 with num pages in case of reflowables and
     * linked text frames.
     * 
     * @var int
     */
    public $actualPages;
    
    /** @var string $pageProgression : Do pages, from 1 to N, increase in the forward or backward direction
     *  'left-to-right' for most languages, 'right-to-left' for Chinese, Japanese, Korean */
    public $pageProgression;

    /** @var boolean $facingPages : true for two-page spreads, false for single-page spreads, this comes from
     *  the IDML Package's <FacingPages> tag */
    public $facingPages;

    /** @var integer $currentIDMLPackageIndex : an index, into the array of idmlPackages, of which IDML file
     *  is currently being processed */
    public $currentIDMLPackageIndex;

    /**
     * Idml resource manager. Used for processing images and other objects that might create additional assets
     * for the book. It is created during construction.
     * @var IdmlResourceManager
     */
    public $resourceManager;
    
    public $pxeTagsAdded;
    
    public $FileManager;

    /**
     *  The constructor must be called only by IDMLProcessor. All others should use getInstance()
     *  and should treat this constructor as if it were "private".
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Get Parent of this assembler.
     * @return IdmlProcessor
     */
    public function parentIdmlObject()
    {
        return $this->processor;
    }

    /**
     * Initialize this instance.
     * 
     * @param IdmlProcessor $processor
     * @return None
     */
    public function init(IdmlProcessor $processor = null)
    {
        $this->processor = $processor;
        if ($processor)
        {
            $this->progressUpdater = ProgressUpdater::getInstance($processor->bookId);
            $this->FileManager = new FileManager($processor->bookId);
            $this->resourceManager = new IdmlResourceManager($processor, $this->FileManager);
        }
        else
        {
            $this->progressUpdater = null;
            $this->resourceManager = null;
            $this->FileManager = null;
        }

        $this->idmlPackages = array();
        $this->styles = array();
        $this->numPages = 0;
        $this->actualPages = 0;
        $this->pageProgression = 'left-to-right';
        $this->facingPages = true;
        $this->currentIDMLFileIndex = 0;
        $this->currentIDMLPackageIndex = 0; //set this as default for unit testing purposes
        IdmlDeclarationManager::resetInstance();
    }
    
    /**
     * This is a modified Singleton, allowing classic access to the one and only $instance.
     * 
     */
    public static function getInstance($persist=true)
    {
        if (self::$instance == null)
        {
            self::$instance = new IdmlAssembler();
        }
        elseif(!$persist)
        {
           self::$instance = false;
           self::$instance = new IdmlAssembler(); 
        }
        
        return self::$instance;
    }
  
    /** The getBookId function should be available to all subordinate objects
     *  @return integer
     */
    public function getBookId()
    {
        return $this->processor->getBookId();
    }

    /**
     * Is this book reflowable?
     * @return boolean return true by default if no information can be retrieved.
     */
    public function isReflowable()
    {
        if ($this->processor)
            return self::$instance->processor->bookData->getIsReflowable();
        else
            return true;
    }

    /**
     * Is this book fixed layout?
     * @return boolean return true by default if no information can be retrieved.
     */
    public function isFixedLayout()
    {
        return !$this->isReflowable();
    }

    /**
     * Update book size. 
     * @param int $bookWidth
     * @param int $bookHeight
     */
    public static function updateBookSize($bookWidth, $bookHeight)
    {
        self::$instance = IdmlAssembler::getInstance();
        if (self::$instance && self::$instance->processor)
        {
            return self::$instance->processor->updateBookSize($bookWidth, $bookHeight);
        }

    }
    
    /** The getProgressUpdater function 
     * 
     *  @return ProgressUpdater
     */
    public static function getProgressUpdater()
    {
        self::$instance = IdmlAssembler::getInstance();
        if (self::$instance)
        {
            return self::$instance->progressUpdater;
        }

        return null;
    }

    /**
     * Get the current IdmlProcessor object
     * @return IdmlProcessor object
     */
    public static function getProcessor()
    {
        self::$instance = IdmlAssembler::getInstance();
        if(self::$instance);
        {
            return self::$instance->processor;
        }

        return null;
    }
    
    public function FileManager()
    {
        return $this->FileManager;
    }
    
    /** 
     *  Add one or more IDML files to the the assembly. It is important that the order how you add packages will be exact when
     *  processing starts.
     *
     *  @param array|string $idmlFilenames : These are fully qualified filenames with paths
     */
    public function addIDMLPackages($idmlFilenames)
    {
        if(!is_array($idmlFilenames))
        {
            CakeLog::debug('[IdmlAssembler::addIDMLPackages] Adding file '.$idmlFilenames);
            $this->idmlPackages[] = new IdmlPackage($this, $idmlFilenames);
        }
        else
        {
            sort($idmlFilenames);
            foreach($idmlFilenames as $idmlFilename)
            {
                CakeLog::debug('[IdmlAssembler::addIDMLPackages] Adding file '.$idmlFilename);            
                $this->idmlPackages[] = new IdmlPackage($this, $idmlFilename);
            }
        }
    }
    
    /** The preparation function should be called before calling Assemble() */
    public function preparation()
    {
        foreach($this->idmlPackages as $package)
        {
            $package->unzip();

            // Check the first package for PXE tags. We assume that all of the IDML files
            // have been prepared the same way, either with or without the "pxe" root.
            if ($package === $this->idmlPackages[0])
            {
                $this->pxeTagsAdded = $this->isThisPxe($package);
            }

            $package->readDesignMap();
            $this->numPages += $package->determinePageCount();
        }

        if ($this->processor)
        {
            $this->processor->updatePageCount($this->numPages);
        }

        // We are incrementing progress, per page, in three phases.
        //    One phase is parsing. (numPages)
        //    One phase is producing. (numPages)
        //    One phase is thumbnail creation. (actualPages)
        if ($this->progressUpdater)
        {
            $maxSteps = (ProgressUpdater::WEIGHTING_IDML_PHASE1 * $this->numPages) +
                        (ProgressUpdater::WEIGHTING_IDML_PHASE2 * $this->numPages) +
                        (ProgressUpdater::WEIGHTING_IDML_PHASE3 * $this->numPages);     // actual pages is not yet knwon
            $this->progressUpdater->startProgress($maxSteps);
        }
        
        return true;
    }

    /**
     * @param IdmlPackage $package - first Idml package in the project
     * @return bool - true iff the package contains PXE tags
     */
    protected function isThisPxe(IdmlPackage $package)
    {
        $filePath = $package->tempDir . '/XML/BackingStory.xml';
        if (!file_exists($filePath))
            return false;
            
        $xml = simplexml_load_file($filePath);
        $matches = $xml->xpath('//idPkg:BackingStory/XmlStory/XMLElement[@MarkupTag="XMLTag/pxe"]');
        if (count($matches) > 0)
            return true;

        $matches = $xml->xpath('//idPkg:BackingStory/XmlStory/ParagraphStyleRange/XMLElement[@MarkupTag="XMLTag/pxe"]');
        if (count($matches) > 0)
            return true;

        $matches = $xml->xpath('//idPkg:BackingStory/XmlStory/ParagraphStyleRange/CharacterStyleRange/XMLElement[@MarkupTag="XMLTag/pxe"]');
        if (count($matches) > 0)
            return true;

        return false;
    }


    public function adjustMaxSteps($additionalSteps=0)
    {
        $maxSteps = (ProgressUpdater::WEIGHTING_IDML_PHASE1 * $this->numPages) +
                    (ProgressUpdater::WEIGHTING_IDML_PHASE2 * $this->numPages) +
                    (ProgressUpdater::WEIGHTING_IDML_PHASE3 * $this->actualPages) +
                    $additionalSteps;
        $this->progressUpdater->adjustMaxSteps($maxSteps);
    }
            
    
    /** The parse function is the starting point for parsing the package(s) into memory.
     *  Prior to beginning, all IDML files, font files, and resource files should already be added.
     */
    public function parse()
    {
        for ($i = 0; $i < count($this->idmlPackages); $i++)
        {
            $this->currentIDMLPackageIndex = $i;    // this is needed by getCurrentPackage()
            $this->idmlPackages[$i]->parse();
        }
    }

    /**
     * During parsing, a TextFrame needs to know which package is currently being processed
     */
    public function getCurrentPackage()
    {
        if (array_key_exists($this->currentIDMLPackageIndex, $this->idmlPackages))
        {
            $package = $this->idmlPackages[$this->currentIDMLPackageIndex];
            $class = get_class($package);
            if (substr($class, 0, 16) == 'Mock_IdmlPackage') $class = 'IdmlPackage';
            assert($class == 'IdmlPackage');
            return $package;
        }

        return null;
    }
    
    
    /**
     * After parsing and visiting, we need to get to the package (styles, colors, etc.) while producing html
     * If we store the currentPackageIndex in each element, then the original package can be recalled from the Assembler
     * for each element as needed
     */
    public function getPackageByIndex($IDMLPackageIndex)
    {
        if (array_key_exists($IDMLPackageIndex, $this->idmlPackages))
        {
            $package = $this->idmlPackages[$IDMLPackageIndex];
            assert(get_class($package) == 'IdmlPackage');
            return $package;
        }

        return null;
    }

    /**
     * function to get the correct HTML Producer object for this processing task
     */
    public function getHtmlProducer()
    {
        if (self::isReflowable())
        {
            if($this->pxeTagsAdded)
            {
                return new IdmlProduceReflowablePxe();
            }
            else
            {
                return new IdmlProduceReflowable();
            }
        }
        else
        {
            if($this->pxeTagsAdded)
            {
                return new IdmlProduceFixedLayoutPxe();
            }
            else
            {
                return new IdmlProduceFixedLayout();
            }
        }        
    }

    /** The produce function is the entry point for producing the HTML and CSS and inserting it into the database.
     *
     */
    public function produce()
    {
        $pxeParser = new IdmlPxeProducer();
        $htmlProducer = $this->getHtmlProducer();


        if($this->pxeTagsAdded)
        {
            // Do this in two passes.
            $this->accept($pxeParser);
            $this->accept($htmlProducer);
        }
        else
        {
            $this->accept($htmlProducer);
        }

        if(Configure::read('dev.idmlHtmlDebugOutput')==true)
        {
            //DEBUG
            $this->accept(new IdmlProduceHtmlDiagnostic());
            //DEBUG
        }
        
        if ($this->processor)
        {
            $this->processor->updatePageCount($this->actualPages);
        }
    }
    
    /* This accept function can be called by diagnostic classes that want to traverse the hierarchy.
     * It calls the IdmlVisitor passing a reference to this object.
     * All items subordinate to this are then iterated through.
     * @param IdmlVisitor $visitor
     * @param $depth is how deep in the traversal we are
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitAssembler($this, $depth);
        
        for ($i = 0; $i < count($this->idmlPackages); $i++)
        {
            $this->currentIDMLPackageIndex = $i;                  // this is needed by getCurrentPackage()
            $this->idmlPackages[$i]->accept($visitor, $depth+1);
        }

        // Replace hyperlink hrefs
        $visitor->fixHyperlinkPageNames($this->processor->epub);

        $visitor->visitAssemblerEnd($this, $depth);
    }
}

?>
