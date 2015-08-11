<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlDeclaredStyle.php
 *
 * @class   IdmlDeclaredStyle
 *
 * @description A Declared Style is a collection of properties which are key-value pairs
 *  that hold the InDesign attributes and properties of a <CharacterStyle>, <ParagraphStyle>, <ObjectStyle>,
 *  <TableStyle> or <CellStyle>.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlParserHelper',       'Import/Idml');
App::uses('IdmlDecode',             'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeFactory',      'Import/Idml/Styles/Decoders');
App::uses('IdmlDeclarationManager', 'Import/Idml/Styles/Declarations');
App::uses('IdmlCharacterStyle',     'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlParagraphStyle',     'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlObjectStyle',        'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlTableStyle',         'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlCellStyle',          'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlContextualStyle',    'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlDeclaredStyle
{
    /*
     * @var string $styleTemplateName is either 'ParagraphStyle', 'CharacterStyle', 'ObjectStyle', 'TableStyle' or 'CellStyle'
     */
    public $styleTemplateName;

    /*
     * @var string $shortNameSuffix is used by getClassName to ensure that IDML Names can be used to create
     * unique CSS classnames: 'Para', 'Char', 'Obj', 'Table', 'Cell'
     */
    public $shortNameSuffix;

    /*
     * @var string $idmlContextualElement is used only for the derived class 'IdmlContextualStyle'.
     * It is the IDML tag name of the element whose attributes are being processed.
     */
    public $idmlContextualElement;

    /*
     * @var string $decodeContext is an enum that is needed when disambiguating the meaning of certain IDML properties
     * For example, when 'FillColor' is encountered on a Rectangle it should be interpreted as a CSS 'background-color'
     * but when it is encountered on a ParagraphRange it should be interpreted as a CSS 'color'.
     * This is only needed for a few properties.
     */
    public $decodeContext;

    /*
     * @var array $idmlKeyValues is an associative array of key-value pairs that are read directly from the IDML package
     * by the parse() function.
     */
    public $idmlKeyValues;

    /*
     * @var array $cssKeyValues is an associative array of key-value pairs that are decoded from IDML into CSS
     * by the convert() function.
     */
    public $cssKeyValues;

    /*
     * @var $cssPseudoSelectors is an associative array.
     * The key to the array is a CSS pseudo selector, such as, for example 'first-child' or 'last-child'.
     * The value of the array is another associative array, that uses the same semantics as $cssKeyValues, for example:
     * 'first-child' => array('padding' => '0 10px 10px 10px', 'margin' => '0', 'text-indent' => '50px' )
     */
    public $cssPseudoSelectors;

    /*
     * @var $cssTagSpecificSelectors is an associative array.
     * The key to the array is an HTML tagname, such as, for example 'ul' or 'ol'.
     * The value of the array is another associative array, that uses the same semantics as $cssKeyValues, for example:
     * 'ol' => array('list-style-position' => 'outside', 'margin' => '0', 'list-style-type' => 'disc' )
     */
    public $cssTagSpecificSelectors;

    /**
     * @var array(arrays) $cssChildPseudoSelectors
     * Saves css to be applied to html child elements.
     * Array key is the additional text to indicate the child (e.g. 'li:before').
     */
    public $cssChildSelectors;

    /**
     * @var array $allStyles - stores the hierarchy of applied styles for the current element and its ancestors
     */
    protected $allStyles;

    /**
     * @var array $appliedStyleNames - stores all applied style names for recognition purposes
     */
    protected $appliedStyleNames = array(
        'AppliedParagraphStyle',
        'AppliedCharacterStyle',
        'AppliedObjectStyle',
        'AppliedTableStyle',
        'AppliedCellStyle',
    );

    /**
     * @var array $computedBorders - saved the border widths, which are needed by IdmlHtmlProducer to allow the actual
     * object's dimensions to be increased by the width of the object's borders.
     * Careful: any of the 6 derived classes may write to this value, but only IdmlContextualStyle can read from it.
     */
    protected $computedBorders;


    /*
     * @param string $styleTemplateName required for all
     * @param string $shortNameSuffix required for true styles
     * @param string $idmlContextualElement required for inline contextual style
     */
    public function __construct($styleTemplateName, $shortNameSuffix, $idmlContextualElement)
    {
        $this->styleTemplateName = $styleTemplateName;
        $this->shortNameSuffix = $shortNameSuffix;
        $this->idmlContextualElement = $idmlContextualElement;
        $this->idmlKeyValues = array();
        $this->cssKeyValues = array();
        $this->cssPseudoSelectors = array();
        $this->cssTagSpecificSelectors = array();
        $this->cssChildSelectors = array();
        $this->allStyles = array();
        $this->computedBorders = 0;

        switch($styleTemplateName)
        {
            case 'ParagraphStyle':
            case 'CharacterStyle':
                $this->decodeContext = 'Typography';
                break;

            case 'ObjectStyle':
            case 'TableStyle':
            case 'CellStyle':
                $this->decodeContext = 'Decoration';
                break;

            case 'ContextualStyle':
                switch($idmlContextualElement)
                {
                    case 'GraphicLine':
                    case 'Polygon':
                    case 'Oval':
                        $this->decodeContext = 'SVG';
                        break;

                    case 'ParagraphStyleRange':
                    case 'CharacterStyleRange':
                    case 'Content':
                    case 'Change':
                    case 'HyperlinkTextSource':
                    case 'HyperlinkTextDestination':
                    case 'TextVariableInstance':
                        $this->decodeContext = 'Typography';
                        break;

                    case 'TextFrame':
                    case 'Table':
                    case 'Row':
                    case 'Column':
                    case 'Cell':
                    case 'Rectangle':
                    case 'Image':
                    case 'PDF':
                    case 'EPS':
                    case 'Group':
                    case 'XMLElement':
                    case 'Movie':
                    case 'Sound':
                        $this->decodeContext = 'Decoration';
                        break;

                    default:
                        CakeLog::debug("[IdmlDeclaredStyle::__construct] Unhandled idmlContextualElement $idmlContextualElement");
                        break;
                }
                break;

            default:
                CakeLog::debug("[IdmlDeclaredStyle::__construct] Unhandled styleTemplateName $styleTemplateName");
                break;
        }
    }

    /*
     * Parse this <xxxStyle> to obtain all attributes and child properties
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        $this->parseChildrenRecursively($node, '');
    }


    /*
     * Rcursively parse the given node's children looking for any elements that have been explicitly flagged as being important
     * @param $node is the node to examine
     * @param $nodeNamespace is a string value to prepend to the key, something like '' or 'TextWrapPreferences' or 'TextWrapPreferences::Properties'
     */
    public function parseChildrenRecursively($node, $nodeNamespace)
    {
        // Within the node there are many, many attributes that we *may* be interested in
        foreach($node->attributes as $key => $attr)
        {
            // For example <ParagraphStyle FontStyle="Regular" /> becomes: $idmlKeyValues['FontStyle'] => 'Regular'
            // For example, <Properties><AppliedFont>Minion Pro</AppliedFont></Properties> becomes: $idmlKeyValues['Properties::AppliedFont'] => 'Minion Pro'
            if ($nodeNamespace != '')
                $key = $nodeNamespace . '->' . $key;

            $this->idmlKeyValues[$key] = $attr->value;
        }

        // Now loop through all child elements
        foreach($node->childNodes as $childNode)
        {
            if ($childNode->nodeType != XML_ELEMENT_NODE)
                continue;

            if (IdmlParserHelper::isParsableChildIdmlObjectNode($childNode) == true)
                continue;

            if ($nodeNamespace == '')
                $key =  $childNode->tagName;
            else
                $key = $nodeNamespace . '::' . $childNode->tagName;

            $value = $childNode->textContent;
            $key = $this->makeUniqueKey($key);
            $this->idmlKeyValues[$key] = $value;

            $this->parseChildrenRecursively($childNode, $key);
        }
    }

    /**
     * Some keys occur multiple times, like "TextFramePreference::Properties::InsetSpacing::ListItem", so append a digit onto the end
     * @param string $key
     * @return string $k
     */
    public function makeUniqueKey($key)
    {
        $next = 1;
        $k = $key;

        while (array_key_exists($k, $this->idmlKeyValues))
        {
            $next++;
            $k = $key . $next;
        }

        return $k;
    }


    /*
     * The basedOn function is used to walk up the master-subordinate style hierarchy
     */
    public function basedOn()
    {
        $basedOn = array_key_exists('Properties::BasedOn', $this->idmlKeyValues) ? $this->idmlKeyValues['Properties::BasedOn']: '';

        // "$ID/[No Paragraph style]" and its analogues aren't prefixed like all the others
        if( strpos($basedOn, $this->styleTemplateName) !== 0 && $basedOn !== '')
            $basedOn = $this->styleTemplateName . '/' . $basedOn;

         return $basedOn;
    }

    /**
     * Add a CSS keyword/value pair to the CSS context for the purpose of explicitly creating a cross-device baseline
     * @param string $cssPropertyName is a CSS property name
     * @param string $cssPropertyValue is a CSS property value, without the closing semicolon.
     * @returns void
     */
    protected function resetCSS($cssPropertyName, $cssPropertyValue)
    {
        $this->cssKeyValues[$cssPropertyName] = $cssPropertyValue;
    }

    /**
     * Convert the IDML properties into CSS equivalents
     * @return string containing the CSS equivalents for every IDML property in this declared style
     */
    public function convertIdmlToCSS()
    {
        $this->convert();
        return $this->getCSS();
    }

    /**
     * Convert the IDML properties into CSS equivalents
     */
    protected function convert()
    {
        // The array of $duplexStyles provides a mechanism for finding styles which work together.
        // For every style in this array, the convert function will be called, which matches the style in the array
        // with its partner. For instance, FontStyle needs to be added to the AppliedFont value, so the convert function
        // is called for that styles to match the FontStyle property with the nearest AppliedFont.

        // $duplexStyles contains the property name for the key and the fallback value for the value.
        $duplexStyles = array(
            'FontStyle' => 'Regular',
        );

        // Convert the current set of values in the declared style
        foreach ($this->idmlKeyValues as $key => $value)
        {
            // Special case coding: if this is a contextual style, the property is FillTint equal to -1, and there's no FillColor in the context, don't apply it.
            // This addresses a very specific case where an unintended styling of a character range overrides the correct color property assigned to the paragraph.
            if ($this->styleTemplateName == 'ContextualStyle' &&
                $key == 'FillTint' &&
                $value == '-1' &&
                !array_key_exists('FillColor', $this->idmlKeyValues))
            {
                continue;
            }

            $this->convertOneStyle($key, $value);
        }

        // Manage 'duplex' styles: those where two values must be combined.
        if (get_class($this) != 'IdmlContextualStyle')
        {
            foreach ($duplexStyles as $property => $default)
            {
                $this->convertOneStyle($property, $default); 
            }
        }

        $this->shortFormBorders();
    }

    protected function convertOneStyle($key, $value)
    {
        $decoder = IdmlDecodeFactory::instantiate($this, $key, $value);
        $decoder->convert();
    }

    /**
     * If all four borders are the same, use the short form
     */
    protected function shortFormBorders()
    {
        $borderTop    = array_key_exists('border-top',    $this->cssKeyValues) ? $this->cssKeyValues['border-top']    : 'missing';
        $borderRight  = array_key_exists('border-right',  $this->cssKeyValues) ? $this->cssKeyValues['border-right']  : 'missing';
        $borderBottom = array_key_exists('border-bottom', $this->cssKeyValues) ? $this->cssKeyValues['border-bottom'] : 'missing';
        $borderLeft   = array_key_exists('border-left',   $this->cssKeyValues) ? $this->cssKeyValues['border-left']   : 'missing';
        if ($borderTop == $borderRight &&
            $borderRight == $borderBottom &&
            $borderBottom == $borderLeft &&
            $borderLeft != 'missing')
        {
            $this->cssKeyValues['border'] = $borderTop;
            unset($this->cssKeyValues['border-top']);
            unset($this->cssKeyValues['border-right']);
            unset($this->cssKeyValues['border-bottom']);
            unset($this->cssKeyValues['border-left']);
        }
    }


    /**
     * The getClassName function converts the IDML declared style name into a suitable CSS class name.
     * @return mixed|string
     */
    public function getClassName()
    {
        $illegal_css = str_split("\0\n\r\t\\ ~`!@#$%^&*()+={}[]<>|?/:;,.•");
        $cleanName = str_replace('$ID', '', $this->idmlKeyValues['Name']);
        $cleanName = str_replace($illegal_css, ' ', $cleanName);
        $cleanName = ucwords($cleanName);
        $cleanName = str_replace(' ', '', $cleanName);
        $cleanName .= $this->shortNameSuffix;
        if (is_numeric($cleanName[0]))
            $cleanName = $this->shortNameSuffix[0] . $cleanName;

        return $cleanName;
    }

    /**
     * The getCSS function concatenates the individually converted CSS key-values pairs together.
     * @return string containing all the CSS for this IDML declared style
     */
    protected function getCSS()
    {
        $css = array();

        // first assemble the regular CSS
        $css[] = "";
        $css[] = '.' . $this->getClassName() . ' {';
        foreach ($this->cssKeyValues as $key => $value)
            $css[] = $this->formatCSS($key, $value);
        $css[] = '}';


       // then assemble the pseudo-selector CSS
       foreach( $this->cssPseudoSelectors as $pseudoName => $pseudoKeyValues )
       {
           $css[] = "";
           $css[] = '.' . $this->getClassName() . ':' . $pseudoName . ' {';
           foreach ($pseudoKeyValues as $key => $value)
           {
               $css[] = $this->formatCSS($key, $value);
           }
           $css[] = '}';
       }

       // then assemble the tag-specific CSS
       foreach( $this->cssTagSpecificSelectors as $tagName => $tagKeyValues )
       {
           $css[] = "";
           $css[] = $tagName . '.' . $this->getClassName() . ' {';
           foreach ($tagKeyValues as $key => $value)
           {
               $css[] = $this->formatCSS($key, $value);
           }
           $css[] = '}';
       }

        // Then add child-specific selectors
        foreach ($this->cssChildSelectors as $childSelector => $tagKeyValues)
        {
            $css[] = "";
            $css[] = '.' . $this->getClassName() . ' ' . $childSelector . ' {';
            foreach ($tagKeyValues as $key => $value)
            {
                $css[] = $this->formatCSS($key, $value);
            }
            $css[] = '}';
        }

       return implode("\n", $css);
    }

    /**
     * The formatCSS function provides a way for all css property-value pairs to be formatted similarly.
     * Changing this one method will allow different formatting preferences to be implemented.  Everyone has their own
     * definition of what is most pleasing and easy to read in this regard.
     *
     * @param string $cssPropertyName
     * @param string $cssPropertyValue
     * @returns string contains CSS property:value;
     */
    private function formatCSS($cssPropertyName, $cssPropertyValue)
    {
        return sprintf("\t%-26.26s : %s;", $cssPropertyName, $cssPropertyValue);
    }

    /**
     * Generic getter function for protected property $allStyles
     * @return array
     */
    public function getAllStyles()
    {
        return $this->allStyles;
    }

    public function setComputedBorders($computedBorders)
    {
        $this->computedBorders = $computedBorders;
    }
}
?>