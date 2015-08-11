<?php
App::import('Vendor', 'Chaucer/Common/BookData');
App::import('Vendor', 'Chaucer/Common/PageManager');
App::import('Vendor', 'Chaucer/Common/CssManager');
App::import('Vendor', 'Chaucer/Epub/EpubManager');
App::import('Vendor', 'Chaucer/Rest/RestClient');
App::import('Vendor', 'Chaucer/Rest/RestResponse');
App::import('Vendor', 'Chaucer/FileAccess/S3Sync');

App::uses('CommonProcessor', 'Lib/Common');
App::uses('ProgressUpdater', 'Lib/Common');
App::uses('Uuid', 'Lib/Common');

App::uses('Folder', 'Utility');
App::uses('ChChaucerAsset', 'Model');
App::uses('ChChaucerBook', 'Model');

/**
 * This is base class for all import processors.
 */
abstract class ImportProcessor extends CommonProcessor
{
    abstract public function isValidSourceFile($sourceFileName);
    
    const BOOK_SOURCE_TYPE_IMAGES = 'images';
    const BOOK_SOURCE_TYPE_PDF = 'pdf';
    const BOOK_SOURCE_TYPE_EPUB = 'epub';
    const BOOK_SOURCE_TYPE_WORD = 'word';
    const BOOK_SOURCE_TYPE_NONE = 'none';
    const BOOK_SOURCE_TYPE_IDML = 'idml';
    
    public $useBodyContentOnly = true;

    /**
     * Array of local source files
     * @var array
     */
    protected $sourceFiles;
    
    /**
     * Array of assets included with the source files
     * @var array 
     */
    protected $sourceAssets;

    public $uploadedFiles;

    /**
     * @var EpubManager 
     */
    public $epub;

    public $startPage = 0;

    /**
     * Supported image types
     * @var array
     */
    protected static $supportedImageTypes = array(
        'jpg',
        'jpeg',
        'png',
        'gif',
        'svg',
        'tif',
        'tiff',
        'psd',
        'bmp',
        'eps',
        'ai'
    );

    /**
     * Supported audio file extensions
     * @var array
     */
    protected static $supportedAudioTypes = array(
        'm4a',
        'mp3',
        'ogg'
    );

    /**
     * Supported font types
     * @var array
     */
    protected static $supportedFontTypes = array(
        'ttf',
        'otf',
        'woff',
        'eot',
        'svg',
    );

    protected static $supportedVideoTypes = array(
        'm4v',
        'mp4',
        'ogv',
        'mov',
        'mpg',
        'avi'
    );

    /**
     * Constructor
     */
    public function __construct($bookId)
    {
        parent::__construct($bookId);
        $this->epub = new EpubManager($bookId);
        $this->workingDir = $this->initWorkingDir();
        $this->startPage = $this->getPageCount();
    }

    /**
     * Initialize the EpubManager class to create a new, local book directory
     * @return string path to epub directory
     */
    protected function initLocalEpub()
    {
        $tmpDir = Configure::read('Chaucer.instanceName').'/'.$this->bookId;
        $targetDir = Folder::addPathElement($this->FileManager->getTmpPath(), $tmpDir);
        $this->epub->initEpub($targetDir.'/');
        return $this->epub->epubRoot();
    }

    /**
     * Copy the built epub directory to the storage location (generally S3)
     */
    protected function publishBookDir()
    {
        $srcDir = $this->epub->epubRoot();
        $this->FileManager->copyDirectory($srcDir, EpubManager::EPUB_ROOT, $this->progress);
    }

    /**
     * Check if book is of a proper book type
     * @param   string      Processing book type
     * @throws  Exception   Book type does not match
     */
    public function checkBookSourceType($processingBookType)
    {
        if ($this->bookData->getBookSourceType() != $processingBookType) 
        {
            throw new Exception(
                '[CommonProcessor::checkBookSourceType] Book ID ' . $this->bookId . ' is not of '
                .$processingBookType.' source type.'
            );
        }
    }

