<?php

App::uses('ImportProcessor', 'Lib/Import');
App::import('Vendor', 'Chaucer/Common/ProgressUpdater');

class NoSourceProcessor extends ImportProcessor
{

    public function isValidSourceFile($sourceFileAsset) 
    {
        return false;
    }

    protected function getMinimumSourceFileCount()
    {
        return 0;
    }


    /**
     * Primary processor
     * @return bool|void
     */
    public function process()
    {
        // Set parent process
        parent::process();

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
    
    public function processBook()
    {
        $this->progress->startProgress(count($this->sourceAssets));
        $this->setCoverImage();
        $this->savePageHTML('',1,'Page 1');
        $this->epub->importPageCss('', 1);
        $this->saveBookCSS('');
    }
    
}