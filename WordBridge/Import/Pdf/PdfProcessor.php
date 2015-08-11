<?php
App::uses('Folder', 'Utility');
App::uses('CommonProcessor', 'Import');
App::uses('PdfInfo', 'Import/PdfInfo');
App::uses('BackgroundProcess', 'ProcessQueue');
App::uses('ImportProcessor', 'Lib/Import');

App::import('Vendor','Chaucer/ProcessorQueue/ProcessorFactory');

/**
 * Process timeout, in seconds. Sets a timeout for the ChaucerPDF processor 
 * external process to complete
 */
const PROCESS_TIMEOUT = 300;

/**
 * Class Pdfprocessor is responsible for running the pdf conversion utility and procesing
 * the output. Register the assets create the pages, default CSS and signal the book is ready. 
 * Also it will generate thumbnails.
 *
 * @author cwalker
 */
class PdfProcessor extends ImportProcessor
{

    public $createBookCss = true;

    public $removeDuplicateFonts = false;

    /**
     * Background image type, jpg or png
     * @var string
     */
    public $backgroundImageType = 'jpg';

    /**
     * jpeg compression quality for jpg backgrounds. ignored for png
     * @var int
     */
    public $backgroundQuality = 80;

    /**
     * if true, the background image will be resized to match the book dimensions
     * @var bool
     */
    public $backgroundResize = true;

    /**
     * list of image files we have changed. $alteredImages[original] = new
     * @var array
     */
    private $alteredImages = array();

    /*
     * Location of the pdf2htmlEX binary templates, required by --data-dir
     */
    public $templatesDir;

    /*
  * @var associative array where the key is the prefixed font filename and the value is the unprefixed font filename
  */
    private $fontMapOldToNew = array();    

    /**
     * Constructor
     */
    public function __construct($bookId)
    {
        parent::__construct($bookId);
        $this->initTemplatesDir();
    }

    /*
     * The path to the templates is located under /processor/Console/Templates
     */
    public function initTemplatesDir()
    {
        $this->templatesDir = ROOT .  "/processor/Console/Templates/pdf2htmlEX";
    }

    public function isValidSourceFile($sourceFileName)
    {
        return (strtolower(pathinfo($sourceFileName, PATHINFO_EXTENSION)) == 'pdf');
    }

    /**
     * Process PDF book source 
     * - call CLI execute pdf processor: 
     *      pdf2htmlEx --embed cfijo --dest-dir <folder> --fit-width <number> --fit-height <number> --split-pages 1 <pdffile>
     * - monitor progress with progress.txt fle in directory, add a watchdog timer
     * - process output of executable
     *      - iterate to all generated pages
     *      - grab content,
     *      - import fonts
     *      - import css files
\     *
     * @return boolean
     */
    public function process()
    {
        // Get parent process
        parent::process();

        // Validate the book source type
        $this->checkBookSourceType(self::BOOK_SOURCE_TYPE_PDF);

        // Get book source files
        $this->getBookSourceFiles();

        $localBookDir = $this->FileManager->getTmpPath();


        // Initialize ePub structure
        $this->initLocalEpub();

        // Start up the routine
        $this->processBook();

        // Update ePub files
        $this->epub->updateEpubFiles();

        //copy book to S3
        $this->publishBookDir();

        // Generate thumbnails
        $this->generateThumbnails(1, $this->getPageCount());

        // Set process complete
        $this->processComplete();

        // Return success
        return true;
    }

