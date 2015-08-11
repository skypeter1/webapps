<?php
App::import('Vendor', 'Chaucer/Common/BookData');
App::import('Vendor', 'Chaucer/Common/FileManager');
App::import('Vendor', 'Chaucer/Common/MediaManager');

App::uses('ProgressUpdater', 'Lib/Common');
App::uses('Folder', 'Utility');

/**
 * This is base class for all processors.
 * This is entry point for all processing activities.
 *
 */
abstract class CommonProcessor
{
    abstract function process();

    /**
     * ProgressUpdater object.
     * @var ProgressUpdater
     */
    public $progress;    
    
    /**
     * Book ID
     * @var int 
     */
    public $bookId;
    
    /**
     * @var BookData
     */
    public $bookData;
    
    /**
     * If the progress updater needs to update a book besides the bookId
     * @var int 
     */
    public $progressBookId = 0;

    /**
     *
     * @var MediaManager 
     */
    protected $MediaManagerInstance;

    /**
     *
     * @var FileManager 
     */
    protected $FileManager;
    
    /**
     * Book status value to set when process is complete
     * @var int 
     */
    public $completedState = BookData::INTERNAL_STATUS_READY;

    /*
     * Local working directory for processing
     * @var string
     */
    public $workingDir;
    
    /**
     * Constructor
     */
    public function __construct($bookId)
    {
        $this->bookId = $bookId;
        $this->bookData = new BookData($this->bookId);
        $this->FileManager = new FileManager($bookId);
    }


    /**
     * Initialize the working directory and ensure it exists
     * @return  string  Path to working directory
     */
    public function initWorkingDir()
    {
        // Check if working directory is already initialized
        if (is_dir($this->workingDir))
        {
            return $this->workingDir;
        }

        // Get temp target directory
        $tmpDir = Configure::read('Chaucer.instanceName').'/'.$this->bookId;
        $targetDir = Folder::addPathElement($this->FileManager->getTmpPath(), $tmpDir);

        // Create and return dir
        if (!$this->FileManager->createDir($targetDir))
        {
            throw new Exception('Unable to create working directory: '.$targetDir);
        }
        CakeLog::debug('[CommonProcessor::initWorkingDir] working dir: '.$targetDir);
        return $targetDir;
    }


    /**
     * Completes the processing
     */
    public function processComplete()
    {
        Configure::write('currentProcessingBook', null);

        // Set the progress to complete.
        $this->progress->setComplete();
    }
    
    public function processError($errorMessage)
    {
       Configure::write('currentProcessingBook',null);
       $this->progress->setError($errorMessage);
    }

    /**
     * Creates and returns media manager instance.
     * @return MediaManager
     */
    public function MediaManager()
    {
        if (!is_object($this->MediaManagerInstance))
        {
            $this->MediaManagerInstance = new MediaManager($this->bookId);
        }
        return $this->MediaManagerInstance;
    }

    /**
     * Get the FileManager used by this processor
     * @return FileManager
     */
    public function getFileManager()
    {
        return $this->FileManager;
    }
    
    public function initProgress($numSteps=2000)
    {
        if($this->progressBookId)
        {
            $this->progress = ProgressUpdater::getInstance($this->progressBookId);
        }
        else
        {
            $this->progress = ProgressUpdater::getInstance($this->bookId);
        }
        $this->progress->completedState = $this->completedState;
        $this->progress->startProgress($numSteps);              
    }

}


