<?php

App::uses('ImportProcessor', 'Lib/Import');

/**
 * Imports files into existing book
 * Class BookFilesProcessor
 */
class BookFilesProcessor extends ImportProcessor
{

    /**
     * Files to add to the book [srcFile] => [targetPath]
     * @var array
     */
    public $importFiles = null;

    /**
     * this class treats all files as additional assets
     * @param $sourceFileAsset
     * @return bool
     */
    public function isValidSourceFile($sourceFileAsset)
    {
        return false;
    }

    /**
     * copy new files into book directory by treating the importFiles array as additional assets
     */
    public function process()
    {
        if(!is_array($this->importFiles))
        {
            throw new Exception('[BookFilesProcessor::process] Source book directory not set');
        }

        if(count($this->importFiles)==0)
        {
            throw new Exception('[BookFilesProcessor::process] No source files found');
        }

        parent::process();

        $this->epub->initEpub();

        CakeLog::debug('[BookFilesProcessor::process] Copying '.count($this->importFiles).' book files for ID '.$this->bookId);

        foreach($this->importFiles as $srcFile=>$targetPath)
        {
            $ext = pathinfo($srcFile, PATHINFO_EXTENSION);
            if(in_array($ext, self::$supportedImageTypes))
            {
                if(!strlen($targetPath))
                {
                    $targetPath = 'images/';
                }
                CakeLog::debug('[ImportProcessor::importSourceAssets] Adding image file '.$srcFile.' to book in '.$targetPath);
                $this->addImageAssetToBook($srcFile, $targetPath);
            }
            elseif(in_array($ext, self::$supportedFontTypes))
            {
                CakeLog::debug('[ImportProcessor::importSourceAssets] Adding font file '.$srcFile.' to book in '.$targetPath);
                $this->addFontToBook($srcFile);
            }
            elseif(in_array($ext, self::$supportedAudioTypes))
            {
                if(!strlen($targetPath))
                {
                    $targetPath = 'audio/';
                }
                CakeLog::debug('[ImportProcessor::importSourceAssets] Adding audio file '.$srcFile.' to book in '.$targetPath);
                $this->addAudioAssetToBook($srcFile, $targetPath);
            }
            elseif(in_array($ext, self::$supportedVideoTypes))
            {
                if(!strlen($targetPath))
                {
                    $targetPath = 'video/';
                }
                CakeLog::debug('[ImportProcessor::importSourceAssets] Adding video file '.$srcFile.' to book in '.$targetPath);
                $this->addVideoAssetToBook($srcFile, $targetPath);
            }
            else
            {
                switch($ext)
                {
                    case 'js':
                        if(!strlen($targetPath))
                        {
                            $targetPath = 'js/';
                        }
                        CakeLog::debug('[ImportProcessor::importSourceAssets] Adding javascript file '.$srcFile.' to book in '.$targetPath);
                        $this->addJavaScriptAssetToBook($srcFile, $targetPath);
                    break;
                    case 'css':
                        if(!strlen($targetPath))
                        {
                            $targetPath = 'styles/';
                        }
                        CakeLog::debug('[ImportProcessor::importSourceAssets] Adding css file '.$srcFile.' to book in '.$targetPath);
                        $this->addCssAssetToBook($srcFile, $targetPath);
                        break;
                    default:
                        if(strlen($targetPath)>0)
                        {
                            CakeLog::debug('[ImportProcessor::importSourceAssets] Adding misc file '.$srcFile.' to book in '.$targetPath);
                            $this->addMiscAssetToBook($srcFile, $targetPath);
                        }
                    break;
                }
            }
            $this->progress->incrementStep();
        }

        $this->epub->updateEpubFiles();
        $this->processComplete();
        CakeLog::debug('[BookFilesProcessor::process] Book id '.$this->bookId.' copy Complete');
        return true;
    }
}