    /**
     * Primary processing routine for the source
     */
    private function processBook()
    {
        // Run PDF Converter and get pages
        $numExpectedPages = $this->runPdfProcessor($this->sourceFiles[0]);
        if ($numExpectedPages !== false) {

            // Get pages
            CakeLog::debug('[PdfProcessor::process] Reading pages from: ' . $this->workingDir);
            $pages = $this->getPageFiles();

            // Validate number of pages
            if (count($pages) != $numExpectedPages && $numExpectedPages>0) {
                throw new Exception('PdfProcessor failed: ' . count($pages) . ' of ' . $numExpectedPages . ' processed.');
            }
            CakeLog::debug('[PdfProcessor::process] ' . count($pages) . ' pages found in: ' . $this->workingDir);

            // Create image assets
            $this->createImageAssets();

            // Process fonts
            $this->processBookFonts('Fonts');

            // Get number of pages and sort them
            $numPages = count($pages);
            sort($pages);

            $this->setCoverImage();
            $bookCssContent = '';
            $cssDirectory = $this->getPageCssContentDirectory();

            // Process pages
            foreach ($pages as $pageFile) {

                // Get page file info
                $pageFileInfo = pathinfo($pageFile);

                // Get page number
                $pageNumber = substr($pageFileInfo['filename'], 4);
                CakeLog::debug('[PdfProcessor::process] Processing page ' . $pageNumber . ' in ' . $this->workingDir);

                // Get page contents and process HTML
                $content = $this->getPageContents($pageFile);
                $pageHTML = $this->processPageHTML($content);

                // Create page for the book
                $this->savePageHTML($pageHTML, $pageNumber + $this->startPage, '', 0);

                // Save individual page CSS
                $pageCssContent = $this->getPageCssContent($cssDirectory, $pageFile);

                if($this->createBookCss)
                {
                    $this->savePageCSS($pageCssContent, $pageNumber);
                }
                else
                {
                    if(!strlen($bookCssContent))
                    {
                        $bookCssContent = $this->getBookCSS();
                        $bookCssContent = $this->removeDuplicatesInCSS($bookCssContent);
                    }
                    $pageCssContent = $this->mergeCssContent($bookCssContent,$pageCssContent, $pageHTML);
                    $this->savePageCSS($pageCssContent, $pageNumber);
                }

                // Progress the step
                $this->progress->incrementStep();
            }

            //handle any non-source assets included with the book source files
            $this->importSourceAssets();

            // Save book CSS
            if($this->createBookCss)
            {
                $this->saveBookCSS($this->getBookCSS());
            }
            else
            {
                $bookCssContent = $this->removeUnusedClassesDeclarations($bookCssContent, array());
                $this->saveBookCSS($bookCssContent);
            }

            // Update book with page count
            $this->updatePageCount($this->getPageCount() + $numPages);

        } else {

            // Throw exception if PDF converter failed
            throw new Exception('[PdfProcessor::process] ChaucerPDF Process failed.');
        }

        CakeLog::debug(
            '[PdfProcessor::process] Completed PDF Processing for book '
            . $this->bookId . ' from host '
            . Configure::read('Chaucer.instanceName')
        );
    }

    protected function getPageCssContentDirectory()
    {
        return Folder::addPathElement($this->workingDir, 'Styles/');
    }

    protected function getPageContents($pageFile)
    {
        return file_get_contents($pageFile);
    }

    protected function getPageCssContent($cssDirectory, $pageFile)
    {
        $cssFilename = $cssDirectory . basename($pageFile) . '.css';
        $cssFilename = str_replace('.html', '', $cssFilename);
        if(file_exists($cssFilename))
        {
            $pageCssContent = file_get_contents($cssFilename);
        }
        return $pageCssContent;
    }

    /**
     * Get new book dimensions
     * @param   float   Book width
     * @param   float   Book height
     */
    protected function getNewBookDimensions(&$bookWidth, &$bookHeight)
    {
        // Check if book is in landscape mode
        $landscape = ($bookHeight < $bookWidth) ? TRUE : FALSE;

        // Get book dimensions
        if ($landscape) {

             $ratio = $bookHeight / $bookWidth;
             $bookWidth = 1024;
             $bookHeight = $bookWidth * $ratio;

             if ($bookHeight > 768) {
                 $bookHeight = 768;
                 $bookWidth = $bookHeight / $ratio;
             }

        } else {

             $ratio = $bookWidth / $bookHeight;
             $bookHeight = 1024;
             $bookWidth = $bookHeight * $ratio;

             if ($bookWidth > 768) {
                 $bookWidth = 768;
                 $bookHeight = $bookWidth / $ratio;
             }
        }

        // Assign new book dimensions
        $bookWidth = (int)$bookWidth;
        $bookHeight = (int)$bookHeight;
    }

