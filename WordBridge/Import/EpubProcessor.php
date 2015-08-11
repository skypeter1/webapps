<?php
App::import('Vendor', 'Chaucer/Epub/EpubManager');
App::import('Vendor', 'Ganon', 'ganon.php');

App::uses('ImportProcessor', 'Lib/Import');
App::uses('EpubOpfReader', 'Lib/Import/Epub');

class EpubProcessor extends ImportProcessor
{    
    const MATHML_NS = "http://www.w3.org/1998/Math/MathML";
    
    public $epubFile;
    public $manifestFiles;
    public $spineFiles;
    private $fontCssRules = null;

    public function isValidSourceFile($sourceFileName)
    {
        return (strtolower(pathinfo($sourceFileName, PATHINFO_EXTENSION)) == 'epub');
    }

    /**
     * import font file
     * @param string $fontFilePath to import
     * @return int Book Font ID
     */
    public function addFontToBook($fontFilePath, $assetId=null)
    {
        if(!$assetId)
        {
            $fontFamily=null;
        }
        else
        {
            $asset = $this->epub->EpubSpine->assetItem($assetId);
            $fontFamily = $this->findFontFamily($asset);
        }
        $fileInfo = pathinfo($fontFilePath);
        $result = $this->epub->importFontFile($fontFilePath, $fontFamily, $fileInfo['filename'],
            null, null, $assetId);
        return $result['asset-id'];
    }

    private function findFontFamily($fontAsset)
    {
        $fontFamily = null;
        try
        {
            $fontRules = $this->getFontCssRules();
            foreach($fontRules as $font)
            {
                $fontSrc = $font['src'];
                if(strpos($fontSrc, $fontAsset->relativePath)!==false)
                {
                    $fontFamily = $font['font-family'];
                    break;
                }
            }
        }
        catch(Exception $e)
        {
            $fontFamily = null;
        }
        return $fontFamily;
    }

    private function getFontCssRules()
    {
        if(is_null($this->fontCssRules))
        {
            $this->fontCssRules = array();
            $cssAssets = $this->epub->EpubSpine->getAssetsByType('text/css');
            foreach($cssAssets as $css)
            {
                try
                {
                    $cssContent = $this->epub->getPageContent($css->relativePath);
                    $cssParser = new CSSParser($cssContent);
                    $objCss = $cssParser->parse();
                    $this->fonts = array();

                    foreach($objCss->getAllRuleSets() as $cssRuleSet)
                    {
                        $font = array();
                        if(is_a($cssRuleSet, 'CSSAtRule'))
                        {
                            $selectorName = $cssRuleSet->getType();
                            if($selectorName == 'font-face')
                            {
                                foreach($cssRuleSet->getRules() as $cssRule)
                                {
                                    $value = (string)$cssRule->getValue();
                                    if(substr($value,0,1)=='"')
                                    {
                                        $value = substr($value,1,-1);
                                    }
                                    $font[$cssRule->getRule()] = $value;
                                }
                                $this->fontCssRules[] = $font;
                            }
                        }
                    }
                    unset($objCss);
                    unset($cssParser);
                }
                catch(Exception $e)
                {
                    CakeLog::warning('[EpubProcessor::getFontCssRules] Error '.$e->getMessage().' when parsing '.$css->relativePath);
                }
            }
        }
        return $this->fontCssRules;
    }

    protected function publishBookDir()
    {
        $srcDir = $this->workingDir.'/source/';
        $sync = new S3Sync();
        $sync->putRemoteDir($srcDir, $this->FileManager->getBookPath('Epub/'), $this->progress);
    }

    /**
     * Primary processor
     * @return bool|void
     */
    public function process()
    {
        // Set parent process
        parent::process();

        // Validate the book source type
        $this->checkBookSourceType(self::BOOK_SOURCE_TYPE_EPUB);

        // Get book source files
        $this->getBookSourceFiles();

        // Start up the routine
        $this->processBook();

        // Set process complete
        $this->processComplete();

        // Return success
        return true;
    }

