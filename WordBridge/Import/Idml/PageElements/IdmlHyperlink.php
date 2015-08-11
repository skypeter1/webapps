<?php

/**
 * @package /app/Import/Idml/PageElements/IdmlHyperlink.php
 * 
 * @class   IdmlHyperlink
 * 
 * @description Parser for InDesign <Hyperlink> elements which represent a hyperlink source, a wrapper for
 *              text pointing to another URL.
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',            'Import/Idml/PageElements');
App::uses('IdmlHyperlinkManager',   'Import/Idml/PageElements');


class IdmlHyperlink extends IdmlElement
{
    public $href;
    public $linkType;
    public $name;
            
    /**
     * Constructor.
     * @param IdmlPage $page Could be null
     * @param IdmlStory $story Could be null if rectangle is not part of story.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Parse from DOM node.
      * 
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);

        $link = IdmlAssembler::getInstance()->getCurrentPackage()->getHyperlinkBySource($node->getAttribute("Self"));
      
        $this->idAttribute = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;
        $this->name = $node->hasAttribute(IdmlAttributes::Name) ? $node->getAttribute(IdmlAttributes::Name) : null;
        if(count($link)>0)
        {                        
            if(is_array($link['Destination']))
            {
                $this->linkType = $link['Destination']['DestinationType']; 
                if(array_key_exists('DestinationURL', $link['Destination']))
                {
                    $this->href = $link['Destination']['DestinationURL'];
                }
            }
            else
            {
                $this->href = $link['Destination'];
            }
           
            if(!strlen($this->idmlTag))
            {
                $this->idmlTag = 'a';
            }
        } 
        $this->parseChildren($node);
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        // Group is also visitable just in case it can have a style.
        $visitor->visitHyperlink($this, $depth);

        // The boolean $hyperlinkClosed is set to true if any child is an IdmlBrContent object.
        // Such an object will cause the producer to terminate this hyperlink and start a new paragraph.
        $hyperlinkClosed = false;

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
            if (is_a($child, 'IdmlBrContent'))
            {
                $hyperlinkClosed = true;
            }
        }

        if (!$hyperlinkClosed)
        {
            $visitor->visitHyperlinkEnd($this, $depth);
        }
    }
}