    protected function getMinimumSourceFileCount()
    {
        return 1;
    }

    /**
     * Collect all source files for processing of defined book type
     * @param   string      Book type for source collection
     * @throws  Exception   No source files found
     * @returns array       Source files
     */
    public function getBookSourceFiles()
    {
        // Check if source files already were collected
        if (empty($this->sourceFiles) || is_null($this->sourceFiles))
        {
            $this->sourceAssets = array();
            $sourceFiles = $this->uploadedFiles;
            if(count($sourceFiles)>0)
            {
                $sourceFileCount = 0;
                foreach ($sourceFiles as $sourceFile) {

                    if( $this->copySourceToWorkingDir($sourceFile))
                    {
                        $sourceFileCount++;
                    }
                }
            }
            CakeLog::debug('[CommonProcessor::getBookSourceFiles] '
                .count($this->sourceFiles).' source files gathered for bookId: '.$this->bookId
            );

            if (count($this->sourceFiles) < $this->getMinimumSourceFileCount()) {
                throw new Exception(
                    '[CommonProcessor::getBookSourceFiles] '
                    .'Unable to get source files for bookId: '.$this->bookId
                );
            }
        }

        return $this->sourceFiles;
    }

    /**
     * Copy over source file to working dir. source files have spaces and such removed, but extra files, like
     * images and fonts, do not
     * @param array $sourceFileAsset
     * @throws Exception
     * @return bool
     */
    protected function copySourceToWorkingDir($sourceFileName)
    {
        // Get source file
        $source =  $sourceFileName;
        if($this->isValidSourceFile($sourceFileName))
        {
            $target =  Folder::addPathElement($this->workingDir, FileManager::getValidFilename(basename($sourceFileName)));
            $this->sourceFiles[] = $target;
        }
        else
        {
            $target = Folder::addPathElement($this->workingDir, basename($sourceFileName));
            $this->sourceAssets[$target] = $sourceFileName;
        }
        // Log the message
        CakeLog::debug(
            '[CommonProcessor::getBookSourceFiles] Preparing to copy remote file on instance '
            .Configure::read('Chaucer.instanceName').': '.$source.' to '.$target
        );
        // Get the source file from remote
        $this->FileManager->copy($source, $target);
        if(!file_exists($target))
        {
            throw new Exception('[CommonProcessor::getBookSourceFiles] Unable to copy book source file.');
        }
        return true;
    }
    
    protected function importSourceAssets()
    {
        if(is_array($this->sourceAssets) && count($this->sourceAssets)>0)
        {
            foreach($this->sourceAssets as $localFile => $sourceFileName)
            {
                $ext = pathinfo($sourceFileName, PATHINFO_EXTENSION);
                CakeLog::debug('[ImportProcessor::importSourceAssets] processing asset '.$sourceFileName);
                if(in_array($ext, self::$supportedImageTypes))
                {
                    CakeLog::debug('[ImportProcessor::importSourceAssets] Adding image file '.$localFile.' to book');
                    $this->addImageAssetToBook($localFile, 'images/');
                }
                elseif(in_array($ext, self::$supportedFontTypes))
                {
                    CakeLog::debug('[ImportProcessor::importSourceAssets] Adding font file '.$localFile.' to book');
                    $this->addFontToBook($localFile);
                }
                elseif(in_array($ext, self::$supportedAudioTypes))
                {
                    CakeLog::debug('[ImportProcessor::importSourceAssets] Adding audio file '.$localFile.' to book');
                    $this->addAudioAssetToBook($localFile, 'audio/');
                }
                elseif(in_array($ext, self::$supportedVideoTypes))
                {
                    CakeLog::debug('[ImportProcessor::importSourceAssets] Adding video file '.$localFile.' to book');
                    $this->addVideoAssetToBook($localFile, 'video/');
                }
                else
                {
                    CakeLog::warning('[ImportProcessor::importSourceAssets] Skipping file'.$sourceFileName.': unknown file type');
                }
                $this->progress->incrementStep();
            }
        }
    }
    
