<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecode.php
 *
 * @class   IdmlDecode
 *
 * @description Convert an IDML property value (or attribute value) into a CSS property value.
 *
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlAssembler',              'Import/Idml');
App::uses('IdmlPackage',                'Import/Idml');
App::uses('IdmlDeclarationManager',     'Import/Idml/Styles/Declarations');
App::uses('IdmlDeclaredColors',         'Import/Idml/Styles/Declarations');
App::uses('IdmlStrokeStyle',            'Import/Idml/Styles/Declarations');
App::uses('IdmlNumberingFormat',        'Import/Idml/Styles/Declarations');


class IdmlDecode
{
    /**
     * @var $contextualStyle is an IdmlContextualStyle, initialized when the IdmlDecode object is instantiated by the instantiating object.
     * It is used in some (but not all) convert methods.
     */
    protected $contextualStyle;

    /*
     * @var string $idmlPropertyName is one of the IDML declared style properties, for example 'AppliedFont'
     */
    protected $idmlPropertyName;

    /*
     * @var string $idmlPropertyValue is the value corresponding to the IDML declared style property, for example 'Minion Pro'
     */
    protected $idmlPropertyValue;

    /*
     * @var array[string] $idmlContext is the complete idmlKeyValuePairs for the declared style or inline-overrides
     */
    protected $idmlContext;

    /*
     * @var array[string] $cssContext is the CSS Key-Value pairs conversion destination
     */
    protected $cssContext;

    /**
     * @var array[string] $cssTarget makes it possible to accurately apply one or more styles
     * to a single element and is only required when calling this->convertStroke()
     */
    protected $cssTarget;

    /*
     * @var array[ array[string] ] $cssPseudoSelectors is a collection of CSS pseudo-selectors each pointing to
     * a collection of CSS Key-Value pairs.
     */
    protected $cssPseudoSelectors;

    /*
     * @var array[ array[string] ] $cssTagSpecificSelectors is a collection of HTML tagnames each pointing to
     * a collection of CSS Key-Value pairs.
     */
    protected $cssTagSpecificSelectors;

    /**
     * @var array(arrays) $cssChildPseudoSelectors
     * Saves css to be applied to html child elements.
     * Array key is the additional text to indicate the child (e.g. 'li:before').
     */
    protected $cssChildSelectors;

    /*
     * @var is an enum that must be either 'Typography' or 'Decoration'.
     */
    protected $decodeContext;


    /**
     * @param string $idmlPropertyName is an IDML property name taken from an InDesign style declaration in /Resources/Styles.xml or
     * from an inline set of properties applied to a ParagraphRange, CharacterRange, etc.
     *
     * @param string $idmlPropertyValue is the corresponding value.
     *
     * @param array $idmlContext is an associative array of all IDML property names and their values. For a style declaration this
     * will be *all* of the style's properties. For a ParagraphRange or CharacterRange this will be the small set of overrides
     * that the InDesign author has applied to that range.
     *
     * @param &$cssContext is an associative array of all the CSS properties and their corresponding values that have
     * been translated so far by all prior IdmlDecode calls.
     *
     * @param $cssPseudoSelectors may be null or may be is an associative array. A reference to an associative array should
     * be passed when called by IdmlDeclaredStyles:convert(). A null should be passed for inline element overrides.
     * The key to the array is a CSS pseudo selector, such as, for example 'first-child' or 'last-child'. The value of the array
     * is another associative array, that uses the same semantics as $cssContext, for example:
     * 'first-child' => array('padding' => '0 10px 10px 10px', 'margin' => '0', 'text-indent' => '50px' )
     *
     * @param $cssTagSpecificSelectors may be null or may be is an associative array. A reference to an associative array should
     * be passed when called by IdmlDeclaredStyles:convert(). A null should be passed for inline element overrides.
     * The key to the array is an HTML tagname, such as, for example 'ol' or 'ul'. The value of the array
     * is another associative array, that uses the same semantics as $cssContext, for example:
     * 'list-style-position' => 'outside', 'padding-left' => '2em', 'text-indent' => '0' )
     *
     * @param string $decodeContext is an enum that is needed when disambiguating the meaning of certain IDML properties
     * For example, when 'FillColor' is encountered on a Rectangle it should be interpreted as a CSS 'background-color'
     * but when it is encountered on a ParagraphRange it should be interpreted as a CSS 'color'.
     * This is only needed for a few ambiguous properties. Must be either 'Typography' or 'Decoration'.

     */
    public function __construct($contextualStyle, $idmlPropertyName, $idmlPropertyValue)
    {
        $this->contextualStyle          = $contextualStyle;
        $this->idmlPropertyName         = $idmlPropertyName;
        $this->idmlPropertyValue        = $idmlPropertyValue;

        $this->idmlContext              = $contextualStyle->idmlKeyValues;
        $this->cssContext               = &$contextualStyle->cssKeyValues;
        $this->cssPseudoSelectors       = &$contextualStyle->cssPseudoSelectors;
        $this->cssTagSpecificSelectors  = &$contextualStyle->cssTagSpecificSelectors;
        $this->cssChildSelectors        = &$contextualStyle->cssChildSelectors;
        $this->decodeContext            = $contextualStyle->decodeContext;
    }

