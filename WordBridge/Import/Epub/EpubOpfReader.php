<?php
/**
 * Copyright © 2012 Metrodigi, Inc. All Rights Reserved. Unpublished
 * -- rights reserved under the copyright laws of the United States.
 * USE OF A COPYRIGHT NOTICE IS PRECAUTIONARY ONLY AND DOES NOT IMPLY
 * PUBLICATION OR DISCLOSURE.  THIS SOFTWARE CONTAINS CONFIDENTIAL INFORMATION
 * AND TRADE SECRETS OF METRODIGI, INC. USE, DISCLOSURE, OR REPRODUCTION IS
 * PROHIBITED WITHOUT THE PRIOR EXPRESS WRITTEN PERMISSION OF METRODIGI, INC.
 */

/**
 * Parse EPUB files and retrieve contents of opf file
 *
 */
class EpubOpfReader
{
    /**
     * Location of base epub.
     *
     * @var path
     */
    protected $path;

    function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Determine the packaging version used.
     * @return int
     */
    public function getVersion()
    {
        $contentOpf = $this->path . '/' . $this->getContentOpfLocation();
        if(!file_exists($contentOpf) || is_dir($contentOpf))
        {
            return FALSE;
        }

        $xml = simplexml_load_file($contentOpf);
        $attrs = $xml->attributes();
        if(isset($attrs['version']))
        {
            return (int) $attrs['version'];
        }
        return FALSE;
    }

    /**
     * Return the location of the opf file by examining the container.xml file
     *
     * @return bool|string
     */
    public function getContentOpfLocation()
    {
        $containerFile = $this->path . "/META-INF/container.xml";
        $dom = simplexml_load_file($containerFile);
        if($dom === FALSE)
        {
            error_log('Unable to open container file '.$containerFile);
            return FALSE;
        }

        //Iterate over each child and file the content.opf entry
        foreach($dom->rootfiles->children() as $rootfile)
        {
            if( isset($rootfile['media-type']) && $rootfile['media-type'] == 'application/oebps-package+xml' )
            {
                return (string) $rootfile['full-path'];
            }
        }

        return FALSE;
    }

    public function getItemBasePath()
    {
        $contentOpf = $this->path . '/'. $this->getContentOpfLocation();
        $parts = pathinfo($contentOpf);
        return $parts['dirname'];
    }
    /**
     * Return a listing of all manifest files
     *
     * @return array|bool
     */
    public function getManifestFiles()
    {
        $contentOpf = $this->path . '/'. $this->getContentOpfLocation();
        if(!file_exists($contentOpf) || is_dir($contentOpf))
        {
            throw new Exception("Unable to get manifest file $contentOpf");
        }
        $results = array();
        $index = 0;
        $xml = simplexml_load_file($contentOpf);
        foreach($xml->manifest->children() as $items)
        {
            $results[$index]['id'] = (string) $items['id'];            
            $results[$index]['href'] = (string) $items['href'];
            $results[$index]['media-type'] = (string) $items['media-type'];
            $index++;
        }

        return $results;
    }

    public function getBookInfo()
    {
        $ret = array();
        $contentOpf = $this->path.'/'.$this->getContentOpfLocation();
        if(!file_exists($contentOpf))
        {
            return FALSE;
        }
        $dom = new DOMDocument();
        $dom->loadXML(file_get_contents($contentOpf));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('', 'http://www.idpf.org/2007/opf');
        $xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $titleNode = $xpath->query('//dc:title')->item(0);
        if($titleNode)
        {
            $ret['publication_name'] = $titleNode->nodeValue;
        }
        $pubNode = $xpath->query('//dc:publisher')->item(0);
        if($pubNode)
        {
            $ret['publication_publisher'] = $pubNode->nodeValue;
        }

        $langNode = $xpath->query('//dc:language')->item(0);
        if($langNode)
        {
            $ret['publication_language'] = $langNode->nodeValue;
        }

        $isbnNode = $xpath->query('//dc:identifier[@id="ISBN"]')->item(0);
        if($isbnNode)
        {
            $ret['isbn'] = $isbnNode->nodeValue;
        }
        return $ret;
    }
    
    /**
     * Return a listing of all spine files
     *
     * @return array|bool
     */
    public function getSpineFiles()
    {
        $contentOpf = $this->path.'/'.$this->getContentOpfLocation();
        if(!file_exists($contentOpf))
        {
            return FALSE;
        }
        
        $results = array();
        $index = 1;
        
        $xml = simplexml_load_file($contentOpf);
        foreach($xml->spine->children() as $items)
        {
            $results[$index] = (string) $items['idref'];
            $index++;
        }

        return $results;
    }    
}