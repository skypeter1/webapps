<?php

App::import('Folder', 'Utility');

App::import('Import', 'CommonProcessor');
App::import('Import/Word', 'XWPFToHTMLConverter');

App::uses('ImportProcessor', 'Lib/Import');

/**
 * Word Processor is the class that is responsible for processing .docx Word Documents.
 * Register the assets create the pages, default CSS and signal the book is ready.
 */
class WordProcessor extends ImportProcessor
{
    public function isValidSourceFile($sourceFileName)
    {
        $ext = strtolower(pathinfo($sourceFileName, PATHINFO_EXTENSION));
        return ($ext == 'doc' || $ext == 'docx');
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
        $this->checkBookSourceType(self::BOOK_SOURCE_TYPE_WORD);

        // Get book source files
        $this->getBookSourceFiles(self::BOOK_SOURCE_TYPE_WORD);

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
        return TRUE;
    }

    /**
     * Primary processing routine for the source
     */
    private function processBook()
    {
        // Preset collectors
        $pageContents = array();
        $pageCssContents = array();

        // Work through all source files
        foreach ($this->sourceFiles as $sourceFile) {

            // Init Apache POI
            $converter = new XWPFToHTMLConverter($this->workingDir, $this->progress);
            if (!$converter) {
                throw new Exception(
                    '[WordProcessor::routine] '
                    .'Book ID ' . $this->bookId . ' cannot be processed as a working directory cannot be found.'
                );
            }

            // Set docx file to parse
            $converter->setDocFileToParse($sourceFile);

            // Convert everything to HTML
            $converter->convertToHTML();

            // Get HTML pages
            $pages = $converter->getHTMLPages();
            $this->progress->adjustMaxSteps((count($pages) * 2) + count($this->sourceAssets)+1);
            foreach($pages as $key => $page) {
                $pages[$key]->setStyleInline(false);
                $pageContents[] = $pages[$key]->getBodyHTML();
                $this->progress->incrementStep();
            }

            // Get CSS
            $pageCssContents[] = $converter->mainStyleSheet->getPagesCSS();

            // Save image assets
            $this->createImageAssets();
        }

        // Save page HTML
        foreach ($pageContents as $page => $contents) {
            $this->savePageHTML($pageContents[$page], $page + 1);
            $this->savePageCSS('', $page+1);
            $this->progress->incrementStep();
        }

        // Save book CSS
        $this->saveBookCSS("body {margin: 0px; padding: 0px;} \n\n".implode("\n", $pageCssContents));
        $this->progress->incrementStep();

        $this->setCoverImage();

        $numPages = count($pages);

        $this->importSourceAssets();

        $this->updatePageCount($numPages);
        return true;
    }

    /**
     * Create image assets.
     */
    private function createImageAssets()
    {
        // Get image directory
        $imageDir = Folder::addPathElement($this->workingDir, 'images');
        if (is_dir($imageDir)) {

            // Get all image files
            $imageFiles = $this->FileManager->getAllFiles($imageDir);

            // Check if there are any images
            if (count($imageFiles) > 0) {

                // Adjust progress
                $this->progress->adjustMaxSteps(count($imageFiles), TRUE);

                // Loop through image files
                foreach ($imageFiles as $imagePath) {

                    // Get file image name
                    $imagePathInfo = pathinfo($imagePath);
                    $imageFilename = $imagePathInfo['basename'];

                    // Check if image exists
                    if (file_exists($imagePath)) {

                        // Path to unlink
                        $imagePathUnlink = $imagePath;

                        // Import image files
                        $this->addImageAssetToBook($imagePath, 'images/');

                        // Remove image files
                        unlink($imagePathUnlink);

                    } else {

                        CakeLog::warning("[WordProcessor::importImages] File $imageFilename not found");
                    }

                    // Update step
                    $this->progress->incrementStep();
                }
            }
        }
    }
} 
