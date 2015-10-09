<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlElement.php
 * 
 * @class   IdmlElement
 * 
 * @description This base class defines an element on a page. Elements come in two varieties: block-level and inline.
 *          Elements are the parts of a story that correspond to paragraph ranges, character ranges, images, frames
 *          and groups.
 *          This class holds information; it is not a parser or a producer.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlVisitable',              'Import/Idml');
App::uses('IdmlParsable',               'Import/Idml');
App::uses('IdmlElementFactory',         'Import/Idml/PageElements');
App::uses('IdmlDeclarationManager',     'Import/Idml/Styles/Declarations');
App::uses('IdmlContextualStyle',        'Import/Idml/Styles/Declarations/DeclaredStyle');


class IdmlElement implements IdmlVisitable, IdmlParsable
{
    /**
     * Array of children elements.
     *
     * @var array Array<IdmlElement> of children elements.
     */
    public $childrenElements = array();

    /**
     * Parent idml element.
     * 
     * @var IdmlElement
     */
    public $parentElement = null;

    /**
     * HTML id attribute
     * @var string 
     */
    public $idAttribute = null;


    /*
     * @var IdmlContextualStyle $contextualStyle is an object that contains all of this element's attributes plus
     * all of its <Properties>.  See the IdmlDeclaredStyle base class for details.  The most important aspects of
     * this are that the parser adds all attributes and properties to this object's $idmlKeyValues and the Producer
     * reads from it's $cssKeyValues.
     */
    public $contextualStyle = null;

    /**
     * Transformation of the object.
     * @var IdmlTransformation
     */
    public $transformation;

    /**
     * Is object visible.
     * @var boolean
     */
    public $visible = true;
    
    /**
     * @var $dmlTag name (rendered html tag) associated with this element
     */
    public $idmlTag = '';
    
    /**
     * hash of attributes associated with this element
     * @var array 
     */
    public $attributes = array();

    /**
     * @var boolean $embedded Is this element contained within an outer TextFrame (true) or is it directly on the Spread (false)?
     * This is needed mostly by IdmlTextFrame, IdmlRectangle, and IdmlGroup to determine whether to use
     * absolute or relative positioning when producing Fixed Layout.
     */
    public $embedded;

    /* One of these: 'AppliedParagraphStyle', 'AppliedCharacterStyle', 'AppliedObjectStyle', 'AppliedTableStyle' or 'AppliedCellStyle' */
    public $appliedStyleType = '';

    /* The style name provided in the IDML AppliedXXXStyle property  */
    public $appliedStyleName = '';


    /**
     * @var array contains elements page-relative top and left positions, useful for positioning other elements embedded inside this one.
     */
    protected $positions = array();

    /**
     * @var array $properties - contains property values for page item tags in the manifest
     * These are only used with text frames and pages, either of which may represent a single xhtml page.
     */
    public $properties = array();

    /**
     * @var $rotationOffset - When an element is rotated, its height changes. Since the element's boundary remains the same,
     *   we need offset that amount of page space by adding divs before and after the element.
     */
    public $rotationOffset = null;

    /**
     * Array of attributes to be added to the HTML element generated by this IDML element.
     */
    public $attribs;

