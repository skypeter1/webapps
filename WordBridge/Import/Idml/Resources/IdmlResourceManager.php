<?php

/**
 * @package /app/Import/Idml/IdmlResourceManager.php
 * 
 * @class   IdmlResourceManager
 * 
 * @description This class provides a helper function for images that are within the IDML document that need to be
 *              saved as assets in the database.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

//? App::import('Lib/Chaucer', 'IdmlProcessor');

class IdmlResourceManager
{
    /**
     * Current processor.
     * @var CommonProcessor
     */
    protected $processor = null;

    protected $FileManager;
    
    /**
     * Constructor.
     *
     * @param IdmlProcessor $processor
     */
    public function __construct(IdmlProcessor $processor, FileManager $fileManager)
    {
        $this->processor = $processor;
        $this->FileManager = $fileManager;
    }

    /**
     * Register image element.
     * 
     * @param IdmlImage $idmlImage containing mediaFilename and optionally containing imageContent (from parsed CDATA).
     * @param IdmlElement $idmlContainer is the wrapper of the image, which is needed for cropping operations.
     */
    public function registerImage(IdmlImage $idmlImage, $idmlContainer)
    {
        $tmpPath = null;
        $containersAllowed = array('IdmlRectangle', 'IdmlPolygon');
        
        // If the image data has come from embedded CDATA . . .
        if ($idmlImage->embeddedImage && strlen($idmlImage->imageContent) > 0)
        {
            $tmpPath = $this->FileManager->getTmpPath();
            $registerFilename = $this->createImageFromCdata($idmlImage, $tmpPath);
        }
        
        // . . . otherwise the image file is, hopefully, contained in the S3 bucket at books/999/Source
        else
        {
            // assemble the filename path, within the S3 bucket, and verify that it exists
            $registerFilename = $this->getImageFile($idmlImage);

            if ($registerFilename == null)
            {
                $idmlImage->mediaFilename .= " [image not found]";
                return;
            }
        }

        // Crop the picture if asked to
        if ($idmlContainer != null && in_array(get_class($idmlContainer), $containersAllowed))
        {
            if (MediaManager::isVectorImage($registerFilename))
            {
                // skip for now, because cropping .ai is crashing IMagick
            }
            else
            {
                $this->cropImage($idmlImage, $idmlContainer, $registerFilename);
            }
        }

        // Does this image need to be converted?
        if (MediaManager::isConvertableImage($registerFilename))
        {
            $idmlImage->mediaFilename = $this->processor->convertUnsupportedImageType($registerFilename);
        }
    }

    protected function createImageFromCdata($idmlImage, $tmpPath)
    {
        // Create an image file from the base64 decoded CDATA
        $imageContent = base64_decode($idmlImage->imageContent);
        $registerFilename = Folder::addPathElement($tmpPath, $idmlImage->mediaFilename);
        file_put_contents($registerFilename, $imageContent);

        // Discard image CDATA to conserve memory.
        $idmlImage->imageContent = null;
        gc_collect_cycles();

        $this->processor->appendToSourceAssetList($registerFilename);

        return $registerFilename;
    }

    /*
    * @return a filename path on AWS S3, like "books/999/Source/myimage.jpg", or null if the file does not exist on S3
    */
    protected function getImageFile($idmlImage)
    {
        $path = $this->getFullFilePath($idmlImage->mediaFilename);
        $registerFilename = Folder::addPathElement($path, $idmlImage->mediaFilename);

        // . . . and just in case the exact filename doesn't exist, look for a filename with a JPG or PNG extension . . .
        if(!$this->FileManager->exists($registerFilename))
        {
            $alternateFilename = $this->findAlternateFilename($idmlImage, $path);

            if (is_null($alternateFilename))
            {
                return null;
            }
            else
            {
                $registerFilename = $alternateFilename;
            }
        }
        return $registerFilename;
    }

    protected function findAlternateFilename($idmlImage, $path)
    {
        $pi = pathinfo($idmlImage->mediaFilename);
        $jpgFilename = $pi['filename'] . '.jpg';
        $pngFilename = $pi['filename'] . '.png';
        $testForJPG = Folder::addPathElement($path, $jpgFilename);
        $testForPNG = Folder::addPathElement($path, $pngFilename);

        // slight of hand
        if ($this->FileManager->exists($testForJPG))
        {
            $registerFilename = $testForJPG;
            $idmlImage->mediaFilename = $jpgFilename;
        }
        else if ($this->FileManager->exists($testForPNG))
        {
            $registerFilename = $testForPNG;
            $idmlImage->mediaFilename = $pngFilename;
        }
        else
        {
            $registerFilename = null;
        }

        return $registerFilename;
    }

    protected function getFullFilePath()
    {
        $path = $this->FileManager->getRemoteRootDir();
        $path = Folder::addPathElement($path, 'Source');
        return $path;
    }

    protected function cropImage($idmlImage, $idmlRectangle, $s3File)
    {
        $ffi = $idmlRectangle->frameFittingOption;

        if ($ffi->hasCroppingInstructions())
        {
            if(!$this->FileManager->exists($s3File))
            {
                CakeLog::warning('[IdmlResourceManager::cropImage] Source file '.$s3File.' does not exist.');
                return false;
            }
            // InDesign uses 72ppi as its base units for IDML
            // convert the cropping instruction units to image pixel units
            $leftCrop   = $ffi->leftCrop   * ($idmlImage->ppiX/72);
            $rightCrop  = $ffi->rightCrop  * ($idmlImage->ppiX/72);
            $topCrop    = $ffi->topCrop    * ($idmlImage->ppiY/72);
            $bottomCrop = $ffi->bottomCrop * ($idmlImage->ppiY/72);

            // convert the image dimensions from InDesign units to real pixels
            $originalImageWidth = $idmlImage->width * ($idmlImage->ppiX/72);
            $originalImageHeight = $idmlImage->height * ($idmlImage->ppiY/72);

            $widthOfCrop = $originalImageWidth - ($rightCrop + $leftCrop);
            $heightOfCrop = $originalImageHeight - ($topCrop + $bottomCrop);

            // download from S3 to local computer
            $tmp = $this->FileManager->getTmpPath();
            $basename = pathinfo($s3File, PATHINFO_BASENAME);
            $localFile = $tmp . DS . $basename;
            $this->FileManager->copy($s3File, $localFile);
            $result = true;
            // Crop
            $imagick = new Imagick($localFile);
            if ($imagick)
            {
                try
                {
                    $imagick->cropImage($widthOfCrop, $heightOfCrop, $leftCrop, $topCrop);
                    $imagick->writeImage();
                }
                catch (Exception $e)
                {
                    CakeLog::warning('[IdmlResourceManager::cropImage] Failed to crop '.$localFile);
                    $result = false;
                }
                $imagick->clear();
                $imagick->destroy();
            }

            // upload from local computer to S3
            $this->FileManager->copy($localFile, $s3File);

            $this->processor->patchSourceAssetList($localFile, $localFile);
            return $result;
        }
    }
}

