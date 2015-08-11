<?php

App::uses('ImportProcessor', 'Lib/Import');

/**
 * Image Processor is the class that is responsible for processing images as comic books. 
 * It will grap the source images, resize them, move them to correct folder, update the database. Register the assets
 * create the pages, default CSS and signal the book is ready. Also it will generate thumbnails.
 *
 */
class ImageProcessor extends ImportProcessor
{
    const GRAPHIC_NOVEL_MAX_IMAGE_PIXELS = 3100000;
    const GRAPHIC_NOVEL_MAX_PAGE_WIDTH = 1024;
    const GRAPHIC_NOVEL_MAX_PAGE_HEIGHT = 768;

    /**
     * Primary processor
     * @return bool|void
     */
    public function process()
    {
        // Set parent process
        parent::process();

        // Validate the book source type
        $this->checkBookSourceType(self::BOOK_SOURCE_TYPE_IMAGES);

        // Initialize ePub structure
        $this->epub->initEpub();
        
        // Get book source files
        $this->getBookSourceFiles();

        // Start up the routine
        $this->processBook();
        
        //handle any extra files included with the book 
        $this->importSourceAssets();

        // Update ePub files
        $this->epub->updateEpubFiles();

        // Set process complete
        $this->processComplete();

        // Return success
        return TRUE;
    }
    
    public function isValidSourceFile($sourceFileName)
    {
        return (MediaManager::isImage($sourceFileName));
    }
    

    /**
     * Copy over source file to working dir
     * @param $sourceFile
     * @param $processingBookType
     * @param $sourceFileCount
     * @throws Exception
     */
    protected function copySourceToWorkingDir($sourceFile)
    {
        static $importPageNumber;
            
        // Check if this is an image
        if ($this->isValidSourceFile($sourceFile))
        {        
            $importPageNumber = (int)$importPageNumber+1;
            // Get source file
            $source = Folder::addPathElement(Configure::read('Chaucer.sourceDir'), $sourceFile['filename']);

            // Set target file

            // In case of images increment filename with number padding
            $assetNumPad = str_pad($importPageNumber, 4, '0', STR_PAD_LEFT);

            // Assemble asset name
            $assetFilename = 'image'.$assetNumPad.'.';
            $assetFilename .= (MediaManager::isConvertableImage($source)) ? 'png' : $sourceFile['ext'];

            // Set target
            $target = Folder::addPathElement($this->workingDir, $assetFilename);

            // Log the message
            CakeLog::debug(
                '[ImageProcessor::getBookSourceFiles] '
                .'Preparing to copy remote file on instance '
                .Configure::read('Chaucer.instanceName').': '.$source.' to '.$target
            );

            // Get the source file from remote
            $this->FileManager->copy($source, $target);
            if (file_exists($target)) {
                $this->sourceFiles[] = $target;
            } else {
                throw new Exception(
                    '[ImageProcessor::getBookSourceFiles] '
                    .'Unable to copy book source file.'
                );
            }
            return true;
        }
        return false;
    }

    /**
     * Update book size.
     * @param int $bookWidth
     * @param int  $bookHeight
     */
    public function updateBookSize($bookWidth, $bookHeight)
    {
        list($bookWidth, $bookHeight) = MediaManager::getResizedDimensionForMaxDimensions(
                $bookWidth,
                $bookHeight);

        $bookItem = $this->bookData->getBookItem(true);
        $bookItem['ChChaucerBookVersion']['book_width'] = $bookWidth;
        $bookItem['ChChaucerBookVersion']['book_height'] = $bookHeight;
        $bookItem['ChChaucerBookVersion']['book_resize_width'] = $bookWidth;
        $bookItem['ChChaucerBookVersion']['book_resize_height'] = $bookHeight;
        $this->bookData->saveBookVersion($bookItem);
    }

