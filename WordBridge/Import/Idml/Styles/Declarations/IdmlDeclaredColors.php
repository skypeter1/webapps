<?php

/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlDeclaredColors.php
 * 
 * @class   IdmlDeclaredColors
 * 
 * @description There should always be exactly one /Resources/Graphics.xml file per Package.
 *              This class parses that file and converts all Colors, Inks and Tints into RBG values suitable for use by CSS.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
 
App::uses('IdmlGradient', 'Import/Idml/Styles/Declarations');


class IdmlDeclaredColors
{
    /*
     * @var array $declaredColors An associative array of IDML colors, tints and swatches each of which points to
     * an array of three integers containing red, green and blue values between 0 and 255.
     * The key is always the 'Self' attribute of the Color, Tint or Swatch.
     */
    public $declaredColors;

    /*
     * @var array[IdmlGradient] $declaredGradients An associative array of IDML gradients where the key is always
     * the 'Self' attribute of the gradient.
     */
    public $declaredGradients;


    public function __construct()
    {
        $this->declaredColors = array();
        $this->declaredGradients = array();
    }
    
    /*
     *  The load function is the starting point for parsing the /Resources/Graphic.xml file.
     *  @param string $filename is a fully qualified filename.
     */
    public function load($filename)
    {
        $doc = new DomDocument();
        $b = $doc->load($filename);
        if ($b === false)
            return false;

        $xpath = new DOMXPath($doc);

        // 1. Colors are the most important, they must be parsed first
        $items = $xpath->query('//idPkg:Graphic/Color');
        $this->parseColors($items);

        // 2. Inks are the four predefined inks (Cyan, Magenta, Yellow and Black) plus any spot-color inks
        // defined by the user. They are referenced by MixedInks.

        // 3. MixedInkGroup is an InDesign thing, used to create the corresponding MixedInk items. These are never needed.

        // 4. MixedInks blend two or more inks by percentage. These are used only by offset printers for paper books.

        // 5. Tints are lighter variants of Colors
        $items = $xpath->query('//idPkg:Graphic/Tint');
        $this->parseTints($items);

        // 6. Gradients make use of the Colors and Tints, so parsing them must come last
        $items = $xpath->query('//idPkg:Graphic/Gradient');
        $this->parseGradients($items);
    }

    private function parseColors(DOMNodeList $items)
    {
        foreach ($items as $item)
        {
            $self = $item->attributes->getNamedItem('Self')->value;
            $colorSpace = $item->attributes->getNamedItem('Space')->value;
            $colorValues = $item->attributes->getNamedItem('ColorValue')->value;

            $this->declaredColors[$self] = self::colorSpaceAndValueToRGB($colorSpace, $colorValues);
        }
    }

    private function parseTints(DOMNodeList $items)
    {
        foreach ($items as $item)
        {
            $self = $item->attributes->getNamedItem('Self')->value;
            $tintPercent = $item->attributes->getNamedItem('TintValue')->value;         // between 0 and 100
            $baseColor = $item->attributes->getNamedItem('BaseColor')->value;           // an IDML color object reference
            if (!array_key_exists($baseColor, $this->declaredColors))
            {
                CakeLog::debug("[IdmlDeclaredColors::parseTints] corresponding BaseColor $baseColor not found for Tint $self");
                continue;
            }
            $rgb = $this->declaredColors[$baseColor];
            $this->declaredColors[$self] = self::applyTintToRGB($rgb, $tintPercent);
        }
    }

    private function parseGradients(DOMNodeList $items)
    {
        foreach ($items as $item)
        {
            $self = $item->attributes->getNamedItem('Self')->value;
            $linearOrRadial = $item->attributes->getNamedItem('Type')->value;
            $obj = new IdmlGradient($linearOrRadial);

            // There are two or more gradient stops per gradient
            $xpath = new DOMXPath($item->ownerDocument);
            $stops = $xpath->query('GradientStop', $item);
            foreach ($stops as $stop)
            {
                $colorRef = $stop->attributes->getNamedItem('StopColor')->value;
                $location = $stop->attributes->getNamedItem('Location')->value;

                // convert the colorRef to an RGB hex value
                $rgb = $this->colorRefAsHex($colorRef);

                // add this stop to the gradient's list of stops
                $obj->addStop($rgb, $location);
            }

            $this->declaredGradients[$self] = $obj;
        }
    }