    /**
     * Derived classes must implement this
     */
    public function convert()
    {
        // IdmlDecode::convert() does nothing by default. This is meaningful because many of the InDesign properties
        // have no CSS equivalents, and must be completely ignored.
    }

    /**
     * Add a CSS keyword/value pair to the caller's CSS context.  Note that this may be called more than once for
     * certain CSS keywords, with each subsequent call overriding any previous call.
     * @param string $cssPropertyName is a CSS property name
     * @param string $cssPropertyValue is a CSS property value, without the closing semicolon.
     * @returns void
     */
    protected function registerCSS($cssPropertyName, $cssPropertyValue)
    {
        $this->cssContext[$cssPropertyName] = $cssPropertyValue;
    }

    /**
     * Checks to see whether a CSS property has been set.
     * Useful when an existing property value should not be overwritten.
     * @param $cssPropertyName
     * @return bool
     */
    protected function propertyIsSet($cssPropertyName)
    {
        return array_key_exists($cssPropertyName, $this->cssContext);
    }

    /**
     * Add a CSS keyword/value pair to the caller's CSS context, for the given pseudo-class.
     * @param string $pseudoSelector is one of the CSS defined pseudo selectors, like 'first-child', 'last-child', etc.
     * @param string $cssPropertyName is a CSS property name
     * @param string $cssPropertyValue is a CSS property value, without the closing semicolon.
     * @returns void
     */
    protected function registerPseudoCSS($pseudoSelector, $cssPropertyName, $cssPropertyValue)
    {
        // It is entirely possible for $this->cssPseudoSelectors to be null. This occurs when the factory method is called
        // on an IDML inline element rather than a declared style. Pseudo selectors can't be used there because the HTML
        // syntax for the style attribute doesn't provide for pseudos.
        if (!is_array($this->cssPseudoSelectors))
            return;

        // Add the pseudo selector to the map the first time it is encountered
        if (!array_key_exists($pseudoSelector, $this->cssPseudoSelectors))
            $this->cssPseudoSelectors[$pseudoSelector] = array();

        $this->cssPseudoSelectors[$pseudoSelector][$cssPropertyName] = $cssPropertyValue;
    }

    /**
     * Add a CSS keyword/value pair to the caller's CSS context, for the given HTML tag.
     * @param string $tagName is an HTML tag name like 'ul' or 'ol', etc.
     * @param string $cssPropertyName is a CSS property name
     * @param string $cssPropertyValue is a CSS property value, without the closing semicolon.
     * @returns void
     */
    protected function registerTagSpecificCSS($tagName, $cssPropertyName, $cssPropertyValue)
    {
        // For real styles, register the CSS using a tag-specific format, like "ol.myClassName { list-style-position: outside; }"
        if (is_array($this->cssTagSpecificSelectors))
        {
            // Add the tagName to the map the first time it is encountered
            if (!array_key_exists($tagName, $this->cssTagSpecificSelectors))
                $this->cssTagSpecificSelectors[$tagName] = array();

            $this->cssTagSpecificSelectors[$tagName][$cssPropertyName] = $cssPropertyValue;
        }

        // For ContextualStyles, which are inline styles, simply use the registerCSS() function which will merge
        // the property name into the element's style="" attribute.
        else
        {
            $this->registerCSS($cssPropertyName, $cssPropertyValue);
        }
    }