    /**
     * Get book dimensions.
     * @param   int     Book ID
     * @param   string  Path to source file
     * @return  array   Height & width of the book
     */
    public function getBookDimensions($localPDF)
    {
        // Preset vars
        $result = array();
        $bookWidth = 0;
        $bookHeight = 0;

        // Get book data by book ID
        $bookData = $this->bookData->getBookItem();

        // Check if there is information about book dimensions
        if (array_key_exists('book_width', $bookData['ChChaucerBookVersion'])) {
            $bookWidth = $bookData['ChChaucerBookVersion']['book_width'];
            $bookHeight = $bookData['ChChaucerBookVersion']['book_height'];           
        }

        // If not, get new book dimensions
        if (!$bookWidth) {
            $pdfData = PdfInfo::getInfo($localPDF);
            $bookWidth = $pdfData['Page width pts'];
            $bookHeight = $pdfData['Page height pts'];
        }

        // Construct results
        $result['width'] = (int)$bookWidth;
        $result['height'] = (int)$bookHeight;

        // Update book size dimensions
        $this->updateBookSize($bookWidth, $bookHeight);

        // Return dimensions
        return $result;
    }
    
    /**
     * Check if progress file has complete in it
     * @param   string      Full path to PDF Processor progress.txt file
     * @return  boolean     Returns true if the PDF processor has written "Complete" to the progress.txt file
     */
    public function isProgressFileComplete($file)
    {
        // Return true if "complete" is found
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $content = strtolower($content);
            if (strpos($content, "complete") !== false) {
                return true;
            }
        }

