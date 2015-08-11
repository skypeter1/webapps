<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeCorners.php
 *
 * @class   IdmlDecodeCorners
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode', 'Import/Idml/Decoders');


class IdmlDecodeCorners extends IdmlDecode
{
    public function convert()
    {
        $enable = (array_key_exists('EnableStrokeAndCornerOptions', $this->idmlContext)) ? $this->idmlContext['EnableStrokeAndCornerOptions'] : 'true';
        if ($enable == 'false')
            return;

        $topLeftOption     = (array_key_exists('TopLeftCornerOption', $this->idmlContext)) ? $this->idmlContext['TopLeftCornerOption'] : 'Square';
        $topRightOption    = (array_key_exists('TopRightCornerOption', $this->idmlContext)) ? $this->idmlContext['TopRightCornerOption'] : 'Square';
        $bottomLeftOption  = (array_key_exists('BottomLeftCornerOption', $this->idmlContext)) ? $this->idmlContext['BottomLeftCornerOption'] : 'Square';
        $bottomRightOption = (array_key_exists('BottomRightCornerOption', $this->idmlContext)) ? $this->idmlContext['BottomRightCornerOption'] : 'Square';

        // InDesign has several corner options, the most likely being 'RoundedCorner', but this algorithm simply accepts
        // any of them except 'Square' as being the equivalent of 'RoundedCorner'
        if ($topLeftOption    == $topRightOption &&
            $topRightOption   == $bottomLeftOption &&
            $bottomLeftOption == $bottomRightOption)
        {
            $genericOption = (array_key_exists('CornerOption', $this->idmlContext)) ? $this->idmlContext['CornerOption'] : 'Square';
            $isGenericOption = true;
        }
        else
            $isGenericOption = false;

        $topLeftRadius     = (array_key_exists('TopLeftCornerRadius',     $this->idmlContext)) ? $this->idmlContext['TopLeftCornerRadius'] : '0';
        $topRightRadius    = (array_key_exists('TopRightCornerRadius',    $this->idmlContext)) ? $this->idmlContext['TopRightCornerRadius'] : '0';
        $bottomLeftRadius  = (array_key_exists('BottomLeftCornerRadius',  $this->idmlContext)) ? $this->idmlContext['BottomLeftCornerRadius'] : '0';
        $bottomRightRadius = (array_key_exists('BottomRightCornerRadius', $this->idmlContext)) ? $this->idmlContext['BottomRightCornerRadius'] : '0';

        $topLeftRadius     = round($topLeftRadius,2);
        $topRightRadius    = round($topRightRadius,2);
        $bottomLeftRadius  = round($bottomLeftRadius,2);
        $bottomRightRadius = round($bottomRightRadius,2);

        if ($topLeftRadius    == $topRightRadius   &&
            $topRightRadius   == $bottomLeftRadius &&
            $bottomLeftRadius == $bottomRightRadius)
        {
            $genericRadius = (array_key_exists('CornerRadius', $this->idmlContext)) ? $this->idmlContext['CornerRadius'] : '0';
            $genericRadius = round($genericRadius,2);
            $isGenericRadius = true;
        }
        else
            $isGenericRadius = false;

        // Use the shorthand form if possible
        if ($isGenericOption && $isGenericRadius)
        {
            if ($genericRadius == 0 || $genericOption == 'None')
            {
                return;
            }
//            else if ($genericOption == 'Square')
//            {
//                $this->registerCSS('border-radius', '0');
//            }
            else
            {
                $this->registerCSS('border-radius', $genericRadius . 'px');
            }

            return;
        }

        else if($topLeftOption     != 'Square' && $topLeftOption     != 'None' &&
            $topRightOption    != 'Square' && $topRightOption    != 'None' &&
            $bottomLeftOption  != 'Square' && $bottomLeftOption  != 'None' &&
            $bottomRightOption != 'Square' && $bottomRightOption != 'None' )
        {
            $s = sprintf("%spx %spx %spx %spx", $topLeftRadius, $topRightRadius, $bottomRightRadius, $bottomLeftRadius);
            $this->registerCSS('border-radius', $s);
        }

        // otherwise use the longhand form for each corner
        else
        {
            if ($topLeftOption == 'Square' || $topLeftOption == 'None')
                $this->registerCSS('border-top-left-radius', '0');
            else
                $this->registerCSS('border-top-left-radius', $topLeftRadius . 'px');

            if ($topRightOption == 'Square' || $topRightOption == 'None')
                $this->registerCSS('border-top-right-radius', '0');
            else
                $this->registerCSS('border-top-right-radius', $topRightRadius . 'px');

            if ($bottomLeftOption == 'Square' || $bottomLeftOption == 'None')
                $this->registerCSS('border-bottom-left-radius', '0');
            else
                $this->registerCSS('border-bottom-left-radius', $bottomLeftRadius . 'px');

            if ($bottomRightOption == 'Square' || $bottomRightOption == 'None')
                $this->registerCSS('border-bottom-right-radius', '0');
            else
                $this->registerCSS('border-bottom-right-radius', $bottomRightRadius . 'px');
        }
    }
}
?>