<?php

App::import('Vendor', 'Chaucer/Common/BookData');
App::uses('ProcessManager', 'Lib/ProcessQueue');
App::uses('EpubProcessor', 'Lib/Import');
App::uses('PdfProcessor', 'Lib/Import/Pdf');
App::uses('PdfProcessorRasterized', 'Lib/Import/Pdf');
App::uses('IdmlProcessor', 'Lib/Import');
App::uses('WordProcessor', 'Lib/Import');
App::uses('NoSourceProcessor', 'Lib/Import');

class ImportBookManager extends ProcessManager
{
    public $updateBookId=0;
    public $sourceFiles;
    public $deleteSourceFile;
    
    public function __construct($bookId, $options=array())
    {
        $this->deleteSourceFile = false;
        parent::__construct($bookId, $options);
        $this->sourceFiles = array();
        if($this->getOptionValue('deleteSourceFile')=='true')
        {
            $this->deleteSourceFile = true;
        }
    }
    
    public function handleBookState($bookState)
    {
        $ret = false;
        switch($bookState)
        {
            case BookData::IMPORT_STATE_NONE:
                $this->completedState = BookData::IMPORT_STATE_READY;
                $ret = true;
            break;
            case BookData::IMPORT_STATE_READY:
                $this->completedState = BookData::IMPORT_STATE_COMPLETE;
                $ret = $this->runBookProcess();
            break;
            case BookData::IMPORT_STATE_COMPLETE:
                $this->setComplete();
                if($this->deleteSourceFile)
                {
                    $this->cleanupSourceFiles();
                }
                $ret = true;
            break;
        }
        return $ret;
    }
    
    /**
     * launches the book build process
     * @param int $chaucerBookId
     * @return boolean
     */
    public function runBookProcess()
    {
        $bookSource = $this->getBookSourceType();
        if(!$bookSource)
        {
            throw new Exception("Book {$this->bookId} not found in chaucer database.");
        }

        $objProcessor = $this->getBookProcessor($bookSource);
        
        if(is_object($objProcessor))
        {
            $objProcessor->progressBookId = $this->updateBookId;
            $objProcessor->completedState = $this->completedState;
            $objProcessor->uploadedFiles = $this->sourceFiles;

            try
            {
                return $objProcessor->process();
            }
            catch(Exception $e)
            {
                $msg = trim($e->getMessage());
                if(strlen($msg)>0)
                {
                    if(substr($msg,0,1)=='[')
                    {
                        $displayMsg = substr($msg,strpos($msg,']')+1);
                    }
                    else
                    {
                        $displayMsg = $msg;
                    }
                }
                if(is_object($objProcessor->progress))
                {
                    CakeLog::error($e->getMessage());
                    $objProcessor->processError($displayMsg);
                }
                else
                {
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }
            }
        }
        else
        {
            throw new Exception('[ProcessQueue::runBookProcess] Unknown book source');
        }
        return false;
    }

    /**
     * Get Book source Type. Can be 'pdf', 'images', 'none', 'word', 'idml', 'epub'
     * @return string False is returned if nothing is found.
     */
    public function getBookSourceType()
    {
        $book = $this->bookData->getBookItem();
        if ($book)
        {
            return $book['ChChaucerBook']['book_source_type'];
        }
        return '';
    }

    public function getBookProcessor($bookSource)
    {
        $objProcessor = false;
        $chaucerBookId = $this->bookId;
        switch($bookSource)
        {
            case 'images':
                CakeLog::debug("[ProcessQueue::getBookProcessor] Image Processing job detected for $chaucerBookId");
                $objProcessor = new ImageProcessor($chaucerBookId);                
                break;
            case 'pdf':
                CakeLog::debug("[ProcessQueue::getBookProcessor] PDF Processing job detected for $chaucerBookId");
                if($this->getOptionValue('pdfRasterized'))
                {
                    $objProcessor = new PdfProcessorRasterized($chaucerBookId);
                    if($this->getOptionValue('pdfTextOverlay'))
                    {
                        $objProcessor->createTextOverlay = true;
                    }
                }
                else
                {
                    $objProcessor = new PdfProcessor($chaucerBookId);
                }

                if($this->getOptionValue('pdfRemoveDuplicateFonts'))
                    $objProcessor->removeDuplicateFonts = true;

                $objProcessor->createBookCss = !($this->getOptionValue('pageLevelCSS')==true);
                if($this->getOptionValue('bookQuality')=='high')
                {
                    /*
                     * high quality: keep as-is from ChaucerPDF: png, double sized
                     */
                    $objProcessor->backgroundImageType = 'png';
                    $objProcessor->backgroundQuality = 100;
                    $objProcessor->backgroundResize = false;
                }
                else
                {
                    /*
                     * Web quality: convert backgrounds to jpeg, resize to match book, and set jpeg compression value
                     */
                    $book = $this->bookData->getBookItem();
                    $objProcessor->backgroundImageType = 'jpg';
                    $objProcessor->backgroundQuality = $book['ChChaucerBook']['book_image_quality'];
                    $objProcessor->backgroundResize = true;
                }
                break;
            case 'idml':
                CakeLog::debug("[ProcessQueue::getBookProcessor] IDML Processing job detected for $chaucerBookId");
                $objProcessor = new IdmlProcessor($chaucerBookId);                    
                break;
            case 'epub':
                CakeLog::debug("[ProcessQueue::getBookProcessor] EPUB Processing job detected for $chaucerBookId");
                $objProcessor = new EpubProcessor($chaucerBookId);                       
                break;
            case 'word':
                CakeLog::debug("[ProcessQueue::getBookProcessor] Word Processing job detected for $chaucerBookId");
                $objProcessor = new WordProcessor($chaucerBookId);
                break;
            case 'none':
                CakeLog::debug("[ProcessQueue::getBookProcessor] Empty book Processing job detected for $chaucerBookId");
                $objProcessor = new NoSourceProcessor($chaucerBookId);
                break;
            default:
                CakeLog::debug("[ProcessQueue::getBookProcessor] Invalid bookSource '$bookSource' detected for book_id '$chaucerBookId'");
                throw new Exception("Invalid bookSource '$bookSource' detected for book_id '$chaucerBookId'");
                break;
        }
        return $objProcessor;
    }

    protected function cleanupSourceFiles()
    {
        $fileManger = new FileManager($this->bookId);
        foreach($this->sourceFiles as $sourceFile)
        {
            CakeLog::debug('[ImportBookManager::cleanupSourceFiles] Deleting source file '.$sourceFile);
            $fileManger->delete($sourceFile);
        }

    }
}
