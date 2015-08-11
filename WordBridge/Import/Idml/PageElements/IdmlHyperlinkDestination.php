<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlHyperlinkDestination.php
 *
 * @class   IdmlHyperlinkDestination
 *
 * @description Parser for InDesign <HyperlinkDestination> which is a non-HTTP URL target, within the current IDML Package.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement', 'Import/Idml/PageElements');


class IdmlHyperlinkDestination extends IdmlElement
{
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

        $hyperlinkMgr = IdmlHyperlinkManager::getInstance();

        // Set the id attribute value
        $destinationKey = $this->contextualStyle->idmlKeyValues['DestinationUniqueKey'];
        $this->id = 'Hyperlink_' . $destinationKey;

        // Save this destination in the hyperlink manager.
        // After parsing the page completes, its id must be stored for all the hyperlink destinations on the page.
        $hyperlinkMgr->saveDestinationForPageData($this->id);
    }

    /**
     * Accept visitor.
     *
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {

        $visitor->visitHyperlinkDestination($this, $depth);

    }

} 