    /**
     * Primary processing routine for the source
     */
    private function processBook()
    {
        // Get Media manager and preset page parameters
        $mediaManager = new MediaManager($this->bookId);
        $pageCount = count($this->sourceFiles) + count($this->sourceAssets);
        $pageNumber = 1;

        // Prepare the progress
        $this->progress->startProgress($pageCount * 2);
        $this->updatePageCount(0);
        $bookSizeFound = false;


        // Loop through source files
        foreach ($this->sourceFiles as $source) 
        {
            CakeLog::debug('[ImageProcessor::processBook] processing source file '.$source);
            // Get book size
            if (!$bookSizeFound) 
            {
                $bookWidth = 0;
                $bookHeight = 0;
                $this->getBookSizeImage($source, $bookWidth, $bookHeight);
                CakeLog::debug('[ImageProcessor::processBook] Book dimensions set to '.$bookWidth.' x '.$bookHeight);
                $this->updateBookSize($bookWidth, $bookHeight);
                $bookSizeFound = true;
            }

            // Convert to png as needed
            if (MediaManager::isConvertableImage($source)) 
            {
                CakeLog::debug('[ImageProcessor::processBook] Converting '.$source.' to png');
                $mediaManager->imageConvertToPng($source, $source);
            }

            // Resize image to max dimension.
            $image = new Imagick($source);
            $width = $image->getimagewidth();
            $height = $image->getimageheight();

            // Get new width and height
            list($newWidth, $newHeight) = $this->getResizedDimensionsForMaxPixels($width, $height);
            If($newHeight != $height || $newWidth != $width )
            {
                CakeLog::debug('[ImageProcessor::processBook] '.$source.' resized for max pixel limits to '.$newWidth.'x'.$newHeight);            
                // Resize the original image
                $img = new Imagick($source);
                $img->resizeimage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1, false);
                $img->writeimage();
            }
            $this->addImageAssetToBook($source, 'images/backgrounds/');
            $this->savePageHTML('', $pageNumber, null, '../images/backgrounds/'.basename($source));
            @unlink($source);
            $this->savePageCSS('', $pageNumber);

            // Count page
            $pageNumber++;

            // Update the progress in database every 10th page.
            $this->progress->incrementStep();
        }
        
        //handle any non-source assets included with the book source files
        $this->importSourceAssets();

        $this->setCoverImage();

        // Save default book CSS
        $this->saveBookCSS('');

        // Generate thumbnails
        $this->generateThumbnails(1, $pageCount);

        $this->updatePageCount($pageCount);
        CakeLog::debug('[ImageProcessor::processBook] Book Processing Complete');
    }

    /**
     * Get image dimensions for resizing based on a maximum dimension
     * @param int $width
     * @param int $height
     * @param int $maxWidth = self::GRAPHIC_NOVEL_MAX_PAGE_WIDTH, $maxHeight
     * @param int $maxHeight
     * @return array<int>
     *
     */
   private function getResizedDimensionForMaxDimensions(
        $width,
        $height,
        $maxWidth = self::GRAPHIC_NOVEL_MAX_PAGE_WIDTH,
        $maxHeight = self::GRAPHIC_NOVEL_MAX_PAGE_HEIGHT)
    {
        $newWidth = $width;
        $newHeight = $height;
        $ratio = $width / $height;

        if ($width < $maxWidth && $height < $maxHeight)
        {
            $width = $maxWidth;
            $height = $maxWidth * $ratio;
        }

        if ($width > $height)
        {
            // Landscape
            $newWidth = min(1024, $width);
            $newHeight = round($newWidth / $ratio);

            if ($newHeight > 768)
            {
                $newHeight = 768;
                $newWidth = round($newHeight * $ratio);
            }
        }
        else
        {
            // Portrait. Fit into height 1024 and max width should be 768.
            $newHeight = min(1024, $height);
            $newWidth = round($newHeight * $ratio);

            if ($newWidth > 768)
            {
                $newWidth = 768;
                $newHeight = round($newWidth / $ratio);
            }
        }

        return array($newWidth, $newHeight);
    }

    /**
     * Determine book size from image file
     * @param string $imagePath
     * @param int $bookWidth
     * @param int $bookHeight
     * @return None
     */
    private function getBookSizeImage($imagePath, &$bookWidth, &$bookHeight)
    {
        $image = new Imagick($imagePath);
        $d = $image->getImageGeometry();
        list($bookWidth, $bookHeight) = $this->getResizedDimensionForMaxDimensions(
            $d['width'],
            $d['height']);
    }

    /**
     * Get image dimensions for resizing based on a maximum dimension
     * @param int $width
     * @param int $height
     * @param float $maxPixelCount in Millions of pixels
     * @return array<int>
     *
     */
    private function getResizedDimensionsForMaxPixels($width, $height, $maxPixelCount = self::GRAPHIC_NOVEL_MAX_IMAGE_PIXELS)
    {
        $numPixels = $width * $height;
        if ($numPixels > $maxPixelCount)
        {
            $scale = sqrt($numPixels / $maxPixelCount);
            $height = floor($height / $scale);
            $width = floor($width / $scale);
        }

        return array($width, $height);
    }
}