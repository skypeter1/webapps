<?php

/**
 * @package /app/Import/Idml/Styles/IdmlPreferences.php
 * 
 * @class   IdmlPreferences
 * 
 * @description There should always be exactly one Preferences.xml file per Package
 *  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */


class IdmlPreferences
{
    public $pageWidth;
    public $pageHeight;

    public function __construct()
    {
        $this->pageWidth = 768;
        $this->pageHeight = 1024;
    }

    public function load($filename)
    {
        $doc = new DomDocument();
        $b = $doc->load($filename);
        if ($b === false)
            return false;

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//idPkg:Preferences/DocumentPreference');

        if ($nodes->length > 0 )
        {
            $node = $nodes->item(0);

            if ($node->hasAttribute('PageWidth'))
                $this->pageWidth = $node->getAttribute('PageWidth');

            if ($node->hasAttribute('PageHeight'))
                $this->pageHeight = $node->getAttribute('PageHeight');
        }
    }
}