    public function processBook()
    {
        $this->epubFile = $this->sourceFiles[0];
        CakeLog::debug('[EpubProcessor::processBook] Starting to process Source File: '.$this->epubFile);
        $this->unpackEpub();   
        
        $this->opfReader = new EpubOpfReader($this->workingDir.'/source');
        $this->spineFiles = $this->opfReader->getSpineFiles();
        $this->manifestFiles = $this->opfReader->getManifestFiles();
        $bookInfo = $this->opfReader->getBookInfo();
        if(count($bookInfo)>0)
        {
            if(array_key_exists('isbn', $bookInfo))
            {
                $isbn = $bookInfo['isbn'];
                $book = $this->bookData->getBookItem();
                $book['ChChaucerBook']['isbn'] = $isbn;
                $this->bookData->saveBook($book);
                unset($bookInfo['isbn']);
            }
            if(count($bookInfo)>0)
            {
                $pubModel = ClassRegistry::init('ChChaucerPublication');
                $data = $pubModel->findByPublicationId($this->bookData->bookItem['ChChaucerBook']['publication_id']);
                foreach($bookInfo as $field=>$value)
                {
                    if(!array_key_exists($field, $data['ChChaucerPublication']) || !strlen($data['ChChaucerPublication'][$field]))
                    {
                        $data['ChChaucerPublication'][$field] = $value;
                    }
                }
                $pubModel->save($data);
            }
        }
        $opfDir = $this->opfReader->getContentOpfLocation();
        CakeLog::debug('[EpubProcessor::processBook] opf loc: '.$opfDir);
        if (substr($opfDir, -4) == '.opf') {
            $path_parts = pathinfo($opfDir);
            if (is_array($path_parts) && isset($path_parts['dirname'])) {
                $opfDir = $path_parts['dirname'];
                CakeLog::debug('[EpubProcessor::processBook] opf dir: '.$opfDir);
            } else {
                CakeLog::error('Unable to determine directory for applied styles');
            }
        }

        $sourceFiles = $this->FileManager->getAllFiles($this->workingDir.'/source');
        $this->progress->startProgress(count($sourceFiles)+count($this->spineFiles) + count($this->sourceAssets));
        
        //perform any page changes
        $this->performPageTransitions();

        //for fixed layout books, find the book dimensions
        $this->setBookDimensions();

        //copy files to book dir
        $this->publishBookDir();

        $cssPath = $this->epub->epubRoot().$opfDir.'/'.EpubManager::CSS_DIR.'applied.css';
        if(!$this->FileManager->exists($cssPath))
        {
            $this->FileManager->writeFileContents($cssPath, '/* Chaucer empty book style */');
        }

        $this->epub->initEpub();

        //handle any non-source assets included with the book source files
        $this->importSourceAssets();

        //Run through all book fonts, ensuring meta data has been set
        //This is commented out because our current infastructure doesn't handle .woff fonts
        //$this->importFontData();

        //Handle existing cover image or apply a new one
        $coverAsset = $this->epub->EpubSpine->coverImageAsset();
        if($coverAsset)
        {
            $currentFile = $this->epub->epubRoot().$this->epub->EpubSpine->getOpsPath().$coverAsset->relativePath;
            $bookItem = $this->bookData->getBookItem(true);
            $bookItem['ChChaucerBook']['book_cover'] = $currentFile;
            $this->bookData->saveBook($bookItem);
        }
        else
        {
            $this->setCoverImage();
        }


        // Generate thumbnails
        $this->generateThumbnails(1, count($this->spineFiles));
            
        $this->updatePageCount(count($this->spineFiles));

        $this->epub->updateEpubFiles();
        CakeLog::debug('[EpubProcessor::processBook] Epub import complete');

        return true;
    }

    /**
     * This method pulls down the font files from the finished epub and 'imports' the font again,
     * ensuring it is in our Fonts/ dir, fonts.css and in the manifest how we like it.
     */
    public function importFontData()
    {
        CakeLog::debug('[EpubProcessor::importFontData] Importing font files');
        $assets = $this->epub->EpubSpine->getAssetsByType('application/vnd.ms-opentype');
        foreach($assets as $asset)
        {
            CakeLog::debug('[EpubProcessor::importFontData] Importing font file '.$asset->relativePath);
            $localFile = $this->FileManager->getTmpPath(basename($asset->relativePath));
            $this->FileManager->copy($this->epub->epubRoot().$this->epub->EpubSpine->getOpsPath().$asset->relativePath,$localFile);
            $this->addFontToBook($localFile, $asset->assetId);
        }
        CakeLog::debug('[EpubProcessor::importFontData] Importing font files complete');
    }
    
    /**
     * Loop through each page, performing any content changes necessary
     */
    public function performPageTransitions()
    {
        foreach($this->spineFiles as $spineEntry)
        {
            $manifestItem = $this->getManifestItemByItemId($spineEntry);
            $srcFile = $this->opfReader->getItemBasePath().'/'.$manifestItem['href'];
            $this->transitionPage($srcFile, $manifestItem);
            $this->progress->incrementStep();
        }        
    }

