<?php

App::uses('ChChaucerBook', 'Model');
App::import('Vendor', 'Chaucer/Common/BookData');
/**
 * Used to update the progress while processing. Update the internal_states of the book, etc.
 */
class ProgressUpdater
{
    const MIN_UPDATE_INTERVAL = 1.0; // how many seconds need to pass for update to happen in database.
    
    // weighting multipliers for the progress updater, should be integers
    const WEIGHTING_TYPICAL = 1;
    const WEIGHTING_IDML_PHASE1 = 1;
    const WEIGHTING_IDML_PHASE2 = 1;
    const WEIGHTING_IDML_PHASE3 = 4;
    
    /**
     * Instance by book Id map.
     * @var type
     */
    protected static $instanceByBookIdMap = array();
    
    /**
     * Book Id
     * @var int 
     */
    protected $bookId = null;
    
    /**
     * ChaucerBook model.
     * @var ChChaucerBook
     */
    protected $ChChaucerBook = null;
    
    /**
     * Current step.
     * @var int 
     */
    protected $currentStep = 0;
    
    /**
     * Max Steps.
     * @var int
     */
    protected $maxSteps = 1;
    
    /**
     * When was last update time of progress.
     * @var float
     */
    protected $lastUpdateTime = 0;
    
    /**
     * Microseconds update interval.
     * @var float
     */
    public $updateInterval = self::MIN_UPDATE_INTERVAL;
    
    /**
     * State to set book to when complete
     * @var int 
     */
    public $completedState = BookData::INTERNAL_STATUS_READY;

    /**
     * List of all errors found so far.
     * @var array
     */
    protected $errors = array();

    /**
     * List of all warnings found so far. Warning will not affect processing. But indicates that there might be a problem
     * with processing.
     * 
     * @var array
     */
    protected $warnings = array();
    

    /**
     * Get Instance.
     *
     * @param int $bookId
     * @return ProgressUpdater 
     */
    public static function getInstance($bookId)
    {
        if (!array_key_exists($bookId, self::$instanceByBookIdMap))
        {
            $newInstance = new ProgressUpdater($bookId);
            self::$instanceByBookIdMap[$bookId] = $newInstance;
        }

        return self::$instanceByBookIdMap[$bookId];
    }    

    /**
     *
     * @param int $bookId
     */
    protected function __construct($bookId, $updateInterval = self::MIN_UPDATE_INTERVAL)
    {
        $this->ChChaucerBook = ClassRegistry::init('ChChaucerBook');
        
        $this->bookId = $bookId;
        $this->updateInterval = $updateInterval;
    }

    private function updateBook($data)
    {
        $this->ChChaucerBook->clear();
        $data['book_id'] = $this->bookId;
        $this->ChChaucerBook->save($data);
    }

    /**
     * Update progress on book. 
     * @param float $progress 0..1
     */
    protected function updateProgress($progress)
    {
        if ($this->bookId)
        {
            $now = microtime(true);
            
            if ($now - $this->lastUpdateTime >= $this->updateInterval)
            {
                $data = array();
                $data['processor_progress'] = $progress;
                $this->updateBook($data);
                $this->lastUpdateTime = $now;
            }
        }
    }
    
    /**
     * Start progress.
     * @param int $maxSteps
     */
    public function startProgress($maxSteps)
    {
        if ($maxSteps == 0) $maxSteps = 1;
        
        $this->maxSteps = $maxSteps;
        $this->currentStep = 0;
        
        if($this->bookId)
        {
            $data = array();
            $this->updateBook($data);
        }
    }
    
    /**
     * The adjustMaxSteps function can be called after processing has begun in order to adjust the percent complete denominator
     * based on new information that has become available. This is necessary for IDML reflowable books which have page breaks
     * that can only be determined during parsing.
     * @param   int     New maximum steps
     * @param   bool    Add to maximum steps
     * @return  void
     */
    public function adjustMaxSteps($newMaxSteps, $addToMaxSteps = FALSE)
    {
        $this->maxSteps = ($addToMaxSteps) ? $this->maxSteps + $newMaxSteps : $newMaxSteps;
    }
    
    /**
     * Increment steps.
     */
    public function incrementStep($increment = ProgressUpdater::WEIGHTING_TYPICAL)
    {
        $this->currentStep += $increment;
        $progressValue = $this->currentStep / $this->maxSteps;
        $this->updateProgress($progressValue > 1 ? 1.0 : $progressValue);
    }

    /**
     * Set current step.
     * @param int $step
     */
    public function setCurrentStep($step)
    {
        $this->currentStep = $step;
        $progressValue = $this->currentStep / $this->maxSteps;
        $this->updateProgress($progressValue > 1 ? 1.0 : $progressValue);
    }

    /**
     * Get current step.
     * Required for testing only (so far)
     * @return int
     */
    public function getCurrentStep()
    {
        return $this->currentStep;
    }

    /**
     * Report error.
     * @param string $errorMessage
     */
    public function setError($errorMessage)
    {
        if (!empty($errorMessage))
        {
            $this->errors[] = $errorMessage;
            $data = array();
            $data['internal_status'] = BookData::INTERNAL_STATUS_ERRORS;
            $data['processor_errors'] = $errorMessage;
            $this->updateBook($data);
        }
    }

    /**
     * Set Warning.
     * @param string $warningMessage
     */
    public function setWarning($warningMessage)
    {
        if (!empty($warningMessage))
        {
            $this->warnings[] = $warningMessage;
        }
    }

    /**
     * Get warnings reported so far.
     * @return string
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Return all errors.
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set book processing complete flag.
     */
    public function setComplete()
    {
        if($this->bookId)
        {
            $data = array();
            $data['internal_status'] = $this->completedState;
            $data['processing_progress'] = 1.0;
            $this->updateBook($data);
        }
    }
}