    /**
     * Start processing 
     */
    public function process()
    {
        CakeLog::debug('[CommonProcessor::process] Starting book process for '.$this->bookId);
        $this->initProgress();
    }

    /**
     * Update book size.
     * @param int $bookWidth
     * @param int  $bookHeight
     */
    public function updateBookSize($bookWidth, $bookHeight)
    {
        $bookItem = $this->bookData->getBookItem(true);
        $bookItem['ChChaucerBookVersion']['book_width'] = $bookWidth;
        $bookItem['ChChaucerBookVersion']['book_height'] = $bookHeight;
        $bookItem['ChChaucerBookVersion']['book_resize_width'] = $bookWidth;
        $bookItem['ChChaucerBookVersion']['book_resize_height'] = $bookHeight;
        $this->bookData->saveBookVersion($bookItem);
    }

    /**
     * Returns the number pages in the book, according to the book version table
     * @return int
     */
    public function getPageCount()
    {
        $bookItem = $this->bookData->getBookItem(true);
        return (int)$bookItem['ChChaucerBookVersion']['book_page_count'];
    }

    /**
     * Update page count.
     * @param int $pageCount
     */
    public function updatePageCount($pageCount)
    {
        $bookItem = $this->bookData->getBookItem(true);
        $bookItem['ChChaucerBookVersion']['book_page_count'] = $pageCount;
        $this->bookData->saveBookVersion($bookItem);
    }

    /**
     * import font file
     * @param string $fontFilePath to import
     * @return int Book Font ID
     */
    public function addFontToBook($fontFilePath, $assetId=null)
    {
        $result = $this->epub->importFontFile($fontFilePath, basename($fontFilePath), basename($fontFilePath),
                                              null,null, $assetId);
        return $result['asset-id'];
    }

