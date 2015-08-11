<?php

App::uses('ProgressUpdater', 'Lib/Common');
App::uses('ImportProcessor',    'Import');
App::uses('IdmlAssembler',      'Import/Idml');
App::uses('FontInspector',      'Import/Idml/Fonts');


/**
 * IDML Processor is the class that is responsible for processing InDesign book sources. 
 * It will gather the source files, parse them, update the database. Register the assets
 * create the pages, default CSS and signal the book is ready. Also it will generate thumbnails.
 */
class IdmlProcessor extends ImportProcessor
{
    /**
     * $startCPUTime and $elapsedCPUTime are for monitoring the execution of long running IDMLs
     * @var float $startCPUTime in microseconds
     * @var float $elapsedCPUTime in microseconds
     */
    public $startCPUTime; 
    public $elapsedCPUTime; 

    /**
     * $startWallTime and $elapsedWallTime are for monitoring the execution of long running IDMLs
     * @var float $startWallTime in seconds
     * @var float $elapsedWallTime in seconds
     */
    public $startWallTime; 
    public $elapsedWallTime;

    /**
     * Constructor
     */
    public function __construct($bookId)
    {
        parent::__construct($bookId);

        $this->startCPUTime = microtime(true);
        $this->elapsedCPUTime = microtime(true) - $this->startCPUTime;
        $this->startWallTime = time();
        $this->elapsedWallTime = time() - $this->startWallTime;
    }
    
    public function isValidSourceFile($sourceFileName) {
        return (strtolower(pathinfo($sourceFileName, PATHINFO_EXTENSION)) == 'idml');
    }    

    public function parentIdmlObject()
    {
        return null;
    }

    /**
     * Primary processor
     * @return bool|void
     */
    public function process()
    {
        // Set parent process
        parent::process();

        // Validate the book source type
        $this->checkBookSourceType(self::BOOK_SOURCE_TYPE_IDML);

        // Get book source files
        $this->getBookSourceFiles();

        // Initialize ePub structure
        $this->initLocalEpub();

        // Start up the routine
        $rc = $this->processBook();

        // Copy the image files, both those uploaded by the user, and those created by the IDML processing, to the ePub and its manifest
        $this->importSourceAssets();

        $this->setCoverImage();

        // Update ePub files
        $this->epub->updateEpubFiles();

        // Copy book to S3
        $this->publishBookDir();

        // Generate thumbnails
        $this->generateThumbnails(1, $this->getPageCount());

        // Set process complete
        $this->processComplete();

        // Return success
        return $rc;
    }


    public function processBook()
    {
        $idmlAssembler = IdmlAssembler::getInstance();
        $idmlAssembler->init($this);
        $idmlAssembler->addIDMLPackages($this->sourceFiles);
        $idmlAssembler->preparation();
        $idmlAssembler->parse();
        $idmlAssembler->produce();

        // Saving the book CSS here is different from the original PXE implementation.
        // It clobbers the template.css for PXE created by the old (now obsolete) Style Manager.
        if(!$idmlAssembler->pxeTagsAdded)
        {
            $bookCss = IdmlDeclarationManager::getInstance()->convertIdmlToCSS();
            $this->saveBookCSS($bookCss, 'template.css', false);
        }

        $idmlAssembler->adjustMaxSteps();
        return true;
    }

    /**
     * import font file
     * @param string $fontFilePath to import
     * @return int Book Font ID
     */
    public function addFontToBook($fontFilePath, $assetId=null)
    {
        // Remove spaces from font file name
        $newFontFilePath = str_replace(' ', '', $fontFilePath);
        if ($newFontFilePath != $fontFilePath)
        {
            $this->FileManager->move($fontFilePath, $newFontFilePath);
        }
        $names = FontInspector::inDesignFontNames($newFontFilePath);
        if ($names['success'] == false)
        {
            CakeLog::error('[IdmlProcessor::addFontToBook] Unable to read font file '.$newFontFilePath);
            return false;
        }

        $fontName = $names['fullFontName'];
        $fontFamily = $names['fontFamily'];
        $fontStyle = $names['fontVariant'];
        $result = $this->epub->importFontFile($newFontFilePath, $fontName, '', '', '', $assetId);
        return $result['asset-id'];
    }

    /*
     * remove the reference to the original S3 file (that the user uploaded), and replace it with the newly converted filename
     * @param $oldFilename (only the basename is used for matching purposes)
     * @param $newFilename is a full path and filename, on the local computer
     */
    public function patchSourceAssetList($oldFilename, $newFilename)
    {
        $basename = pathinfo($newFilename, PATHINFO_BASENAME);
        $extension = pathinfo($newFilename, PATHINFO_EXTENSION);

        $lookFor = pathinfo($oldFilename, PATHINFO_BASENAME);
        if (count($this->sourceAssets) > 0)
        {
            foreach ($this->sourceAssets as $localFilePath => $sourceAsset)
            {
                if (strpos($localFilePath, $lookFor) !== false)
                {
                    unset($this->sourceAssets[$localFilePath]);
                    $this->sourceAssets[$newFilename] = $basename;
                    break;
                }
            }
        }
    }

    /**
     * @param string $fileToImport full path -- on the local computer -- to the file to convert
     * @return string $basename of the converted file.
     */
    public function convertUnsupportedImageType($fileToConvert)
    {
        // convert tif files to jpg
        $newFilename = $this->MediaManager()->convertUnsupportedImageType($fileToConvert);

        $this->patchSourceAssetList($fileToConvert, $newFilename);

        $basename = pathinfo($newFilename, PATHINFO_BASENAME);
        return $basename;
    }

    /*
     * Add a new file to the asset list.
     * This is needed by IDML when a CDATA section contains the actual contents of an image.
     * In this scenario, the user does not upload a separate image file.
     * @param $newFilename is a full path and filename, of the binary image extracted from the CDATA section
     */
    public function appendToSourceAssetList($newFilename)
    {
        $basename = pathinfo($newFilename, PATHINFO_BASENAME);
        $extension = pathinfo($newFilename, PATHINFO_EXTENSION);

        $this->sourceAssets[$newFilename] = $basename;
    }


}
