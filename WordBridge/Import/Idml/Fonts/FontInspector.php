<?php

/**
 * @package /app/Lib/Metrolib/FontInspector.php
 *
 * @class   FontInspector
 *
 * @description A utility function to open a truetype or opentype font file and get the font family name
 *  and font variant name that uses the rules that InDesign follows.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

require_once(VENDORS.'/Typo/bootstrap.php');

class FontInspector
{
    /**
     * @param string $fontfile
     * @return array containing 'success', 'algorithm', 'fontFamily', 'fontVariant', 'fullFontName', 'postscriptName'
     */
    public static function inDesignFontNames($filename)
    {
        $names = array();

        try
        {
            $fontInfo = Pronamic\Typos\Typos::loadFromFile($filename);
        }
        catch(Exception $e)
        {
            $names['algorithm'] = 'none';
            $names['success'] = false;
            return $names;
        }

        if( !isset($fontInfo->font->nameTable))
        {
            $names['algorithm'] = 'none';
            $names['success'] = false;
            return $names;
        }

        $numNames = $fontInfo->font->nameTable->getNumberNameRecords();

        $stdFamily     = $fontInfo->getNameRecord(1,null);
        $stdVariant    = $fontInfo->getNameRecord(2,null);
        $stdPostscript = $fontInfo->getNameRecord(6,null);
        $advFamily     = ($numNames >= 18) ? $fontInfo->getNameRecord(16,null) : '';
        $advVariant    = ($numNames >= 18) ? $fontInfo->getNameRecord(17,null) : '';

        if ($advFamily <> '')
        {
            if ($advVariant == '')
            {
                $names['algorithm'] = 'advanced/no variant';
                $names['fontFamily'] = $advFamily;
                $names['fontVariant'] = '';
                $names['fullFontName'] = $advFamily;
            }

            else if ($advVariant <> 'Regular')
            {
                $names['algorithm'] = 'advanced/variant';
                $names['fontFamily'] = $advFamily;
                $names['fontVariant'] = $advVariant;
                $names['fullFontName'] = $advFamily . ' ' . $advVariant;
            }

            else
            {
                $names['algorithm'] = 'advanced/Regular';
                $names['fontFamily'] = $advFamily;
                $names['fontVariant'] = '';
                $names['fullFontName'] = $advFamily;
            }
        }
        else
        {
            if ($stdVariant == '')
            {
                $names['algorithm'] = 'standard/no variant';
                $names['fontFamily'] = $stdFamily;
                $names['fontVariant'] = '';
                $names['fullFontName'] = $stdFamily;
            }

            else if ($stdVariant <> 'Regular')
            {
                $names['algorithm'] = 'standard/variant';
                $names['fontFamily'] = $stdFamily;
                $names['fontVariant'] = $stdVariant;
                $names['fullFontName'] = $stdFamily . ' ' . $stdVariant;
            }

            else
            {
                $names['algorithm'] = 'standard/Regular';
                $names['fontFamily'] = $stdFamily;
                $names['fontVariant'] = '';
                $names['fullFontName'] = $stdFamily;
            }
        }

        $names['postscriptName'] = $stdPostscript;
        $names['success'] = true;

        return $names;
    }
}
?>