    /**
     * Add asset to book
     * @param string $fileToImport full path to the file to import
     * @param string $relativePath relative path in assets dir for this item. (ie: Images)
     * @return int Asset ID
     */
    public function addImageAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_IMAGE);
    }

    /**
     * Add asset to book
     * @param string $fileToImport full path to the file to import
     * @param string $relativePath relative path in assets dir for this item. (ie: styles)
     * @return string Asset ID
     */
    public function addCssAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_CSS);
    }

    /**
     * add audio file to book
     * @param $fileToImport
     * @param $relativePath
     * @return string Asset ID
     */
    public function addAudioAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_AUDIO);
    }

    /**
     * add video asset to book
     * @param $fileToImport
     * @param $relativePath
     * @return string Asset ID
     */
    public function addVideoAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_VIDEOS);
    }

    /**
     * add javascript asset to book
     * @param $fileToImport
     * @param $relativePath
     * @return string Asset ID
     */
    public function addJavaScriptAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_JS_FILES);
    }


    /**
     * add javascript asset to book
     * @param $fileToImport
     * @param $relativePath
     * @return string Asset ID
     */
    public function addMiscAssetToBook($fileToImport, $relativePath)
    {
        return $this->epub->importAsset($fileToImport, $relativePath, EpubManager::ASSET_OTHER);
    }

    /**
     * Adds a page to the beginning of the book to display the cover page. uses the book_cover field from the books
     * table to determine the image file
     *
     * @return string path to cover image. Relative in S3, absolute locally
     */
    public function setCoverImage()
    {        
        $book = $this->bookData->getBookItem();
        $coverSource = trim($book['ChChaucerBook']['book_cover']);
        $path = '';
        if(strlen($coverSource)>0)
        {
            CakeLog::debug('[ImportProcessor::addCoverPageImage] Book '.$book['ChChaucerBook']['book_id'].' has cover image: '.$coverSource);
            $path = $this->epub->addCoverImage(Folder::addPathElement(Configure::read('Chaucer.sourceDir'), $coverSource));
            $book['ChChaucerBook']['book_cover'] = $coverSource;
            $this->bookData->saveBook($book);
            return $this->epub->epubRoot().$path;
        }
        return '';
    }
    
    /**
     * 
     * @param string $cssContent
     * @param int $pageNumber
     * @param int $backgroundImageAssetId
     * @return string Asset ID
     */
    public function savePageCSS($cssContent, $pageNumber)
    {
        return $this->epub->importPageCss($cssContent, $pageNumber);
    }

    /**
     * Crestes a book CSS template class based on the book data
     * @return BookCss
     */
    protected function getBookCssTemplate()
    {
        $bookItem = $this->bookData->getBookItem(true);
        $bookTypeCode = strtolower($bookItem['ChChaucerBookType']['book_type_code']);

        $template = ROOT . DS . "vendors/Chaucer/Templates/BookCss/book_{$bookTypeCode}.css.php";

        $bookCss = new BookCss(
            $bookItem['ChChaucerBookVersion']['book_width'],
            $bookItem['ChChaucerBookVersion']['book_height'],
            $bookItem['ChChaucerPublication']['publication_language'],
            $bookItem['ChChaucerBook']['semantic_vocabulary'],
            $template
        );
        return $bookCss;
    }

    /**
     * 
     * @param string $bookCssContent
     * @param string $cssFilename CSS filename to store with css content
     * @return BookCss Bok CSS Object
     */
    public function saveBookCSS($bookCssContent, $cssFilename='template.css', $useBookTypeTemplate=true)
    {
        $bookItem = $this->bookData->getBookItem(true);
        $bookTypeCode = strtolower($bookItem['ChChaucerBookType']['book_type_code']);

        $template = ROOT . DS . "vendors/Chaucer/Templates/BookCss/book_{$bookTypeCode}.css.php";

        $bookCss = $this->getBookCssTemplate();

        $bookCss->useBookTypeTemplate = $useBookTypeTemplate;
        if(strlen($bookCssContent)>0)
        {
            $bookCss->setContent($bookCssContent);
        }
        $bookCss->fileName = $cssFilename;

        return $this->epub->importBookCss($bookCss->getContent(), $cssFilename);
    }

    /**
     * Get all content within the body tag
     * @param string $html full html page source
     * @return string
     */
    protected function getDocumentPageContent($html)
    {
        $matches = array();
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $matches);
        return $matches[1];
    }
        
    
    /**
     * 
     * @param string $pageHTML
     * @param int $pageNumber
     * @param string $title
     * @param string $backgroundImageUrl
     * @param int $spreadNumber
     * @param array $properties
     * @return Page Page Object
     */
    public function savePageHTML(
            $pageHTML,
            $pageNumber,
            $title=null,
            $backgroundImageUrl='',
            $spreadNumber=null,
            $properties=array())
    {
        // Create page for the book.
        $page = PageManager::getInstance()->createPage($this->bookId, $pageNumber, $backgroundImageUrl);
        if(!is_null($spreadNumber))
        {
            $page->setSpreadPageNumber($spreadNumber);
        }
        if(is_null($title))
        {
            $page->setTitle("Untitled"); 
        }
        else
        {
            $page->setTitle($title);
            //$page->setTitle($this->book_pub->publication_name);
        }
        if(strpos($pageHTML,"<body")===false)
        {
            $page->setTextContent($pageHTML, false);
            $pageHTML = $this->applyElementUuids($page->getPageContent());
            $page->setTextContent($pageHTML, true);
        }
        else
        {
            $pageHTML = $this->applyElementUuids(trim($pageHTML));
            $page->setTextContent($pageHTML, true);
        }

        $this->epub->importPage($page, $properties);
        return $page;
    }

    /**
     * Adds UUID data attributes to all elements in the document, skipping cdata blocks.
     * @param $html html source
     * @return string
     */
    public function applyElementUuids($html)
    {
        //remove CDATA blocks from source and replace with tokens
        $cdata = array();
        $html = preg_replace_callback(
            '/<!\[CDATA\[((?:[^]]|\](?!\]>))*)\]\]>/',
            function ($matches) use (&$cdata)
            {
                $index = count($cdata);
                $replacement = "{{{CDATA-$index"."}}}";
                $cdata[$index] = $matches[0];
                return $replacement;
            },
            $html);

        //Add uuid attribute to all tags
        $content = preg_replace_callback(
            "/(<([\w]+)[^>]*>)/",
            function ($matches)
            {
                if(strpos($matches[0], 'data-chaucer-element-id')===false)
                {
                    $id = Uuid::v4();
                    $search = '<'.$matches[2];
                    $replace = '<'.$matches[2].' data-chaucer-element-id="'.$id.'"';
                    return str_replace($search, $replace, $matches[0]);
                }
                return $matches[0];
            },
            $html);

        //Put CDATA blocks back into source
        if(count($cdata)>0)
        {
            for($x=0;$x<count($cdata);$x++)
            {
                $search="{{{CDATA-$x"."}}}";
                $content = str_replace($search, $cdata[$x], $content);
            }
        }

        return $content;
    }

    /**
     * submit the http request to the node server to generate the thumbnail
     * @param $assetHref
     * @return boolean
     */
    public function requestThumbnail($assetHref)
    {
        $url = Configure::read('Chaucer.thumbnailHost');
        if(strlen($url)>0)
        {
            $pageKey = Configure::read('Chaucer.rootFolder').$this->bookId.'/'.
                       $this->epub->epubRoot() . dirname($this->epub->EpubSpine->getContentOpfLocation()).'/'.
                       $assetHref;
            $rest = new RestClient();
            $bookItem = $this->bookData->getBookItem();
            $width = (int)$bookItem['ChChaucerBookVersion']['book_width'];
            $height = (int)$bookItem['ChChaucerBookVersion']['book_height'];
            if(!$width)
            {
                $width = 768;
                $height = 1024;
            }
            CakeLog::debug('[ImportProcessor::requestThumbnail] Requesting thumbnail from '.$url.' for path '.$pageKey.
                            ' height: '.$height.' width:'.$width);

            $response = $rest->setUrl($url)
                             ->setData(array(
                                                'path'  => $pageKey,
                                                'height'=> $height,
                                                'width' => $width,
                                                'bucket'=> Configure::read('Chaucer.s3Bucket')
                             ))
                             ->get();
            return $rest->isGoodResponse();
        }
        return false;
    }

    /**
     * @param int $startPage
     * @param int $endPage
     * @param int $progressUpdaterWeighting multiplier for the amount to increment the progress indicator each time a thumbnail is complete
     */
    public function generateThumbnails($startPage, $endPage, $progressUpdaterWeighting = ProgressUpdater::WEIGHTING_TYPICAL)
    {
        $pages = $this->epub->pageList();
        // Generate Thumbnails and update progress.
        for ($pageNum = $startPage; $pageNum <= $endPage; $pageNum++)
        {
            CakeLog::debug("[CommonProcessor::process] Generating thumbnail for page $pageNum in Book ID {$this->bookId}");
            $this->requestThumbnail($pages[$pageNum-1]->relativePath);
            $this->progress->incrementStep($progressUpdaterWeighting);
        }
        CakeLog::debug("[CommonProcessor::process] Thumbnail generation complete");
    }

    public function getSourceFiles()
    {
        return $this->sourceFiles;
    }

    public function setSourceFiles($sourceFiles)
    {
        $this->sourceFiles = $sourceFiles;
    }
}

?>