        // Return false if not
        return false;
    }
    
    /**
     * Determines the total number of steps for processing the pdf
     * @param   int     Number of pages in the book
     * @return  int     Maximum steps
     */
    public function getMaxProgressSteps($numPages)
    {
        $supportFileCount = 3;
        return ($numPages * 6) + count($this->sourceAssets) + $supportFileCount;
    }

    /**
     * Check if PDF processor is still running
     * @param   string      Full path to pdf processor progress.txt file
     * @param   string      Full path to pdf processor ErrorLog.txt file
     * @return  boolean     Returns true if the PDF processor is still running
     */
    public function isPdfProcessorRunning($progressFile, $errorLogFile)
    {        
        return ($this->isProgressFileComplete($progressFile) == false && file_exists($errorLogFile) == false);
    }
    
    /**
     * Determines the command line options for the PDF Processor binary
     * @param   float   Book width
     * @param   float   Book height
     * @return  string  Command line options
     */
    public function getPdfProcessorSwitches($width, $height)
    {
        // Check if this book is reflowable or not
        if ($this->bookData->getIsReflowable()) {

            // Construct options
            $options = '--decompose-ligature 1 --split-pages 1 --reflowable 1 --individual-images 1 --embed cfijo';
            $options .= ' --fit-width ' . $width . ' --fit-height ' . $height;
            $options .= ' --dest-dir ' . $this->workingDir;

        } else {


            // Construct options
            $options = '';
            $options .= ' --decompose-ligature 1 --split-pages 1 --embed cfijo';
            $options .= ' --fit-width ' . $width . ' --fit-height ' . $height;
            $options .= ' --dest-dir ' . $this->workingDir;
        }

        $options .= ' --data-dir '   . $this->templatesDir;


        // Return options
        return $options;
    }
    
    /**
     * Run the PDF Processor utility.
     * @return  mixed   Total pages or false if failed processing.
     */
    protected function runPdfProcessor($localPDF)
    {
        // Get Chaucer PDF converter
        $ChaucerPDF = Configure::read('processor.ChaucerPDF');
        if (strlen($ChaucerPDF) > 0) {

            if (!strlen($localPDF)) {
                throw new exception("[PdfProcessor::runPdfProcessor] PDF Processing executable not configured");
            }

            if(!file_exists($localPDF))
            {
                throw new exception("[PdfProcessor::runPdfProcessor] PDF Processor $ChaucerPDF not found");
            }

            // Get book dimensions
            $bookSize = $this->getBookDimensions($localPDF);

            // Get book processing options
            $options = $this->getPdfProcessorSwitches($bookSize['width'], $bookSize['height']);

            // Assemble PDF converter command
            $cmd = "$ChaucerPDF $options $localPDF";
            CakeLog::debug('[PdfProcessor::runPdfProcessor] Executing: ' . $cmd);

            // Set progress and error log files for PDF converter
            $progressFile = Folder::addPathElement($this->workingDir, "progress.txt");
            $errorLogFile = Folder::addPathElement($this->workingDir, "ErrorLog.txt");

            // Create and run new background process
            $pdfProc = new BackgroundProcess($cmd);
            $endTime = time() + PROCESS_TIMEOUT;
            $pdfProc->run($cmd);

            // Preset progress vars
            $maxSteps = 0;
            $lastPage = 0;
            $totalPages = 0;            

            // Get and check running process
            $bRunning = true;
            while ($bRunning && (time() < $endTime)) {

                // Get the progress
                if (file_exists($progressFile)) {

                    // Get contents of the progress file
                    $content = file_get_contents($progressFile);

                    // Check for the string 10/1020. <page>/<page_count>
                    if (strpos($content, '/') !== false) {

                        // Get page info
                        $pageInfo = explode('/', $content);

                        // Check to stat the progress
                        if (!$maxSteps) {
                            $totalPages = (int)$pageInfo[1];
                            if ($totalPages > 0) {
                                $maxSteps = $this->getMaxProgressSteps($totalPages);
                                $this->progress->startProgress($maxSteps);
                            }
                        }

                        // Update step
                        $this->progress->setCurrentStep($pageInfo[0]);
                        if ($lastPage != $pageInfo[0]) {
                            $endTime = time() + PROCESS_TIMEOUT; // reset the timeout window for the pdf processor
                            $lastPage = $pageInfo[0];
                        }

                    } else {

                        // Check if process is running
                        $bRunning = $this->isPdfProcessorRunning($progressFile, $errorLogFile);
                    }
                }
                sleep(2);
            }
            clearstatcache();

            // Check if there were any errors
            if (file_exists($errorLogFile)) {
                throw new Exception('PDF Processor Failed: ' . file_get_contents($errorLogFile));
            }
            CakeLog::debug('[PdfProcessor::runPdfProcessor] Complete: ' . $cmd);

            // Return total pages
            return $totalPages;
        }

        // Return false if PDF Converter was not detected
        return FALSE;
    }

    /**
     * Get list of page html files in working directory
     * @return  array   Page HTML files
     */
    protected function getPageFiles()
    {
        // Get all files
        $files = $this->FileManager->getAllFiles($this->workingDir);
        CakeLog::debug('[PdfProcessor::getPageFiles] Found ' . count($files) . ' files in ' . $this->workingDir);

        // Iterate through files
        $pages = array();
        foreach ($files as $file) {

            // Get path information
            $file_path = pathinfo($file);
            if (array_key_exists('extension', $file_path)) {

                // Check if this is a HTML page
                if ($file_path['extension'] == 'html') {

                    // Collect page
                    if (substr($file_path['filename'], 0, 4) == 'page') {
                        CakeLog::debug('[PdfProcessor::getPageFiles] Adding ' . $file . ' as book page.');
                        $pages[] = $file;
                    }
                }
            }
        }

        // Return pages
        return $pages;
    }

    /**
     * Add image assets to EPub.  If source images are altered, we copy the source image to the book's Source dir
     */
    protected function createImageAssets()
    {
        $bookItem = $this->bookData->getBookItem(true);
        $imageDir = Folder::addPathElement($this->workingDir, 'Images');
        if (is_dir($imageDir))
        {
            $imageFiles = $this->FileManager->getAllFiles($imageDir);
            if (count($imageFiles) > 0)
            {
                foreach ($imageFiles as $imageFilename)
                {
                    $targetFilename = $this->convertImage($imageFilename,
                                                          $bookItem['ChChaucerBookVersion']['book_height'],
                                                          $bookItem['ChChaucerBookVersion']['book_width']
                                                         );
                    if($imageFilename != $targetFilename)
                    {
                        $this->FileManager->copy($imageFilename, 'Source/'.basename($imageFilename));
                        $this->alteredImages[basename($imageFilename)] = basename($targetFilename);
                    }
                    $this->addImageAssetToBook($targetFilename, 'images/');
                }
            }
        }
    }

    /**
     * Converts the background image to the correct type and quality
     * @param $filename
     */
    protected function convertImage($filename, $bookHeight, $bookWidth)
    {
        if($this->backgroundImageType == 'png')
        {
            return $filename;
        }
        $newFilename = substr($filename, 0, -4).'.jpg';
        $img = new Imagick($filename);
        $img->setformat('jpeg');
        $img->setCompression(imagick::COMPRESSION_JPEG);
        if($this->backgroundResize)
        {
            $img->resizeImage($bookWidth, $bookHeight, Imagick::FILTER_LANCZOS,1);
        }
        $img->writeimage($newFilename);
        return $newFilename;
    }

    /**
     * import font file
     * @param string $fontFilePath to import
     * @return int Book Font ID
     */
    public function addFontToBook($fontFilePath, $assetId=null)
    {
        //We override the font family to match the css from chaucerPDF
        $fileInfo = pathinfo($fontFilePath);
        $result = $this->epub->importFontFile($fontFilePath, $fileInfo['filename'], $fileInfo['filename'],
                                              null, null, $assetId);
        return $result['asset-id'];
    }


    /**
     * Add PDF fonts to book, importing to chaucer if necessary
     * @param   string  Relative subdirectory to working dir containing font files
     * @return  array   Book font files, indexed by book_font_id
     */
    protected function processBookFonts($fontDir)
    {
        // Check if font directory exists
        $fontDir = Folder::addPathElement($this->workingDir, $fontDir);
        if (is_dir($fontDir)) {

            // Get all fonts
            $fontFiles = $this->FileManager->getAllFiles($fontDir);
            foreach ($fontFiles as $fontFilename) {

                // Add font to the book and check if font was added.
                if (!$this->addFontToBook($fontFilename)) {
                    throw new Exception('[PdfProcessor::getPageFiles] Error importing font file: ' . $fontFilename);
                }
            }
        }
    }

    /**
     * Get Book CSS by reading css/template.css and stripping the font-face rules
     * @return  string  CSS content
     */
    protected function getBookCSS()
    {
        // Get template CSS
        $file = Folder::addPathElement($this->workingDir, "Styles/template.css");
        if (file_exists($file)) {

            // Strip @font-face
            $css = file_get_contents($file);
            while (strpos($css, '@font-face') !== false) {
                $start = strpos($css, '@font-face');
                $end = strpos($css, "}", $start);
                $pre = substr($css, 0, $start - 1);
                $post = substr($css, $end + 1);
                $css = $pre . $post;
            }

            // Return CSS
            return $css;
        }

        // Return empty string if there is no file
        return "";
    }

    /**
     * Process page HTML
     * @param   string  HTML
     * @return  string  Formatted HTML
     */
    public function processPageHTML($html)
    {
        $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html, 'UTF-8, ISO-8859-1', TRUE));
        $dom = str_get_dom($html);
        $this->setImageSrc($dom);
        $this->removeEmptyTags($dom);
        $html = (string)$dom;

        $html = str_replace('</div>',"</div>\n", $html);
        $html = str_replace('<div ','    <div ', $html);
        return $html;
    }

    /**
     * Fixes the image src attribute of the background image to be ../images/<filename>
     * filename may have been changed since ChaucerPDF ran, so we check to see if we need to change the
     * filename of the source image as well
     * @param $dom
     * @return string
     */
    public function setImageSrc($dom)
    {
        $href = '';
        foreach($dom('img') as $img)
        {
            $href = $img->getAttribute('src');
            $imgFilename = basename($href);
            if(array_key_exists($imgFilename, $this->alteredImages))
            {
                $imgFilename = $this->alteredImages[$imgFilename];
            }
            $img->setAttribute('src', '../images/'.$imgFilename);
        }
        return (string)$dom;
    }

    /**
     * Removes any empty span tags (<span></span>)
     * @param $dom
     */
    function removeEmptyTags($dom)
    {
        foreach($dom('span') as $span)
        {
            if(!strlen($span->getInnerText()))
            {
                $span->detach();
            }
        }
    }


    private function collectKnownClassesInPage($pageHtml)
    {
        // collect all known classes in two steps
        // step 1. collect div starts
        $re = '|<div[^>]+class=\"([^\"]+)\"|i';
        if (!\preg_match_all($re, $pageHtml, $matches, PREG_PATTERN_ORDER)) {
            throw new \Exception('unable find divs and their classes, check regular expression');
        };
        $knownCssClasses = array();
        // step 2. extract classes from each div and collect
        foreach($matches[1] as $classesPerDivStr) {
            $classesPerDiv = \preg_split('/\s+/', $classesPerDivStr, NULL, PREG_SPLIT_NO_EMPTY);
            $knownCssClasses = \array_merge($knownCssClasses, array_flip($classesPerDiv));
        }
        return $knownCssClasses;
    }

    /**
     * remove unused classes from <style> section
     *
     * @param $buffer
     * @param $knownCssClasses
     */
    private function removeUnusedClassesDeclarations($buffer, $knownCssClasses) {
        $re = '/[\n\r]+\.((?:text|common_span|common)_style\d+)\D*{[^}]*}/';
        $buffer = preg_replace_callback($re, function(array $matches) use (&$knownCssClasses) {
            list($whole,$className) = $matches;
            if (array_key_exists($className, $knownCssClasses)) {
                return $whole;
            }
            return '';
        }, $buffer);
        return $buffer;
    }

    private function extractClassesDeclaration($buffer)
    {
        $re = '/[\n\r]+\.((?:text|common_span|common)_style\d+)\D*{[^}]*}/';
        preg_match_all($re, $buffer, $matches);
        $result = join("\n", $matches[0]);
        return $result;
    }

    private function removeDuplicatesInCSS(&$buffer) {
        //remove same declarations and collect signatures
        $knownSignatures = array();
        return preg_replace_callback('/[\n\r]+(?:\.|body)[^{]+{[^}]*}/', function(array $matches) use (&$knownSignatures) {
            list($match) = $matches;
            $md5 = md5($match);
            if (!array_key_exists($md5, $knownSignatures)) {
                $knownSignatures[$md5] = false;
                return $match;
            }
            return '';
        }, $buffer);

    }

    private function mergeCssContent($bookCss, $pageCss, $pageHTML)
    {
        $knownClasses = $this->collectKnownClassesInPage($pageHTML);
        $bookClasses = $this->extractClassesDeclaration($bookCss);
        $bookCss = $this->removeUnusedClassesDeclarations($bookClasses, $knownClasses);
        return $bookCss."\n\n".$pageCss;
    }

}
