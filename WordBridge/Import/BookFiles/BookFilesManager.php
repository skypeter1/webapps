<?php
App::import('Vendor', 'Chaucer/Common/BookData');
App::import('Vendor', 'Chaucer/Epub/EpubManager');

App::uses('ProcessManager', 'Lib/ProcessQueue');
App::uses('BookFilesProcessor', 'Lib/Import/BookFiles');

class BookFilesManager extends ProcessManager
{
    public function __construct($bookId, $options=array())
    {
        parent::__construct($bookId);
        $this->options = $options;
    }

    public function handleBookState($bookState)
    {
        $ret = false;
        switch ($bookState) {
            case BookData::FILES_STATE_NONE:
                CakeLog::info('[BookFilesManager::handleBookState] Book File Add State None: '.$this->bookId);
                $this->completedState = BookData::FILES_STATE_INIT;
                $ret = true;
                break;
            case BookData::FILES_STATE_INIT:
                CakeLog::info('[BookFilesManager::handleBookState] Copying Files to Book Directory: '.$this->bookId);
                $this->completedState = BookData::FILES_STATE_COPIED;
                $objProcessor = new BookFilesProcessor($this->bookId);
                $objProcessor->importFiles = $this->options['files'];
                if(count($objProcessor->importFiles)>0)
                {
                    $ret = $objProcessor->process();
                }
                else
                {
                    $ret = true;
                }
                break;
            case BookData::FILES_STATE_COPIED:
                CakeLog::info('[BookFilesManager::handleBookState] Files Copied: '.$this->bookId);
                $this->completedState = BookData::FILES_STATE_COMPLETE;
                $ret = true;
                break;
            case BookData::FILES_STATE_COMPLETE:
                CakeLog::info('[BookFilesManager::handleBookState] File add Complete: '.$this->bookId);
                $bookItem = $this->bookData->getBookItem(true);
                $bookItem['ChChaucerBookVersion']['processing_progress'] = 1.0;
                $this->bookData->saveBookVersion($bookItem);
                $this->setComplete();
                $ret = true;
                break;
        }
        return $ret;
    }


}