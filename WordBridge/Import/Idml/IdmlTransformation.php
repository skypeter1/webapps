<?php


/**
 * @package /app/Import/Idml/IdmlTransformation.php
 * 
 * @class   IdmlTransformation
 * 
 * @description A class for holding transformation instructions that are suitable for use in mathematical
 *          linear transformation such as scale, skew, rotate, invert, rotate
 *          This class is used by the IdmlBoundary class to perform operations on its points.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlTransformation
{
    // The matrix variables are: xScale=a, ySkew=b, xSkew=c, yScale=d, xTranslate=tx, yTranslate=ty
    private $a, $b, $c, $d, $tx, $ty;

    /**
     * Make transformation.
     * @param string $transformString In format like "1 0 0 1 100 150"
     */
    public function __construct($transformString = '')
    {
        $this->a = (float)1.0;
        $this->b = (float)0.0;
        $this->c = (float)0.0;
        $this->d = (float)1.0;
        $this->tx = (float)0.0;
        $this->ty = (float)0.0;

        if (!empty($transformString))
        {
            $parts = explode(' ', $transformString);
            $countParts = count($parts);
            if ($countParts == 6)
            {
                $this->a = (float)$parts[0];
                $this->b = (float)$parts[1];
                $this->c = (float)$parts[2];
                $this->d = (float)$parts[3];
                $this->tx = (float)$parts[4];
                $this->ty = (float)$parts[5];
            }
            else
            {
                CakeLog::debug("[IdmlTransformation:__construct] TransformString '$transformString' contains $countParts numbers, 6 required.");
            }
        }
    }

    /**
     * Is transformation identity.
     * @return boolean
     */
    public function isIdentity()
    {
        if ($this->a == 1.0 &&
            $this->b == 0.0 &&
            $this->c == 0.0 &&
            $this->d == 1.0 &&
            $this->tx == 0.0 &&
            $this->ty == 0.0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Return css compatible transformation. If identity then returns empty string.
     * 
     * @param string $type Type of matrix css. Defaults to -webkit.
     *
     * @return string final css compatible string with matrix.
     */
    public function getCssTransformation($type = '-webkit')
    {
        if ($this->isIdentity())
        {
            return '';
        }

        switch ($type)
        {
            case '-webkit':
                $matrixValue = "{$this->a},{$this->b},{$this->c},{$this->d},{$this->tx},{$this->ty}";
                $result = "-webkit-transform: matrix({$matrixValue}); -webkit-transform-origin: center center;";
                return $result;
            
            case '':
                $matrixValue = "{$this->a},{$this->b},{$this->c},{$this->d},{$this->tx},{$this->ty}";
                $result = "transform: matrix({$matrixValue}); transform-origin: center center;";
                return $result;
            
            default:
                return '';
        }
    }

    /**
     * Get Position from transformation. (This is linear "translation").
     * 
     * @param float $outX
     * @param float $outY
     *
     * @return none 
     */
    public function getPosition(&$outX, &$outY)
    {
        $outX = $this->tx;
        $outY = $this->ty;
    }
    
    /**
     * Get scale from matrix.
     * 
     * @param float $outScaleX
     * @param float $outScaleY
     *
     * @return none
     */
    public function getScale(&$outScaleX, &$outScaleY)
    {
        $outScaleX = sqrt($this->a*$this->a + $this->b*$this->b);
        $outScaleY = sqrt($this->c*$this->c + $this->d*$this->d);
    }

    public function getA()
    {
        return $this->a;
    }
    
    public function getB()
    {
        return $this->b;
    }
    
    public function getC()
    {
        return $this->c;
    }
    
    public function getD()
    {
        return $this->d;
    }

    public function xTranslate()
    {
        return $this->tx;
    }
    
    public function yTranslate()
    {
        return $this->ty;
    }

    /**
     * Sets the transform's tx and ty values.
     * @param $tx
     * @param null $ty
     */
    public function setXY($tx, $ty)
    {
        $this->tx = $tx;
        $this->ty = $ty;
    }
    
    /* Dump the transformation in a human readable format
     * @return string 
     */
    public function diagnostic()
    {
        $s = array();
        $s[] = "[a:]=" . $this->getA();
        $s[] = "[b:]=" . $this->getB();
        $s[] = "[c:]=" . $this->getC();
        $s[] = "[d:]=" . $this->getD();
        $s[] = "[tx: xtranslate]=" . $this->xTranslate();
        $s[] = "[ty: ytranslate]=" . $this->yTranslate();
        return implode('  ', $s);
    }
}
