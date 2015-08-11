<?php

/**
 * @package /app/Import/Idml/IdmlPostProcessor.php
 *
 * @class   IdmlPostProcessor
 *
 * @description Use this class to massage the HTML created by IdmlProduceXXX.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
App::import('Vendor', 'Ganon', 'ganon.php');

class IdmlPostProcessor
{
    private $css_selectors;

    private $nextNumber;

    public $numElementsDeleted;


    public function __construct()
    {
        $this->css_selectors = array();
        $this->nextNumber = 1;
        $this->numElementsDeleted = 0;
    }

    /**
     * @param $rawHTML is the HTML produced by IdmlProduceXXX
     * @return an associative array with two values 'css' and 'html'
     */
    public function segregateCssFromHtml($rawHTML)
    {
        $dual['css'] = '';
        $dual['html'] = '';

        $dom = new HTML_Parser($rawHTML);
        $this->recursivelySegregate($dom->root);

        $dual['html'] = (string)$dom;
        $dual['css'] = $this->getCleanCSS();

        // reclaim memory leak in Ganon library
        $dom->root->clear();
        unset($dom);

        return $dual;
    }

    /*
     * Walk the DOM, looking for any element with a 'style' attribute, moving the style declaration
     * to an associative array, keyed on the element's 'id'
     */
    public function recursivelySegregate($node)
    {
        for ($i = 0; $i < $node->childCount(); $i++)
        {
            $child = $node->getChild($i);

            // any element with a 'style' tag
            if ($child->hasAttribute('style'))
            {
                $styles = $child->getAttribute('style');
                $child->deleteAttribute('style');

                // if the element has an 'id', use it, otherwise create one
                if ($child->hasAttribute('id'))
                    $id = $child->getAttribute('id');
                else
                {
                    $id = sprintf('i%03d', $this->nextNumber++);
                    $child->setAttribute('id', $id);
                }

                // copy the style declarations to the associative array for getCleanCSS()
                $styles = htmlspecialchars_decode($styles, ENT_QUOTES);
                $this->css_selectors[$id] = $styles;
            }

            $this->recursivelySegregate($child);
        }
    }

    /*
     * Scan the associative array built by recursivelySegregate() creating a single string
     * suitable for use as an external stylesheet.
     */
    public function getCleanCSS()
    {
        $cleanCSS = array();

        foreach ($this->css_selectors as $id => $uglyCSS)
        {
            $cleanCSS[] = sprintf('#%s {', $id);
            $parts = explode(';', $uglyCSS);

            // convert the single-line styles into prettified multi-line styles
            foreach ($parts as $part)
            {
                $part = trim($part);
                if ($part != '')
                {
                    list($attribute, $value) = explode(':', $part);
                    $cleanCSS[] = sprintf("\t%-26.26s : %s;", $attribute, $value);
                }
            }

            $cleanCSS[] = '}';
        }

        return implode("\n", $cleanCSS);
    }


    /**
     * @param $rawHTML is the HTML produced by IdmlProduceXXX
     *
     * If Adobe has done its job, this may not even be necessary, that is, there may be no empty elements to remove
     * for a well-formed IDML document.  As such, if this algorithm is too expensive, the alternative is to simply not
     * call it, since the resultant output may not be significantly smaller than the original.
     *
     * @return $cleanHTML with empty elements removed
     */
    public function removeEmptyElements($rawHTML)
    {
        $dom = new HTML_Parser($rawHTML);
        $this->recursivelyRemoveEmptyElements($dom->root);
        $cleanHTML = (string)$dom;

        // reclaim memory leak in Ganon library
        $dom->root->clear();
        unset($dom);

        return $cleanHTML;
    }


    /*
     * Walk the DOM, looking for any element without children and without a text node
     * Remove those empty nodes
     */
    public function recursivelyRemoveEmptyElements($node)
    {
        $ignoreList = array('head', 'body', 'col', 'td', 'img');
        for ($i = 0; $i < $node->childCount(); $i++)
        {
            $element = $node->getChild($i);

            if (get_class($element) == 'HTML_Node')     // do not deal with comment nodes, CDATA nodes, text nodes, etc, only with HTML Element nodes.
            {
                if (in_array($element->getTag(), $ignoreList))
                {
                    // skip over any tag in the ignore list, because empty elements still have meaning for them
                }
                else
                {
                    // <span>s are a special case, because they may be empty but if they have width or height, we need to treat them as important
                    $bKeepSpan = false;
                    if ($element->getTag() == 'span')
                    {
                        if ($element->hasAttribute('style'))
                        {
                            $styles = $element->getAttribute('style');
                            $parts = explode(';', $styles);
                            foreach ($parts as $part)
                            {
                                list($attr, $value) = explode(':', $part, 2);

                                // keep any span with a width greater than 0
                                if (strtolower(trim($attr)) == 'width' && intval($value) > 0)
                                    $bKeepSpan = true;

                                // keep any span with a height greater than 0
                                if (strtolower(trim($attr)) == 'height' && intval($value) > 0)
                                    $bKeepSpan = true;
                            }
                        }
                    }

                    // now that we've determined if this is a candidate element, recurse to see if _it_ contains anything
                    if ($bKeepSpan == false)
                    {
                        // recurse all children, removing _them_ if empty
                        $this->recursivelyRemoveEmptyElements($element);

                        if ($element->childCount() == 0)
                        {
                            // skip over any tag in the ignore list, because empty elements still have meaning for them
                            if (!in_array($element->getTag(), $ignoreList))
                            {
                                $element->delete();             // <-- delete the empty element in question
                                $this->numElementsDeleted++;
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