    /**
     * @var boolean @usesNestedClass - indicates whether the element may be styled as part of a nested class, or affect the application of the nested class.
     * Set to false by default; set to true in constructors for IdmlTab, IdmlText, IdmlProcessingInstruction, and IdmlTextVariableInstance
     */
    protected $usesNestedClass;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->embedded = false;
        $this->attribs = array();
        $this->usesNestedClass = false;
    }

    /**
     * Accept idml visitor.
     *
     * @param IdmlVisitor $visitor CssManager or HtmlProduct, etc.
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        // The base class does nothing.
    }

     /**
     * Parse from DOM node.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        if ($node->hasAttribute('AppliedParagraphStyle'))
        {
            $this->appliedStyleType = 'AppliedParagraphStyle';
            $this->appliedStyleName = $node->getAttribute('AppliedParagraphStyle');
        }
        else if ($node->hasAttribute('AppliedCharacterStyle'))
        {
            $this->appliedStyleType = 'AppliedCharacterStyle';
            $this->appliedStyleName = $node->getAttribute('AppliedCharacterStyle');
        }
        else if ($node->hasAttribute('AppliedObjectStyle'))
        {
            $this->appliedStyleType = 'AppliedObjectStyle';
            $this->appliedStyleName = $node->getAttribute('AppliedObjectStyle');
        }
        else if ($node->hasAttribute('AppliedTableStyle'))
        {
            $this->appliedStyleType = 'AppliedTableStyle';
            $this->appliedStyleName = $node->getAttribute('AppliedTableStyle');
        }
        else if ($node->hasAttribute('AppliedCellStyle'))
        {
            $this->appliedStyleType = 'AppliedCellStyle';
            $this->appliedStyleName = $node->getAttribute('AppliedCellStyle');
        }
        else
        {
            $this->appliedStyleType = '';
            $this->appliedStyleName = '';
        }


        $this->contextualStyle = new IdmlContextualStyle($node->tagName);
        $this->contextualStyle->parse($node);

        $ancestry = $this->parents();

        foreach($ancestry as $obj)
        {
            $objClass = get_class($obj);

            if (in_array($objClass, array('IdmlStory', 'IdmlGroup')))
            {
                $this->embedded = true;
                break;
            }
        }

        // Set the element's data-uid attribute, useful in comparing raw IDML and derived HTML
        if ($node->hasAttribute('Self'))
        {
            $this->attribs['data-uid'] = $node->getAttribute('Self');
        }
    }

    /**
     * Get all CSS style overrides for this element
     * @returns string suitable for use as an HTML style="" attribute
     */
    public function getStyleOverrides()
    {
        return $this->contextualStyle->convertIdmlToCSS();
    }

    /**
     * Get the computed width of the object's borders.
     * @returns int - the width, in pixels, of the object's border
     */
    public function getComputedBorders()
    {
        return $this->contextualStyle->getComputedBorders();
    }

    /**
     * Parse children.
     * @param DOMElement $parentNode
     */
    protected function parseChildren($parentNode)
    {
        foreach ($parentNode->childNodes as $childNode)
        {
            if (IdmlParserHelper::isParsableChildIdmlObjectNode($childNode))
            {
                $parsableObject = IdmlElementFactory::createFromNode($childNode);
                if(is_object($parsableObject))
                {
                    //set the parent first since sometimes parse requires or sets parent data
                    $parsableObject->parentElement = $this;

                    //set this parsable child object as a child of the parent (bypassing xml parent)
                    $this->childrenElements[] = $parsableObject;
                    $parsableObject->parse($childNode);                
                }
            }
        }
    }

    /**
     * Get an array of all parents, from nearest on up
     * @return array<IdmlElement>
     */
    public function parents()
    {
        if(!is_null($this->parentElement))
        {
            $parentElements = array();
            $parentNode = $this->parentElement;
            while(!is_null($parentNode))
            {
                $parentElements[] = $parentNode;
                $parentNode = $parentNode->parentElement;
            }
            return $parentElements; 
        }
        else
        {
            return array();
        }
    }  
    /**
     * return the parent Idml object
     * This method is created at all levels up to Page, etc. (where parentElement does not exist).
     * It allows simple uniform upward traversal of all Idml Objects while visitors are processing.
     */
    public function parentIdmlObject()
    {
        return $this->parentElement;
    }

    /*
     * Is this element contained within a TextFrame?
     * @return true if this element is contained within a parent <TextFrame>
     * @return false if this element is placed directly on a <Spread>
     */
    public function isEmbedded()
    {
        return $this->embedded;
    }


    public function setPosition($positions)
    {
        $this->positions = $positions;
    }

    public function getPosition($dimension)
    {
        return $this->positions[$dimension];
    }

    public function hasPositions()
    {
        return (isset($this->positions) && is_array($this->positions) && isset($this->positions['left']) && isset($this->positions['top']));
    }

    public function hasPosition($dimension)
    {
        return array_key_exists($dimension, $this->positions);
    }

    /**
     * Sets the transform data, both local and cumulative, for the element
     * @param DOMElement $node
     */
    public function setTransform(DOMElement $node)
    {
        $transformation = $node->hasAttribute(IdmlAttributes::ItemTransform) ? $node->getAttribute(IdmlAttributes::ItemTransform) : '';
        $this->transformation = new IdmlTransformation($transformation);

        // If the parent element contains a transformation, accumulate the tx and ty values
        if (is_a($this->parentElement, 'IdmlElement') && isset($this->parentElement->transformation))
        {
            // get the parent's cumulative transform values
            $cumTx = $this->transformation->xTranslate() + $this->parentElement->transformation->xTranslate();
            $cumTy = $this->transformation->yTranslate() + $this->parentElement->transformation->yTranslate();

            $this->transformation->setXY($cumTx, $cumTy);
        }
    }

    /**
     * @return string containing the canonical CSS classname applied to this element,
     *  suitable for use in an HTML class='' attribute
     */
    public function getCssClassname()
    {
        $idmlAppliedStyle = $this->appliedStyleName;

        if ($idmlAppliedStyle == '')
        {
            return '';
        }

        $mgr = IdmlDeclarationManager::getInstance();
        if (!array_key_exists($idmlAppliedStyle,  $mgr->declaredStyles))
        {
            return '';
        }

        $declaredStyle = $mgr->declaredStyles[$idmlAppliedStyle];

        // this will rarely happen, but might in the case of a <Change> element
        if (!$declaredStyle)
        {
            return '';
        }

        // this is the normal case
        return $declaredStyle->getClassName();
    }

    /**
     * Returns the closest IdmlParagraphRange ancestor of this IdmlElement
     * @return IdmlParagraph
     */
    protected function getParagraph()
    {
        $parentElement = $this->parentElement;

        while (!is_null($parentElement) && get_class($parentElement) != 'IdmlParagraphRange')
        {
            $parentElement = $parentElement->parentElement;
        }

        return $parentElement;
    }
}

?>