<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlSvgStyleManager.php
 *
 * @class   IdmlSvgStyleManager
 *
 * @description Manager to convert svg style properties to their equivalent HTML values.
 *              svg tags in HTML use different style property names than non-svg tags.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclarationManager',   'Import/Idml/Styles/Declarations');
App::uses('IdmlDeclaredColors',       'Import/Idml/Styles/Declarations');

class IdmlSvgStyleManager
{
    /**
     * @var array - maps all IDML properties to their corresponding svg property names
     */
    private $conversionStyles = array(
        'FillColor'=>'fill',
        'StrokeColor'=>'stroke',
        'StrokeWeight'=>'stroke-width',
    );

    /**
     * @var array - contains all styles, contextual and applied, for the svg element we're styling
     */
    private $allStyles;

    /**
     * This method produces a string containing what will become the style attribute of the IdmlSvgShape.
     * It parses the array of IDML properties that must be converted. For each property, it searches for a matching value
     * applied to the element, and, if one is found, converts it to the svg style property value.
     * Finally, it concatenates all the name/value pairs it finds into a string to be returned as the style attribute.
     * @param IdmlElement $element - the IdmlPolygon or IdmlOval we're styling
     * @return string $convertedStyleString - semicolon separated list of name/value style attributes
     */
    public function convertSvgStyles(IdmlElement $element)
    {
        $this->setAllStyles($element);

        // Go through the property array and call the conversion function for any which have been assigned to the element
        $convertedStyles = array();
        foreach ($this->conversionStyles as $property => $attribute)
        {
            if (array_key_exists($property, $this->allStyles))
            {
                $functionName = 'convert' . $property;
                $convertedStyles[$attribute] = $this->$functionName($element, $this->allStyles[$property]);
            }
        }

        // Convert the array of style name/value pairs to a string
        $convertedStyleStr = '';

        foreach ($convertedStyles as $attribute => $value)
        {
            $convertedStyleStr .= $attribute . ':' . $value . ';';
        }

        return $convertedStyleStr;
    }

    /**
     * The following methods are called dynamically, based on the IDML property name.
     * Each converts an IDML property's value to its svg equivalent.
     */

    private function convertFillColor($element, $value)
    {
        return $this->convertColor($value, 'FillTint', 'none');
    }

    private function convertStrokeColor($element, $value)
    {
        return $this->convertColor($value, 'StrokeTint', '#000000');
    }

    private function convertStrokeWeight($element, $value)
    {
        return $value;
    }

    /**
     * @param string $idmlColor - raw value of the IDML color property
     * @param string $tintPropName - 'FillTint'|'StrokeTint'
     * @param string $defaultColor - '#000000' (black) for stroke, 'none' for fill
     * @return string - rgb value of the color/tint
     */
    private function convertColor($idmlColor, $tintPropName, $defaultColor)
    {
        $colorArray = explode('/', $idmlColor);
        $rawColor = $colorArray[count($colorArray) - 1];

        // If no color is assigned, return the default.
        // If the assigned color is a gradient, it's processed as a background color of the svg parent element and should not be managed here.
        if ($rawColor == 'None' || $colorArray[0] == 'Gradient')
        {
            return $defaultColor;
        }

        $handle = IdmlDeclarationManager::getInstance()->declaredColorHandler;

        $rgbRaw = $handle->colorRefToRGB($idmlColor);

        $rgbTint = (isset($this->allStyles['FillTint'])) ? $handle->applyTintToRGB($rgbRaw, $this->allStyles[$tintPropName]) : $rgbRaw;

        return $handle->rgbAsHex($rgbTint);
    }

    /**
     * Populates the array of all the elements styles: contextual and applied.
     * @param IdmlElement $element
     */
    private function setAllStyles(IdmlElement $element)
    {
        // Get applied styles. (IDML defaults the applied style name to 'n' if there is no applied style).
        if ($element->appliedStyleName != 'n')
        {
            $appliedStyle = $element->appliedStyleName;
            $declarationMgr = IdmlDeclarationManager::getInstance();
            $appliedStyleKeys = $declarationMgr->declaredStyles[$appliedStyle]->idmlKeyValues;
        }
        else
        {
            $appliedStyleKeys = array();
        }

        // Get contextual styles
        $contextualStyles = isset($element->contextualStyle->idmlKeyValues) ? $element->contextualStyle->idmlKeyValues : array();

        // Merge the styles into a single array
        $this->allStyles = array_merge($appliedStyleKeys, $contextualStyles);
    }


}