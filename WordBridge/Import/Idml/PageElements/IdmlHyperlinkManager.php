<?php
/**
 * @package /app/Lib/Import/Idml/PageElements/IdmlHyperlinkManager.php
 *
 * @class   IdmlHyperlinkManager
 *
 * @description The manager for hyperlinks
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlHyperlinkManager
{
    /**
     * $sourceDestinations is an array of source->destination information.
     * For each element, the key is the source identifier, and the value is an array containing
     *   the destination--which either a URL or an anchor--and a boolean indicating when it's an anchor.
     * The array is populated during the parsing of the design map, where InDesign stores the data,
     *   and retrieved during the production of hyperlinks.
     * @var array $sourceDestinations
     */
    protected $sourceDestinations;

    /**
     * $destinationPages is an array which stores the page file names for all the hyperlink destinations.
     * Data is populated by the IdmlHyperlinkDestination class, and retrieved during production of hyperlinks.
     * @var array $destinationPages
     */
    protected $destinationPages;

    /**
     * @var array $destinationsNeedingPageData - stores all destinations that need page data
     * The page data is added to $destinationPages for the array elements once the page is fully parsed.
     */
    protected $destinationsNeedingPageData;

    /**
     * The $pageMap is a mapping of all page UIDs to their corresponding text frame UIDs.
     * This is used in translating the href attribute of hyperlinks to another page within a reflowable epub.
     * While the page ID for a hyperlink is available during parsing (via the design map),
     * the text frame ID, which is used for reflowable, does not become available until the destination page is parsed,
     * which may not occur until after the hyperlink is parsed.
     * So the page IDs must be saved for *all* text frames, and the UIDs translated for reflowable during production.
     * @var array $pageMap
     */
    protected $pageMap;

    private static $instance = null;

    public static function getInstance()
    {
        if (IdmlHyperlinkManager::$instance == null)
            IdmlHyperlinkManager::$instance = new IdmlHyperlinkManager();

        return IdmlHyperlinkManager::$instance;
    }

    private function __construct()
    {
        $this->sourceDestinations = array();
        $this->destinationPages = array();
        $this->destinationsNeedingPageData = array();
        $this->pageMap = array();
    }

    /**
     * Set the destination information for a single hyperlink tag.
     * @param string $source - The id of the hyperlink tag.
     * @param string $destination - The xhtml or html page to which the hyperlink will refer.
     * @param boolean $anchor - the name of the anchor (may be null)
     * @param boolean $external - indicates whether the link is to a page outside the epub
     */
    public function setSourceDestination($source, $destination, $anchor, $external)
    {
        $this->sourceDestinations[$source] = array();
        $this->sourceDestinations[$source]['destination'] = $destination;
        $this->sourceDestinations[$source]['anchor'] = $anchor;
        $this->sourceDestinations[$source]['external'] = $external;
    }

    /**
     * Return the destination information for a single hyperlink.
     * @param $source
     * @return array
     */
    public function getSourceDestination($source)
    {
        if (isset($this->sourceDestinations[$source]))
        {
            return $this->sourceDestinations[$source];
        }
        else
        {
            return array();
        }
    }

    /**
     * Set the page id and text frame id for all the destinations in the array
     * These must be set after the page is completely parsed, which doesn't happen until after the destination is parsed.
     * @param $destinations
     * @param $pageUID
     * @param $textFrameUID
     */
    protected function setDestinationPages($destinations, $pageUID, $textFrameUID)
    {
        foreach ($destinations as $destination)
        {
            $this->destinationPages[$destination] = array();
            $this->destinationPages[$destination]['pageUID'] = $pageUID;
            $this->destinationPages[$destination]['textFrameUID'] = $textFrameUID;
        }
    }

    /**
     * Updates the destinations on the current page with the fully parsed page and textframe data.
     * Clears array once the updates are complete.
     * @param string $pageUID
     * @param string$textFrameUID
     */
    public function updateDestinationPages($textFrameUID, $pageUID)
    {
        $this->setDestinationPages($this->destinationsNeedingPageData, $pageUID, $textFrameUID);
        $this->destinationsNeedingPageData = array();
    }

    public function getDestinationPages($destination)
    {
        if (isset($this->destinationPages[$destination]))
        {
            return $this->destinationPages[$destination];
        }
        else
        {
            return array();
        }
    }

    /**
     * Save a single destination ID in an array.
     * These will be used to link them to the current page (or text frame) after the page is completely parsed.
     * @param $destId
     */
    public function saveDestinationForPageData($destId)
    {
        $this->destinationsNeedingPageData[] = $destId;
    }

    /**
     * Adds a text frame/page uid pair to the page map
     * @param string $textFrameUID
     * @param string $pageUID
     */
    public function saveTextFrameUID($textFrameUID, $pageUID)
    {
        $this->pageMap[$pageUID] = $textFrameUID;
    }

    /**
     * Retrieves a text frame uid from the page map for a specified page UID
     * @param string $pageUID
     * @return string text frame UID
     */
    public function getTextFrameUID($pageUID)
    {
        return $this->pageMap[$pageUID];
    }
}