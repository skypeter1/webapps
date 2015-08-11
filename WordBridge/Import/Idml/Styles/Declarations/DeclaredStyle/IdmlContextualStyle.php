<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/DeclaredStyle/IdmlContextualStyle.php
 *
 * @class   IdmlContextualStyle
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredStyle',      'Import/Idml/Styles/Declarations/DeclaredStyle');


/**
 * Class IdmlContextualStyle is the collection of inline overrides for an element. This is not an IDML style per se,
 * rather it is the CSS that is to be applied to an HTML element's style="" attribute.
 */
class IdmlContextualStyle extends IdmlDeclaredStyle
{
    /*
     * $idmlContextualElement is the type of IDML element whose inline styles are being processed
     */
    public function __construct($idmlContextualElement)
    {
        parent::__construct('ContextualStyle', '', $idmlContextualElement);
    }

    /**
     * Saves the node--required only for contextual styles--after calling the parent class parse method.
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $this->setAllStyles($node);
    }

    /**
     * setAllStyles recurses back up the DOM from the original IDML element and populates an array of all the applied style
     * classes applied to the element. The applied style class name for all ancestors is stored, until the parent element
     * either is not set, or is not a DOMElement, indicating the top of the hierarchy.
     * @param DOMElement $node
     */
    protected function setAllStyles(DOMElement $node)
    {
        $attribs = $node->attributes;
        $numAttribs = $attribs->length;

        // Find the node's attribute defining the element's style class (e.g. 'AppliedParagraphStyle')
        for ($i = 0; $i < $numAttribs; $i++)
        {
            $attribName = $attribs->item($i)->name;
            $attribValue = $attribs->item($i)->value;

            if (in_array($attribName, $this->appliedStyleNames))
            {
                // This is the attribute: store it and break out of the loop
                $this->allStyles[] = $attribValue;
                break;
            }
        }

        // Recurse on the parent node, until there's no DOMElement parent node
        if (isset($node->parentNode))
        {
            $parent = $node->parentNode;

            // If the parent is not a DOMElement, it's an element like a spread which indicates the top of the tree.
            if (get_class($parent) == 'DOMElement')
            {
                $this->setAllStyles($parent);
            }
        }
    }

    /*
     * This getCSS function overrides the base class behavior to get a string suitable for inclusion in an
     * element's HTML style="" attribute. Note: style declaration that need quotes, like 'Minion Pro', use single quotes,
     * so the caller should put this returned string in a style="" that uses single quotes.
     */
    protected function getCSS()
    {
        $css = array();
        foreach ($this->cssKeyValues as $key => $value)
            $css[] = sprintf("%s:%s;", $key, $value);

        return implode(" ", $css);
    }


    /**
     * @return int
     */
    public function getComputedBorders()
    {
        $w = $this->computedBorders;

        if ($w == 0)
        {
            $stylesMgr = IdmlDeclarationManager::getInstance();

            // see if there are computed borders on any of the declared styles associated with this element
            foreach ($this->allStyles as $declaredStyle)
            {
                if (!array_key_exists($declaredStyle, $stylesMgr->declaredStyles)) continue;
                $w = $stylesMgr->declaredStyles[$declaredStyle]->computedBorders;
                if ($w != 0)
                    break;
            }
        }

        return $w;
    }
}
?>