    /**
      * Add a CSS keyword/value pair to the caller's CSS context, for the given child element.
      * @param string $childSelector can be anything that appears *after* the CSS class name.
      *         For example, "li:before" might be used with an IDML style called "CustomList"
      *         which would result in a CSS declaration for ".CustomList li:before".
      * @param string $cssPropertyName is a CSS property name
      * @param string $cssPropertyValue is a CSS property value, without the closing semicolon.
      * @returns void
      */
    protected function registerChildCSS($childSelector, $cssPropertyName, $cssPropertyValue)
    {
        // It is entirely possible for $this->cssChildSelectors to be null. This occurs when the factory method is called
        // on an IDML inline element rather than a declared style. Child selectors can't be used there.
        if (!is_array($this->cssChildSelectors))
            return;

        // Add the child selector to the map the first time it is encountered
        if (!array_key_exists($childSelector, $this->cssChildSelectors))
            $this->cssChildSelectors[$childSelector] = array();

        $this->cssChildSelectors[$childSelector][$cssPropertyName] = $cssPropertyValue;
    }

    /**
     * This function solves an important problem that occurs when a ContextualStyle contains a property override that
     * is interdependent with a property that is in the element's AppliedStyle.
     * An example of this is when the FillTint property is on an element itself, but the corresponding FillColor
     * is on the element's AppliedCharacterStyle. Similarly an element may have a FontStyle while the corresponding
     * AppliedFont is on the element's AppliedParagraphStyle.
     * @param $idmlKeyName string something like 'FillColor'
     * @param $fallbackValue string is to be used if there is no AppliedStyle, or if it doesn't have the supplied idmlKeyName
     * @returns the IDML key value
     */
    public function getAppliedStyleKeyValue($idmlKeyName, $fallbackValue)
    {
        // if the IDML property exists in the context itself, use it.
        if (array_key_exists($idmlKeyName, $this->idmlContext))
        {
            return $this->idmlContext[$idmlKeyName];
        }

        else
        {
            if( array_key_exists('AppliedCharacterStyle', $this->idmlContext) )
                $styleRef = $this->idmlContext['AppliedCharacterStyle'];
            else if( array_key_exists('AppliedParagraphStyle', $this->idmlContext) )
                $styleRef = $this->idmlContext['AppliedParagraphStyle'];
            else if( array_key_exists('AppliedObjectStyle', $this->idmlContext) )
                $styleRef = $this->idmlContext['AppliedObjectStyle'];
            else if( array_key_exists('AppliedTableStyle', $this->idmlContext) )
                $styleRef = $this->idmlContext['AppliedTableStyle'];
            else if( array_key_exists('AppliedCellStyle', $this->idmlContext) )
                $styleRef = $this->idmlContext['AppliedCellStyle'];
            else
                return $fallbackValue;

            $idmlKeyValue = $fallbackValue;

            $mgr = IdmlDeclarationManager::getInstance();
            if( array_key_exists($styleRef, $mgr->declaredStyles) )
            {
                $styleObj = $mgr->declaredStyles[$styleRef];

                if( array_key_exists($idmlKeyName, $styleObj->idmlKeyValues) )
                {
                    $idmlKeyValue = $styleObj->idmlKeyValues[$idmlKeyName];
                }
            }

            return $idmlKeyValue;
        }
    }

    /**
     * findProperty finds the most proximate defined value of the property $propName.
     * It first looks for an override value in the contextual style itself.
     * If not found, it then parses through the array of applied style class names to find a match.
     *
     * @param string $propName - the name of the style property we're looking for
     * @param array $styleList - An array of all applied styles, in application order.
     * @param string $fallback - value assigned to the property if it was not defined
     * @return string $propValue - The value of the property, either in a contextual style or an applied (class) style
     */
    protected function findProperty($propName, $styleList, $fallback='')
    {
        $propValue = $fallback;

        if (array_key_exists($propName, $this->idmlContext))
        {
            // Value found in contextual style: return it
            return $this->idmlContext[$propName];
        }

        $declarationMgr = IdmlDeclarationManager::getInstance();

        // Go through the hierarchy of applied style classes to look for the property.
        foreach ($styleList as $style)
        {
            if (array_key_exists($style, $declarationMgr->declaredStyles))
            {
                $appliedStyle = $declarationMgr->declaredStyles[$style];

                if (array_key_exists($propName, $appliedStyle->idmlKeyValues))
                {
                    return $appliedStyle->idmlKeyValues[$propName];
                }
            }
        }
        // If we reached here, return the value assigned as the fallback in the invocation
        return $propValue;
    }
}
?>