    /**
     * For fixed layout books, try to find the dimensions from a page in the book
     */
    public function setBookDimensions()
    {
        if(!$this->bookData->getIsReflowable())
        {
            CakeLog::debug('[EpubProcessor::setBookDimensions] Setting book dimensions for fixed layout book');
            $height = null;
            $width = null;
            foreach($this->spineFiles as $spineEntry)
            {
                $manifestItem = $this->getManifestItemByItemId($spineEntry);
                $srcFile = $this->opfReader->getItemBasePath().'/'.$manifestItem['href'];
                $dom = new HTML_Parser(file_get_contents($srcFile));
                foreach($dom('meta[name="viewport"]') as $metaTag)
                {
                    $content = $metaTag->getAttribute('content');
                    if(strlen($content)>0)
                    {
                        $parts = explode(',',$content);
                        foreach($parts as $dim)
                        {
                            $dimParts = explode('=', $dim);
                            switch(trim($dimParts[0]))
                            {
                                case 'height':
                                    $height = intval($dimParts[1]);
                                break;
                                case 'width':
                                    $width = intval($dimParts[1]);
                                break;
                            }
                        }
                    }
                    break;
                }
                if($height && $width)
                {
                    $book = $this->bookData->getBookItem(true);
                    $book['ChChaucerBookVersion']['book_height'] = $height;
                    $book['ChChaucerBookVersion']['book_width'] = $width;
                    $this->bookData->saveBookVersion($book);
                    CakeLog::debug('[EpubProcessor::setBookDimensions] Setting book ID '.$this->bookId.' dimensions to height:'.$height.' width:'.$width);
                    break;
                }
            }
        }
        else
        {
            CakeLog::debug('EpubProcessor::setBookDimensions] Skipping book dimensions for reflowable epub');
        }
    }
    
    /**
     * Read in the page, perform any changes to the content, and write back in place
     * @param string $srcFile
     * @param string $pageHref
     */
    public function transitionPage($srcFile, $manifestItem)
    {
        CakeLog::debug('[EpubProcessor::transitionPage] Starting transitions on '.$srcFile);
        if(!file_exists($srcFile))
        {
            throw new Exception('[EpubProcessor::transitionPage] Page file '.$srcFile.' not found');
        }

        $dom = new HTML_Parser(file_get_contents($srcFile));
        CakeLog::debug('[EpubProcessor::transitionPage] DOM Parsing Complete on '.$srcFile);
        $rootRelUrl = $this->getRootRelativeUrl($manifestItem);
        //perform fixes necessary
        CakeLog::debug('[EpubProcessor::transitionPage] Removing XML Declarations on '.$srcFile);
        $this->removeXmlDeclaration($dom);
        CakeLog::debug('[EpubProcessor::transitionPage] Fixing Table Styles on '.$srcFile);
        $this->fixTableTags($dom);
        CakeLog::debug('[EpubProcessor::transitionPage] Fixing MathML tags '.$srcFile);
        $this->fixMathMLTags($dom);
        CakeLog::debug('[EpubProcessor::transitionPage] Adding CSS Links '.$srcFile);
        $this->addBookStyleCssLink($dom, $rootRelUrl.EpubManager::CSS_DIR.'applied.css');
        $this->addBookStyleCssLink($dom, $rootRelUrl.EpubManager::CSS_DIR.'fonts.css');

        CakeLog::debug('[EpubProcessor::transitionPage] Applying UUIDs to '.$srcFile);
        $pageHTML = $this->applyElementUuids((string)$dom);

        //write back to temp dir
        CakeLog::debug('[EpubProcessor::transitionPage] Writing page to '.$srcFile);
        file_put_contents($srcFile, $pageHTML);
        CakeLog::debug('[EpubProcessor::transitionPage] Completed transitions on '.$srcFile);
        $dom->root->clear();
        unset($dom);
    }

    public function getRootRelativeUrl($manifestItem)
    {
        $pageRel = $manifestItem['href'];
        $depth = substr_count($pageRel, '/');
        if($depth>0)
        {
            $rootRelUrl = str_repeat('../', $depth);
        }
        else
        {
            $rootRelUrl = '';
        }
        return $rootRelUrl;
    }
    
    /**
     * removes the <?xml version="1.0" ?> tag from the document
     * @param HTML_Parser $document
     */
    public function removeXmlDeclaration(HTML_Parser $document)
    {
        foreach($document('?xml') as $item)
        {
            $item->detach();
        }        
    }