    /*
     * For a given IDML color object reference, return a string suitable for a CSS 'background-image'.
     * Note this is only suitable when the decodeContext is 'Decoration'
     *
     * @param $colorRef is an IDML color object reference which must be of type 'Gradient/name'
     * @param int $angle is number from 0 to 360
     * @returns string suitable for 'background-image' CSS
     */
    public function colorRefAsGradient($colorRef, $angle)
    {
        list($colorRefType, $colorRefName) = explode('/', $colorRef);
        if ($colorRefType != 'Gradient' )
        {
            CakeLog::debug("[IdmlDeclaredColors::colorRefAsGradient] inappropriate colorRefType $colorRef");
            return '';
        }

        if (!array_key_exists($colorRef, $this->declaredGradients))
            return '';

        $obj = $this->declaredGradients[$colorRef];
        return $obj->gradientAsCSS($angle);
    }

    /*
     * For a given IDML color object reference, return a string suitable for a CSS 'color'
     * Note this is only suitable when the decodeContext is 'Typography'
     *
     * @param $colorRef is an IDML color object reference which must be of type 'Gradient/name'
     * @returns string suitable for 'color' CSS
     */
    public function firstColorAsCSS($colorRef)
    {
        list($colorRefType, $colorRefName) = explode('/', $colorRef);
        if ($colorRefType != 'Gradient' )
        {
            CakeLog::debug("[IdmlDeclaredColors::firstColorAsCSS] inappropriate colorRefType $colorRef");
            return '';
        }

        if (!array_key_exists($colorRef, $this->declaredGradients))
            return '';

        $obj = $this->declaredGradients[$colorRef];
        return $obj->firstColorAsCSS();
    }

    /*
     * For a given IDML color object reference, return the CSS color hex value.
     * @param $colorRef is an IDML color object reference of the form 'Color/name', 'Ink/name', 'MixedInk/name', 'Tint/name'
     * @returns string suitable for CSS
     */
    public function colorRefAsHex($colorRef)
    {
        list($colorRefType, $colorRefName) = explode('/', $colorRef);

        // consider handling 'Gradient' using IdmlGradient::firstColorAsCSS

        if ($colorRefType != 'Color' &&
            $colorRefType != 'Ink' &&
            $colorRefType != 'MixedInk' &&
            $colorRefType != 'Tint')
        {
            CakeLog::debug("[IdmlDeclaredColors::colorRefAsHex] inappropriate colorRefType $colorRef");
            return '';
        }

        $rgb = $this->colorRefToRGB($colorRef);
        $hex = $this->rgbAsHex($rgb);
        return $hex;
    }

    /*
     * For the given array of three integers, which represent the RGB components of a color,
     * print the CSS equivalent in Hex notation.
     */
    public static function rgbAsHex($rgb)
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /*
     * For a given IDML color object reference, return an array of 3 RGB colors
     * $colorRef is an IDML color object reference of the form 'Color/name' or 'Tint/name'
     */
    public function colorRefToRGB($colorRef)
    {
        if (array_key_exists($colorRef, $this->declaredColors))
            return $this->declaredColors[$colorRef];
        else
            return array(0, 0, 0);
    }

    /*
     * Convert the given IDML color space and color value to RGB
     * @param string $colorSpace is 'RGB', 'CMYK' or 'LAB'
     * @param string $colorValues contains 3 or 4 space-delimited numbers that represent the RGB, CMYK or Lab colors
     * $returns an array with three integers from 0 to 255
     */
    public static function colorSpaceAndValueToRGB($colorSpace, $colorValues)
    {
        switch ($colorSpace)
        {
            case 'LAB':
                list($L, $a, $b) = explode(' ', $colorValues);
                $rgb = self::LABtoRGB($L, $a, $b);
                break;

            case 'CMYK':
                list($c, $m, $y, $k) = explode(' ', $colorValues);
                $rgb = self::CMYKtoRGB($c, $m, $y, $k);
                break;

            case 'RGB':
                list($r, $g, $b) = explode(' ', $colorValues);
                $rgb = array(round($r), round($g), round($b));
                break;

            default:
                CakeLog::debug("[IdmlDeclaredColors::colorSpaceAndValueToRGB] Unsupported color space $colorSpace");
                $rgb = array(0, 0, 0);
                break;
        }
        return $rgb;
    }

    /**
     * Convert CMYK to RGB
     *
     * From http://developer.loftdigital.com/blog/cmyk-rgb-and-php
     *
     * @static
     * @param $c
     * @param $m
     * @param $y
     * @param $k
     * @return array
     */
    public static function CMYKtoRGB($c,$m,$y,$k)
    {
        // Cast variables to doubles to prevent rounding errors:
        $c = (double) $c;
        $m = (double) $m;
        $y = (double) $y;
        $k = (double) $k;

        // convert into percentages
        $c = $c / 100;
        $m = $m / 100;
        $y = $y / 100;
        $k = $k / 100;

        $r = 1 - ($c * (1 - $k)) - $k;
        $g = 1 - ($m * (1 - $k)) - $k;
        $b = 1 - ($y * (1 - $k)) - $k;

        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);

