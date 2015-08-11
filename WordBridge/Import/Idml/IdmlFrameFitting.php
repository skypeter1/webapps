<?php

/**
 * @package /app/Import/Idml/IdmlFrameFitting.php
 * 
 * @class   IdmlFrameFitting
 * 
 * @description This contains cropping instructions, typically used for an image that needs to be cropped within a rectangle.
 * 
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlFrameFitting
{
    public $leftCrop;
    public $topCrop;
    public $rightCrop;
    public $bottomCrop;

    /**
     * The constructor
     *
     * @param float $topCrop
     * @param float $leftCrop
     * @param float $bottomCrop
     * @param float $rightCrop
     */
    public function __construct($topCrop, $leftCrop, $bottomCrop, $rightCrop)
    {
        $this->topCrop    = $topCrop;
        $this->leftCrop   = $leftCrop;
        $this->bottomCrop = $bottomCrop;
        $this->rightCrop  = $rightCrop;
    }
    
    public function hasCroppingInstructions()
    {
        if ($this->topCrop != 0.0 ||
            $this->leftCrop != 0.0 ||
            $this->bottomCrop != 0.0 ||
            $this->rightCrop != 0.0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