    /**
 * Adds a <link> tag for the applied.css file to the page.  applied.css is always placed in
 * Styles/applied.css regardless of where the other CSS files are stored in the epub
 * @param HTML_Parser $document
 */
    public function addBookStyleCssLink(HTML_Parser $document, $cssHref)
    {
        $tagFound = false;
        $headTag = null;
        foreach($document('link') as  $linkTag)
        {
            $href = $linkTag->getAttribute('href');
            if(strpos($href, $cssHref) !== false)
            {
                $tagFound = true;
            }
        }
        if(!$tagFound)
        {
            foreach($document('head') as $headTag)
            {
                $link = $headTag->addChild('link');
                $link->addAttribute('href', $cssHref);
                $link->addAttribute('rel', 'stylesheet');
                $link->addAttribute('type', 'text/css');
                $link->self_close = true;
            }
        }
    }


    /**
     * Fix table related attributes to use css in whole document;
     * @param HTML_Parser $document
     * @return void
     */
    public function fixTableTags(HTML_Parser $document)
    {        
        $tags = array('table','tbody','thead','tr','td');
        $table2css = array(
        		'width' => 'max-width: %s',
        		'border' => 'border: %s',
        		'cellpadding' => 'padding: %s',
        		'cellspacing' => 'border-collapse: separate; border-spacing: %s'
        );
        $tagQuery = implode(',', $tags);

        //Check out every table-related tag within the file
        foreach($document($tagQuery) as $item)
        {
            $styleToBeAdded = trim ((string) $item->getAttribute('style'));

            foreach ($table2css as $attributeName=>$pattern)
            {
                if (!$item->hasAttribute($attributeName)) {
                    continue;
                }

                $attributeValue = trim ((string) $item->getAttribute($attributeName));

                // we will remove attribute anyway
                $item->deleteAttribute($attributeName);

                // however, we will add css only if there is sensetive information
                if (strlen($attributeValue)>0)
                {
                    $styleToBeAdded .= ((strlen($styleToBeAdded)==0)? '':'; ') . sprintf($pattern, $attributeValue);
                }
            }

            if (strlen($styleToBeAdded)>3) {
                $item->style = $styleToBeAdded;
            }
        }

    }    
       
    /**
     * Fix mathml tags by seting the namespace on the tag:
     * <math display="block" alttext="" altimg-width="210" altimg-height="18" altimg="../images/e0082-01.png">
     * @param string $html
     * @return string Html 
     */
    public function fixMathMLTags(HTML_Parser $document)
    {
        $prefix = '';
          
        foreach($document('html') as $htmlTag)
        {
            foreach($htmlTag->attributes as $name=>$value)
            {
                if($value==self::MATHML_NS)
                {
                    $htmlTag->deleteAttribute($name);
                    $parts = explode(':', $name);
                    $prefix = $parts[1];
                }
            }
        }
                
        if(strlen($prefix)>0)
        {
            foreach($document($prefix.'|*') as $item)
            {
                $item->addAttribute('xmlns', self::MATHML_NS);
                $item->setNamespace('');           
            }
        }
        else
        {
            //Check out every math tag within the file, set namespace attribute and remove prefix
            foreach($document('math') as $item)
            {
                $item->addAttribute('xmlns', self::MATHML_NS);
            }
        }
        foreach($document('annotation-xml') as $item)
        {
            if($item->getAttribute('encoding')=='application/xhtml+xml')
            {
                foreach($item('*') as $child)
                {
                    $child->addAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
                }
            }
        }
    }    
    
    
    /**
     * Find a manifest entry based on the item ID
     * @param string $id
     * @return array|false
     */
    public function getManifestItemByItemId($id)
    {
        foreach($this->manifestFiles as $file)
        {
            if($file['id']==$id)
            {
                return $file;
            }
        }
        return false;
    }

    /**
     * Unpack the epub into a temp location so it can be processed
     * @throws Exception
     * @return boolean
     */
    public function unpackEpub()
    {
        $zip = new ZipArchive();
        $res = $zip->open($this->epubFile);
        if ($res !== TRUE)
        {
            throw new Exception("[EpubProcessor::unpackEpub] Unable to open epub file, make sure it is in the correct format: code($res) {$this->epubFile}");
        }

        if($zip->numFiles == 0)
        {
            throw new Exception("[EpubProcessor::unpackEpub] Uploaded file $this->epubFile is empty");
        }

        $zip->extractTo($this->workingDir.'/source');
        $zip->close();
        return true;
    }            
}