        return array($r, $g, $b);
    }

    /**
     * Convert LAB to XYZ
     *
     * @static
     * @param $l
     * @param $a
     * @param $b
     * @return array
     */
    public static function LABtoXYZ($L, $a, $b)
    {
        $L = (double) $L;
        $a = (double) $a;
        $b = (double) $b;
        $var_Y = ($L + 16) / 116;
        $var_X = ($a / 500) + $var_Y;
        $var_Z = $var_Y - ($b / 200);

        if (pow($var_Y, 3) > 0.008856)
        {
            $var_Y = pow($var_Y, 3);
        }
        else
        {
            $var_Y = ($var_Y - 16 / 116) / 7.787;
        }

        if (pow($var_X, 3) > 0.008856)
        {
            $var_X = pow($var_X, 3);
        }
        else
        {
            $var_X = ($var_X - 16 / 116) / 7.787;
        }

        if (pow($var_Z, 3) > 0.008856)
        {
            $var_Z = pow($var_Z, 3);
        }
        else
        {
            $var_Z = ($var_Z - 16 / 116) / 7.787;
        }

        $ref_X = 95.047;
        $ref_Y = 100.000;
        $ref_Z = 108.883;

        $X = $ref_X * $var_X;
        $Y = $ref_Y * $var_Y;
        $Z = $ref_Z * $var_Z;

        return array($X, $Y, $Z);
    }

    /**
     * Convert LAB to RGB.
     *
     * @static
     * @param $l
     * @param $a
     * @param $b
     * @return array
     */
    public static function LABtoRGB($L, $a, $b)
    {
        // First we have to convert LAB into XYZ. Adapted from easyrgb.com.
        list($x,$y,$z) = self::LABtoXYZ($L, $a, $b);

        $var_X = $x / 100;
        $var_Y = $y / 100;
        $var_Z = $z / 100;

        $var_R = $var_X * 3.2406 + $var_Y * -1.5372 + $var_Z * -0.4986;
        $var_G = $var_X * -0.9689 + $var_Y * 1.8758 + $var_Z * 0.0415;
        $var_B = $var_X * 0.0557 + $var_Y * -0.2040 + $var_Z * 1.0570;

        if ($var_R > 0.0031308)
        {
            $var_R = 1.055 * (pow($var_R, (1/2.4))) - 0.055;
        }
        else
        {
            $var_R = 12.92 * $var_R;
        }

        if ($var_G > 0.0031308)
        {
            $var_G = 1.055 * (pow($var_G, (1/2.4))) - 0.055;
        }
        else
        {
            $var_G = 12.92 * $var_G;
        }

        if ($var_B > 0.0031308)
        {
            $var_B = 1.055 * (pow($var_B, (1/2.4))) - 0.055;
        }
        else
        {
            $var_B = 12.92 * $var_B;
        }

        // Scale the calculaiont to the 0-255 range, making sure no values exceed the range limits.
        $R = max(min(round($var_R * 255), 255),0);
        $G = max(min(round($var_G * 255), 255),0);
        $B = max(min(round($var_B * 255), 255),0);

        return array($R, $G, $B);
    }

    /*
     * Convert the given Color and TintValue into a CSS ready rgb(N,N,N) value.
     * This static function can be called by this class's parsing functions or by an IDMLElement that has overridden
     * 'Color' and 'TintValue' properties.
     * @param array $rgb is an array of three integers
     * @param int $tintPercent is a number from 0 to 100, which is a percentage of the color
     * @returns an array with three integers containing the new RGB values
     */
    static public function applyTintToRGB($rgb, $tintPercent)
    {
        // IDML Specification: 'FillTint' is the percent of tint to use in the page item’s FillColor. To specify a
        // tint percent, use a number in the range of 0 to 100; to use the inherited or overridden value, use -1.

        if ($tintPercent == -1)
            return $rgb;

        $pct = 1.0 - ($tintPercent / 100.0);

        $ri = 255 - $rgb[0];
        $r = $rgb[0] + ($ri * $pct);

        $gi = 255 - $rgb[1];
        $g = $rgb[1] + ($gi * $pct);

        $bi = 255 - $rgb[2];
        $b = $rgb[2] + ($bi * $pct);

        return array(round($r), round($g), round($b));
    }

}
