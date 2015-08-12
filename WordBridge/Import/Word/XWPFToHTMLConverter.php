<?php

ini_set('xdebug.var_display_max_depth', 5);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 17834);
//App::import('Vendor', 'Chaucer/Common/ProgressUpdater');

include_once "HTMLDocument.php";
include_once "HTMLElement.php";
include_once "HWPFWrapper.php";
include_once "StyleSheet.php";
include_once "XhtmlEntityConverter.php";


/**
* HWPFToHTMLConverter is a class that takes a doc or docx file as input and converts it to a Simple HTMLDocument, keeping stylesheets, images e.g.
* The main idea is to reuse Apache POI parser that is integrated with JavaBridge web app on a tomcat server.
* @author Avetis Zakharyan
*/
class XWPFToHTMLConverter {

    /**
     * Instance of Simple HTML document, that converter will be filling with data
     */
    private $htmlDocument;

    /**
     * String, path to the file that needs to be parsed
     */
    private $parsingFile;

    /**
     * HWPF document from parsed word file
     */
    private $document;
    
    /**
     * Paragraph numbering state
     */
    private $numberingState;

    /**
     * List item counter
     */
    private $listItemIterator;

    /**
     * List level state
     */
    private $listLevelState;

    /**
     * List num id
     */
    private $listNumId;

    /**
     * inout stream
     */
    private $inputStream;
    
    /**
     * Document pages and styles
     */
    private $pages;
    private $styles;
    public $mainStyleSheet;
    
    /**
     * Document images
     */
    private $images;
    private $images_data;
    private $images_currentIndex = 0;

    private $_tmp_path;
    private $_progress;

    /**
     * Table of contents
     */
    private $toc;
    private $tocLevel;

    /**
     * Set default page break
     */
    const PAGE_BREAK = -1;


    /**
     * Constructor that needs to get a Java Bridge classes
     * @param bool $tmp_path
     * @param bool $progress
     * @internal param Path $string to working directory
     * @internal param Progress $object Manager
     */
    function __construct($tmp_path = FALSE, $progress = FALSE)
    {
        // $errorLevel = error_reporting(E_ALL & ~E_NOTICE);
        // Check temp path
        if (!$tmp_path) return FALSE;

        // Store temp path and progress tracker per instance
        $this->_tmp_path = $tmp_path;
        $this->_progress = $progress;

        // Start progress
        //$this->_progress->startProgress(2);

        // Set bridge host
        $bridge_host = "http://localhost:8080/";
        if ($bridge_host == null || $bridge_host == '') {
            $bridge_host = 'http://word.chaucercloud.com:8080/';
            // Use this host for local VM
            //$bridge_host = 'http://processor.vm.local:8080/';
        }

        // Download JavaPHP bridge to local temp for cross domain access
        $url = $bridge_host.'WordBridge/java/Java.inc';
        $local = "/var/lib/tomcat7/webapps/WordBridge/java/Java.inc";
       // $remote_contents = file_get_contents($url);
        //file_put_contents($local, $remote_contents);

        // Setup Java servlet (used by Java.inc must be present)
        if (!defined('JAVA_SERVLET')) {
            define('JAVA_SERVLET', '/WordBridge/servlet.phpjavabridge');
        }

        // Format and set bridge host (used by Java.inc must be present)
        if (substr($bridge_host, -1) == '/') {
            $bridge_host = substr($bridge_host, 0, -1);
        }
        if (substr($bridge_host, 0, 7) == 'http://') {
            $bridge_host = substr($bridge_host, 7);
        }
        if (!defined('JAVA_HOSTS')) {
            define('JAVA_HOSTS', $bridge_host);
        }
        
        // Include Java PHP local bridge library
        include($local);

        // Adjust progress step
        //$this->_progress->incrementStep();
    }

    /**
     * Does no parsing, but sets the parsing file, prepares Java file stream and document file
     * @param   string  Path to Word document
     */
    public function setDocFileToParse($docx_file)
    {
        // Preset dictionaries
        $this->numberingState = array();
        $this->pages = array();
        $this->listItemIterator = null;
        $this->listLevelState = 0;
        $this->listNumId = 1;
        $this->tocLevel = 0;
        $this->toc = null;

        // Parse path to file and create input stream
        $this->parsingFile = $docx_file;
        $this->inputStream = $this->createJavaInputStream($docx_file);
        //var_dump($this->inputStream);
        $this->document = new Java("org.apache.poi.xwpf.usermodel.XWPFDocument", $this->inputStream);

        // Adjust progress step
       // $this->_progress->incrementStep();
    }

    /**
     * Create input stream from loaded stream
     * @param   string  Path to Word document
     * @return  object  Java object of input stream
     */
    private function createJavaInputStream($path)
    {
        // Get contents of Word document
        $handle = fopen($path, "r");
        $contents = fread($handle, filesize($path));
        fclose($handle);
        
        // Create Java output stream
        $out = new Java("java.io.ByteArrayOutputStream");

        // Prepare content chunks
        $arr = array();
        $chunks = str_split($contents, 10000);
        $arrsize = count($chunks);


        // Update progress with new steps
        //$this->_progress->adjustMaxSteps($arrsize, TRUE);

        // Iterate through content chunks and add it to output stream
        for ($i = 0; $i < $arrsize; $i++) {
            $strlen = strlen($chunks[$i]);
            for ($j = 0; $j < $strlen; $j++) {
                $val = ord(substr($chunks[$i], $j, 1));
                if ($val >= 128) {
                    $val = ($val) - 256;
                }
                $arr[] = $val;
            }
            $out->write($arr);
            unset($arr);

            // Adjust progress step
            //$this->_progress->incrementStep();
        }
        
        // Write to input stream
        $value = new Java("java.io.ByteArrayInputStream", $out->toByteArray());
        /*TODO Write a new way to get the inputStream */

        //var_dump($value);

        //die();
        
        // Return stream
        return $value;
    }

    /**
     * Takes the previously opened file and converts it to simple HTMLDocument object.
     */
    public function convertToHTML()
    {
        // Create a new HTML page
        $this->createPage();
        
        // Start parsing
        $this->parseSection();
    }

    /**
     * Create new page
     */
    private function createPage()
    {
        // Set new HTML document and its style sheet
        $document = new HTMLDocument();
        $document->styleSheet = new StyleSheet();
        
        // Add document to local pages
        $this->pages[] = $document;

        // Assign current HTML document
        $this->htmlDocument = $document;
    }

    /**
     * Parses a section element of HWPF document
     * @return  HTMLElement
     */
    private function parseSection()
    {
        // Create new style for HTML element
        $container = new HTMLElement(HTMLElement::DIV);
        $this->mainStyleSheet = new StyleSheet();

        // Set class name
        $styleClass = new StyleClass();
        $className = $this->mainStyleSheet->getClassName($styleClass);
        $container->setClass($className);

        // Get document body elements
        $elements = java_values($this->document->getBodyElements());

        // Every Word document section consists of paragraphs, so we iterate through them, and parse them one by one
        foreach ($elements as $key => $element) {
            
            // Check if element is a table
            if (java_instanceof($element, java('org.apache.poi.xwpf.usermodel.XWPFTable'))) {
                
                // Get table out of element
                $table = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFTable');
                
                // Parse table
                $tableElement = $this->parseTable($table, $key);
                
                // Add element to container
                $container->addInnerElement($tableElement);
            }
            
            // Check if element is a paragraph
            if (java_instanceof($element, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {

                // Get paragraph out of element
                $paragraph = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFParagraph');

                //Check if the element is a list
                $numberingInfo = $this->paragraphExtractNumbering($paragraph);

                if ($numberingInfo) {

                    $this->processList($numberingInfo, $container, $paragraph);

                } else {

                    // Parse paragraph
                    $paragraphHTMLElement = $this->parseParagraph($paragraph, $key);

                    // Check if this is a page break
                    if (is_int($paragraphHTMLElement) && $paragraphHTMLElement == self::PAGE_BREAK) {

                        // Add container to inner body
                        $this->htmlDocument->setBody($container);

                        // Create new page
                        $this->createPage();

                        // Create new HTML element and set its class name
                        $container = new HTMLElement(HTMLElement::DIV);
                        $styleClass = new StyleClass();
                        $className = $this->mainStyleSheet->getClassName($styleClass);
                        $container->setClass($className);

                    }elseif($paragraphHTMLElement->getTagName() == "tr"){

                        if($this->tocLevel <= 1) {
                            $table = new HTMLElement(HTMLElement::TABLE);
                            $table->addInnerElement($paragraphHTMLElement);
                            $container->addInnerElement($table);
                        }else {
                            $lastTable = $container->getLastElement();
                            if(is_object($lastTable)){
                                $lastTable->addInnerElement($paragraphHTMLElement);
                            }
                        }

                    } else {
                        // Add element to container
                        $container->addInnerElement($paragraphHTMLElement);
                    }

                }


            }
            // Adjust progress step
            //$this->_progress->incrementStep();
        }
        
        // Add container to inner body
        $this->htmlDocument->setBody($container);
    }


    /**
     * Parse Word table
     * @staticvar   int Unique ID
     * @param       object  Table
     * @param       string  Element key
     * @return      HTMLElement
     */
    private function parseTable($table, $parIterator) 
    {
        // Set initial unique id for element
        static $uniqueId = 10000;
        
        // Create new HTML table element
        $container = new HTMLElement(HTMLElement::TABLE);
        
        // Create new table style class
        $tableStyleClass = new StyleClass();
        
        // Check if table has style ID assigned
        if (java_values($table->getStyleID()) != null) {
            
            // Get style name
            $style = $this->styles->getStyle($table->getStyleID());
            //$style_name = java_values($style->getName());
            
            // Apply style to table style class
            $styleXML = java_values($style->getCTStyle()->toString());
            $tableStyleClass = $this->processStyle($tableStyleClass, $styleXML);
            
            // Get XML out of XML style
            $tmpStyleXML = str_replace('w:', 'w', $styleXML);
            $xml = new SimpleXMLElement($tmpStyleXML);
            
            // Get cell margins
//            $cellTopMargin = $xml->xpath('wtblPr/wtblCellMar/wtop');
//            $cellTopMargin = round(intval($cellTopMargin[0]['ww']) / 15.1);
//            $cellLeftMargin = $xml->xpath('wtblPr/wtblCellMar/wleft');
//            $cellLeftMargin = round(intval($cellLeftMargin[0]['ww']) / 15.1);
//            $cellRightMargin = $xml->xpath('wtblPr/wtblCellMar/wright');
//            $cellRightMargin = round(intval($cellRightMargin[0]['ww']) / 15.1);
//            $cellBottomMargin = $xml->xpath('wtblPr/wtblCellMar/wbottom');
//            $cellBottomMargin = round(intval($cellBottomMargin[0]['ww']) / 15.1);
        }

        //$styleXML = java_values($style->getCTStyle()->toString());
        // Process table style
        //$tableStlData = $this->processTableStyle($styleXML);
        
        // Get horizontal border attributes (color, size, space)
        //$inHBc = java_values($table->getInsideHBorderColor());
        $inHBsz = java_values($table->getInsideHBorderSize());
        $inHBsp = java_values($table->getInsideHBorderSpace()); 

        //Set default borders
        if(!java_is_null($table->getInsideHBorderColor())){
            $inHBc = java_values($table->getInsideHBorderColor());
        }else{
            $inHBc = '00000A';
        }

        // Get horizontal border attributes (type)
        if (!java_is_null($table->getInsideHBorderType())) {
            $inHBtp = java_values($table->getInsideHBorderType()->name());
        } else {
            $inHBtp = 'SINGLE';
        }
        
        // Get vertical border attributes (color, size, space)
	    $inHVBsz = java_values($table->getInsideVBorderSize());
	    $inVBsp = java_values($table->getInsideVBorderSpace());

        if(!java_is_null($table->getInsideHBorderColor())){
            $inVBc = java_values($table->getInsideHBorderColor());
        }else{
            $inVBc = '00000A';
        }
        
        // Get vertical border attributes (type)
	    if (!java_is_null($table->getInsideVBorderType())) {
            $inVBtp = java_values($table->getInsideVBorderType()->name());
        } else {
            $inVBtp = 'SINGLE';
        }
        
        // Set border attributes
        $tableStyleClass->setAttribute("border-collapse", "inherit");
        $tableStyleClass->setAttribute("border-left", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
        $tableStyleClass->setAttribute("border-right", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
        $tableStyleClass->setAttribute("border-top", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
        $tableStyleClass->setAttribute("border-bottom", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
        
        // Preset default width of the table to 100%
        $tableStyleClass->setAttribute("width", "100%");
        
        // Get rows and iterate through them
        $rows = java_values($table->getRows());

        foreach ($rows as $rowKey => $row) {
            
            // Create new row tag
            $rowTag = new HTMLElement(HTMLElement::TR);
            $rowXmlStr = java_values($row->getCtRow()->toString());
            $tmpRowXmlStr = str_replace('w:', 'w', $rowXmlStr);
            $rowXml = new SimpleXMLElement($tmpRowXmlStr);
            
            // Get row height
            $height = $rowXml->xpath('wtrPr/wtrHeight');
            $height = (isset($height[0]['wval'])) ? round(intval($height[0]['wval']) / 15.1).'px' : 'auto';
            
            // Set style attributes on the row
            $trStyle = new StyleClass();
            $trStyle->setAttribute("border-left", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
            $trStyle->setAttribute("border-right", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
            $trStyle->setAttribute("border-top", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
            $trStyle->setAttribute("border-bottom", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
            $trStyle->setAttribute("height", $height);
            
            // Set class on the row
            $cnm = $this->mainStyleSheet->getClassName($trStyle);
            $rowTag->setClass($cnm);
            
            // Get and iterate through the row cells
            $cells = java_values($row->getTableCells());
            foreach ($cells as $cellKey => $cell) {
                
                // Create new cell tag
                $cellTag = new HTMLElement(HTMLElement::TD);
                $xmlstr = java_values($cell->getCTTc()->toString());
                $xmlstr = str_replace('w:', 'w', $xmlstr);
                $xml = new SimpleXMLElement($xmlstr);
                
                // Create cell attributes
                $cellDescribers = array();
                if ($rowKey == 0) $cellDescribers[] = 'firstRow';
                if ($rowKey == count($rows)-1) $cellDescribers[] = 'lastRow';
                if ($cellKey == 0) $cellDescribers[] = 'firstCol';
                if ($cellKey == count($cells)-1) $cellDescribers[] = 'lastCol';
                if ($cellKey % 2 == 0) $cellDescribers[] = 'band1Vert';
                if ($cellKey % 2 == 1) $cellDescribers[] = 'band2Vert';
                if ($rowKey % 2 == 0) $cellDescribers[] = 'band1Horz';
                if ($rowKey % 2 == 1) $cellDescribers[] = 'band2Horz';	
                if ($rowKey == 0 && $cellKey == count($cells)-1) $cellDescribers[] = 'neCell';			
                if ($rowKey == 0 && $cellKey == 0) $cellDescribers[] = 'nwCell';			
                if ($rowKey == count($rows)-1 && $cellKey == count($cells)-1) $cellDescribers[] = 'seCell';			
                if ($rowKey == 0 && $cellKey == 0) $cellDescribers[] = 'swCell ';			
                
                // Set cell attributes
                $tdStyle = new StyleClass();
//                foreach ($cellDescribers as $describer) {
//                    if (isset($tableStlData['fill'][$describer]) && strlen($tableStlData['fill'][$describer]) > 2) {
//                        $tdStyle->setAttribute("background", $tableStlData['fill'][$describer]);
//                    }
//                }
                
                // Set colspan
                $gridspan = $xml->xpath('*/wgridSpan');
                if ($gridspan) {
                    $gridspan = ((string)$gridspan[0]['wval']);
                    $cellTag->setAttribute('colspan', $gridspan);
                }
                
                // Set cell width
                $cellwidth = $xml->xpath('*/wtcW');
                if ($cellwidth) {
                    $cellwidth = ((string)$cellwidth[0]['ww']);
                    $cellwidth = round($cellwidth/15.1);
                    $tdStyle->setAttribute('width', $cellwidth.'px');
                }
                
                // Set cell background color
                $color = java_values($cell->getColor());
                if ($color) {
                    $tdStyle->setAttribute("background-color", "#"."$color");
                }
                
                // Set table border color
//                if ($tableStlData['border']['left']['color'] > '') {
//                    $tdStyle->setAttribute("border-left", "1px ".$tableStlData['border']['left']['stl']." ".$tableStlData['border']['left']['color']);
//                }
//                if ($tableStlData['border']['right']['color'] > '') {
//                    $tdStyle->setAttribute("border-right", "1px ".$tableStlData['border']['right']['stl']." ".$tableStlData['border']['right']['color']);
//                }
//                if ($tableStlData['border']['top']['color'] > '') {
//                    $tdStyle->setAttribute("border-top", "1px ".$tableStlData['border']['top']['stl']." ".$tableStlData['border']['top']['color']);
//                }
//                if ($tableStlData['border']['bottom']['color'] > '') {
//                    $tdStyle->setAttribute("border-bottom", "1px ".$tableStlData['border']['bottom']['stl']." ".$tableStlData['border']['bottom']['color']);
//                }
                
                // Set border type
                if($inVBc == 'auto')$inVBc = '000000';
                if($inHBc == 'auto')$inHBc = '000000';
                if ($inVBc > '') {
                    $tdStyle->setAttribute("border-left", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
                    $tdStyle->setAttribute("border-right", "1px ".HWPFWrapper::getBorder($inVBtp)." #$inVBc");
                    $tdStyle->setAttribute("border-top", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
                    $tdStyle->setAttribute("border-bottom", "1px ".HWPFWrapper::getBorder($inHBtp)." #$inHBc");
                }
                
                // Set class on the cell
                $cnm = $this->mainStyleSheet->getClassName($tdStyle);
                $cellTag->setClass($cnm);
                
                // Get cell text
                //$text = java_values($cell->getText());
                
                // Get and iterate through cell paragraphs
                $paragraphs = java_values($cell->getParagraphs());
                foreach ($paragraphs as $key=>$paragraph) {
                    
                    // Adjust unique ID for paragraph
                    $uniqueId++;
                    
                    // Parse paragraph
                    $paragraphHTMLElement = $this->parseParagraph($paragraph, $uniqueId);
                    
                    // Set cell margins on temp style
                    $tmpStyle = new StyleClass();
//                    $tmpStyle->setAttribute("margin-top", $cellTopMargin.'px');
//                    $tmpStyle->setAttribute("margin-left", $cellLeftMargin.'px');
//                    $tmpStyle->setAttribute("margin-right", $cellRightMargin.'px');
//                    $tmpStyle->setAttribute("margin-bottom", $cellBottomMargin.'px');
                    
                    // Apply margins to paragraph
                    $className = $this->mainStyleSheet->getClassName($tmpStyle);
                    $paragraphHTMLElement->setClass($paragraphHTMLElement->getClass().' '.$className);
                    
                    // Add paragraph to cell tag
                    $cellTag->addInnerElement($paragraphHTMLElement);
                }
                
                // Add cell tag to row
                $rowTag->addInnerElement($cellTag);
            }
            
            // Add row to the container
            $container->addInnerElement($rowTag);
        }
        
        // Get and set class name on container
        $className = $this->mainStyleSheet->getClassName($tableStyleClass);
        $container->setClass($className);
        
        // Return container
        return $container;
    }

    /**
     * Process table style
     * @todo    Need to recheck all the XML values we are getting.
     * @param   object  XML Style
     * @return  object  Style data
     */
    private function processTableStyle($styleXML)
    {
        // Check if passed style XML is null and return (CHAUC-3902)
        if ($styleXML == NULL) return;

        // Prepare XML style
        $styleXML = str_replace('w:', 'w', $styleXML); 
        $xml = new SimpleXMLElement($styleXML);
        
        // firstRow #first row
        $dt['fill']['firstRow'] = $xml->xpath("wtblStylePr[@wtype='firstRow']/wtcPr/wshd");
        $dt['fill']['firstRow'] = (isset($dt['fill']['firstRow'][0]['wfill'])) ? '#'.$dt['fill']['firstRow'][0]['wfill'] : '#';

        // lastRow #last row
        $dt['fill']['lastRow'] = $xml->xpath("wtblStylePr[@wtype='lastRow']/wtcPr/wshd");
        $dt['fill']['lastRow'] = (isset($dt['fill']['lastRow'][0]['wfill'])) ? '#'.$dt['fill']['lastRow'][0]['wfill'] : '#';
        
        // firstCol #first column
        $dt['fill']['firstCol'] = $xml->xpath("wtblStylePr[@wtype='firstCol']/wtcPr/wshd");
        $dt['fill']['firstCol'] = (isset($dt['fill']['firstCol'][0]['wfill'])) ? '#'.$dt['fill']['firstCol'][0]['wfill'] : '#';
        
        // lastCol #last column
        $dt['fill']['lastCol'] = $xml->xpath("wtblStylePr[@wtype='lastCol']/wtcPr/wshd");
        $dt['fill']['lastCol'] = (isset($dt['fill']['lastCol'][0]['wfill'])) ? '#'.$dt['fill']['lastCol'][0]['wfill'] : '#';
        
        // band1Vert #odd column
        $dt['fill']['band1Vert'] = $xml->xpath("wtblStylePr[@wtype='band1Vert']/wtcPr/wshd");
        $dt['fill']['band1Vert'] = (isset($dt['fill']['band1Vert'][0]['wfill'])) ? '#'.$dt['fill']['band1Vert'][0]['wfill'] : '#';
        
        // band2Vert #even column
        $dt['fill']['band2Vert'] = $xml->xpath("wtblStylePr[@wtype='band2Vert']/wtcPr/wshd");
        $dt['fill']['band2Vert'] = (isset($dt['fill']['band2Vert'][0]['wfill'])) ? '#'.$dt['fill']['band2Vert'][0]['wfill'] : '#';
        
        // band1Horz #odd row
        $dt['fill']['band1Horz'] = $xml->xpath("wtblStylePr[@wtype='band1Horz']/wtcPr/wshd");
        $dt['fill']['band1Horz'] = (isset($dt['fill']['band1Horz'][0]['wfill'])) ? '#'.$dt['fill']['band1Horz'][0]['wfill'] : '#';
        
        // band2Horz #even row
        $dt['fill']['band2Horz'] = $xml->xpath("wtblStylePr[@wtype='band2Horz']/wtcPr/wshd");
        $dt['fill']['band2Horz'] = (isset($dt['fill']['band2Horz'][0]['wfill'])) ? '#'.$dt['fill']['band2Horz'][0]['wfill'] : '#';
        
        // neCell #last cell in first row
        $dt['fill']['neCell'] = $xml->xpath("wtblStylePr[@wtype='neCell']/wtcPr/wshd");
        $dt['fill']['neCell'] = (isset($dt['fill']['neCell'][0]['wfill'])) ? '#'.$dt['fill']['neCell'][0]['wfill'] : '#';
        
        // nwCell #first cell in first row
        $dt['fill']['nwCell'] = $xml->xpath("wtblStylePr[@wtype='nwCell']/wtcPr/wshd");
        $dt['fill']['nwCell'] = (isset($dt['fill']['nwCell'][0]['wfill'])) ? '#'.$dt['fill']['nwCell'][0]['wfill'] : '#';
        
        // seCell #last cell in last row
        $dt['fill']['seCell'] = $xml->xpath("wtblStylePr[@wtype='seCell']/wtcPr/wshd");
        $dt['fill']['seCell'] = (isset($dt['fill']['seCell'][0]['wfill'])) ? '#'.$dt['fill']['seCell'][0]['wfill'] : '#';
        
        // swCell #first cell in last row
        $dt['fill']['swCell'] = $xml->xpath("wtblStylePr[@wtype='swCell']/swCell/wshd");
        $dt['fill']['swCell'] = (isset($dt['fill']['swCell'][0]['wfill'])) ? '#'.$dt['fill']['swCell'][0]['wfill'] : '#';
        
        // Preset style to the top of border
        $tmp = $xml->xpath("wtblPr/wtblBorders/wtop");
        $dt['border']['top']['stl'] = HWPFWrapper::getBorder(''.$tmp[0]['wval']);
        $dt['border']['top']['sz'] =''.$tmp[0]['wsz'];
        $dt['border']['top']['color'] = HWPFWrapper::colorFix(''.$tmp[0]['wcolor']);
        
        // Preset style to the left of border
        $tmp = $xml->xpath("wtblPr/wtblBorders/wleft");
        $dt['border']['left']['stl'] = HWPFWrapper::getBorder(''.$tmp[0]['wval']);
        $dt['border']['left']['sz'] = ''.$tmp[0]['wsz'];
        $dt['border']['left']['color'] = HWPFWrapper::colorFix(''.$tmp[0]['wcolor']);
        
        // Preset style to the right of border
        $tmp = $xml->xpath("wtblPr/wtblBorders/wright");
        $dt['border']['right']['stl'] = HWPFWrapper::getBorder(''.$tmp[0]['wval']);
        $dt['border']['right']['sz'] = ''.$tmp[0]['wsz'];
        $dt['border']['right']['color'] = HWPFWrapper::colorFix(''.$tmp[0]['wcolor']);
        
        // Preset style to the bottom of border
        $tmp = $xml->xpath("wtblPr/wtblBorders/wbottom");
        $dt['border']['bottom']['stl'] = HWPFWrapper::getBorder(''.$tmp[0]['wval']);
        $dt['border']['bottom']['sz'] = ''.$tmp[0]['wsz'];
        $dt['border']['bottom']['color'] = HWPFWrapper::colorFix(''.$tmp[0]['wcolor']);	    	    	    
        
        // Return style information
        return $dt;
    }

    /**
     * Parses a paragraph element of HWPF document
     * @param   object  Paragraph
     * @param   string  Element key
     * @return  HTMLElement
     */
    private function parseParagraph($paragraph, $parIterator)
    {
        // Create new HTML element and its style class
        $container = new HTMLElement(HTMLElement::P);
        $styleClass = new StyleClass();
        
        // Get styles
        $this->styles = $this->document->getStyles();
        
        // Get character runs
        $charRuns = java_values($paragraph->getRuns());
        
        // Check if paragraph has its style ID
        if (java_values($paragraph->getStyleID()) != null) {

            // Get style class
            $style = $this->styles->getStyle($paragraph->getStyleID());
            $styleXML = java_values($style->getCTStyle()->toString());
            $styleClass = $this->processStyle($styleClass, $styleXML);

        } else {
            
            // Normal style (margin bottom 0.14in)
            $styleClass->setAttribute("margin-bottom", '0.14in');
        }
        
        // Set indentation
        $indentation = java_values($paragraph->getIndentationFirstLine());

        // Check if is list indentation
        $numberingInfo = $this->paragraphExtractNumbering($paragraph);

        //Set indentation to paragraphs except for list items
        if ($indentation > 0 and !$numberingInfo) {
            $styleClass->setAttribute("text-indent", round($indentation / 11).'px');
        }
        
        // Get xml of this paragraph
        $paragraph_xml = java_values($paragraph->getCTP()->toString());

        //var_dump($paragraph_xml);

        //Check if is a table of contents
        if (strpos($paragraph_xml, '<w:fldChar w:fldCharType="begin"/>') !== false) {
            $tocContainer = $this->parseToc($paragraph);
            return $tocContainer;
        }

        // Check for page break
        if (strpos($paragraph_xml, '<w:br w:type="page"/>') !== false) {
            
            // This is a page break
            return self::PAGE_BREAK;
        }

        // Check if there is a line spacing for the paragraph
        $PStyleXml = java_values($paragraph->getCTP()->toString());

        //var_dump($PStyleXml);

        $paragraphXml = str_replace('w:', 'w', $PStyleXml);
        $xml = new SimpleXMLElement($paragraphXml);
        $PSpacingStyle = $xml->xpath('*/wspacing');

        if ($PSpacingStyle){

            //Get the line space value
            $PSpacingLineValue = ((string)$PSpacingStyle[0]['wline']);

            if($PSpacingLineValue=='240'){
                $lineValue = '100';
            }else{
                $tmpSpace = ((int)$PSpacingLineValue - 240);
                $addSpace = $tmpSpace + 100;
                $lineValue = ((string)$addSpace);
            }

            //Set paragraph line spacing
            $styleClass->setAttribute("line-height",$lineValue."%");
        }

        //Check paragraph Numfmt listing level
        //$numFmt = java_values($paragraph->getNumFmt());
        //var_dump($numFmt);
        
        // Iterate through paragraph characters
        for ($j = 0; $j < count($charRuns); $j++) {

            // Character run is a simple part of text (of paragraph), every character or word of
            // character run shares the same style properties within entire character run.
            $characterRun = $charRuns[$j];
            $pictures = java_values($characterRun->getEmbeddedPictures());
            $charRunHTMLElement = $this->parseCharacterRun($characterRun);
            
            // Check if this is a picture
            if (count($pictures) > 0) {
                
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            
            // This part is needed in order to merge similar SPAN tags in one, if they have same
            // style (this will also prevent unneeded spaces)
            } else if (@isset($prevCharRunHTMLElement) && $charRunHTMLElement->getClass() == $prevCharRunHTMLElement->getClass()) {

                $container->getLastElement()->addInnerText($charRunHTMLElement->getInnerText());

            // The rest of elements
            } else {
                
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            }

        }
	
        // Get alignment
        $alignment = java_values($paragraph->getAlignment()->getValue());
        $justification = HWPFWrapper::getAlignment($alignment);
        
        // Set alignment on paragraph
        $styleClass->setAttribute("text-align", $justification);
 	    $className = $this->mainStyleSheet->getClassName($styleClass);
        $container->setClass('textframe horizontal common_style1 ' . $className);
        
        // Add id attribute to container for this paragraph
        $container->setAttribute('id', 'div_' . $parIterator);

        // Return container
        return $container;
    }
    
    /**
     * Extracts numbering information
     * @param   object  Paragraph
     * @return  string|boolean  Numbering
     */
    private function paragraphExtractNumbering($paragraph) 
    {
        // Prepare paragraph XML
        $paragraph_xml = java_values($paragraph->getCTP()->toString());
        $paragraph_xml = str_replace('w:', 'w', $paragraph_xml);
        
        // Get level
        $xml = new SimpleXMLElement($paragraph_xml);
        $lvl = $xml->xpath("wpPr/wnumPr/wilvl");
        
        // Check if there is numbering on level
        if (!is_array($lvl) || count($lvl) == 0) {
            return false;
        }
        
        // Get numbering ID
        $lvl = $lvl[0]['wval'].'';
        $numId = $xml->xpath("wpPr/wnumPr/wnumId");
        $numId = $numId[0]['wval'].'';
        
        // Set numbering data
        $data['lvl'] = $lvl;
        $data['numId'] = $numId;
        
        // Check numbering ID and return data
        if ($numId >= '1') {
            return $data;
        }
        
        // No numbering found
        return false;
    }
    
    /**
     * Currently is not being used.
     * @ignore
     * @param type $field
     * @param type $paragraph
     * @param type $index
     * @return \HTMLElement
     */
    private function processField($field, $paragraph, $index)
    {
        $type = java_values($field->getType());
        
        switch ($type) {
            case 37: // page reference
                break;	
            case 58: // Embedded Object
                break;
            case 83: // drop down
                break;
            case 88: // hyperlink
                
                $url = java_values($paragraph->getCharacterRun($index+2)->text());
                $innerTextElement = $charRunHTMLElement = $this->parseCharacterRun($paragraph->getCharacterRun($index+6));
                
                $link = new HTMLElement(HTMLElement::A);
                $link->setAttribute('href', $url);
                $link->addInnerElement($innerTextElement);

                return $link;
                break;
            
            case 37: // page reference
                break;																		
            default: break;
        }
    }

    /**
     * Parses a characterRun element of HWPF document
     * @param   object  Character run
     * @return  HTMLElement
     */
    private function parseCharacterRun($characterRun)
    {
        // Create null container
        $container = null;

        // Even non text elements will have a text property
        $text = nl2br(java_values($characterRun->getText(0))); 
        
        // Get and process pictures if there are any
        $pictures = java_values($characterRun->getEmbeddedPictures());
        if (count($pictures) > 0) {
            foreach ($pictures as $key => $picture) {
                $container = $this->processPicture($picture);
                return $container;
            }
        }

        //Get Run xml
        $charXml = java_values($characterRun->getCTR()->ToString());
        $charXml = str_replace('w:', 'w', $charXml);
        $xml = new SimpleXMLElement($charXml);

        //Get xml of the parent paragraph
        $xmlParagraph = java_values($characterRun->getParagraph()->getCTP()->ToString());
        $xmlParagraph = str_replace('w:', 'w', $xmlParagraph);
        $xmlP = new SimpleXMLElement($xmlParagraph);

        //Get font of the paragraph
        $paragraphFontSize = $xmlP->xpath('wpPr/wrPr/wsz');
        $paragraphFontSize = ($paragraphFontSize) ? ((string) ($paragraphFontSize[0]['wval']/2) ) : '';

        //Get the value of hyperlink
        $link = $xml->xpath("wrPr/wrStyle");

        //Check if the hyperlink xml tag exists
        if (!empty($link)) {
            $linkValue = $link[0]['wval'];
        } else {
            $linkValue = "none";
        }

        //Check if is valid internet link
        if($linkValue == 'InternetLink'){

            //Create empty A tag for the hyperlink and the text
            $container = new HTMLElement(HTMLElement::A);
            $container->setAttribute('href',java_values($characterRun->getText(0)));

        }else {
            /* In every other case, if we got here we do simple text parsing */
            // Create empty text element
            $container = new HTMLElement(HTMLElement::SPAN);
        }
        
        // Get color
        $color = java_values($characterRun->getColor());
        if(is_null($color)){
            $color = 'black';
        }else {
            $color = '#'.$color;
        }

        //Get background highlight color
        $runCharShadows = $xml->xpath("wrPr/wshd");
        if(!empty($runCharShadows)) {
            $backgroundColor = $runCharShadows[0]['wfill'];
        }else {
            $backgroundColor = null;
        }

        // Get style
        $isItalic = java_values($characterRun->isItalic()); 
        $fontStyle = ($isItalic) ? 'italic' : 'normal';

        // Get weight
        $isBold = java_values($characterRun->isBold()); 
        $fontWeight = ($isBold) ? 'bold' : 'normal';
        
        // Get char font family and size
        $fontFamily = java_values($characterRun->getFontFamily()); 
        $fontSize = floor(java_values($characterRun->getFontSize()));

        // Get parent paragraph font family
        $fonts = $xmlP->xpath("wpPr/wrPr/wrFonts");

        //Check if the Fonts xml tag exists
        if (!empty($fonts)) {
            $pFontFamily = $fonts[0]['wascii'][0];
        } else {
            $pFontFamily = "none";
        }


        // Get underline
        $underlined_type = java_values($characterRun->getUnderline()->getValue());

        //Default underline set to none
        if(!is_int($underlined_type)) {
            $underlined_type = 12;
        }
        $underlined = HWPFWrapper::getUnderline($underlined_type);
        
        // Create empty class, and attach it to span element
        $styleClass = new StyleClass();
        
        // Prepare ctrStyle
        $ctrStyle = $xml->xpath('*/wrStyle');
        if ($ctrStyle) $ctrStyle = ((string)$ctrStyle[0]['wval']);
        
        // Get style class
        if (!is_array($ctrStyle)) {
            $style = $this->styles->getStyle($ctrStyle);
            $styleXML = java_values($style->getCTStyle()->toString());
            $styleClass = $this->processStyle($styleClass, $styleXML);
        }

        //If this value change to true the text is wrapped in span
        $spanActivator = true;

        //Initiate semantic containers
        $boldContainer = null;
        $italicContainer = null;
        
        // Set style attributes
        if ($color != 'black'  and $color!= '#000000'){
            $styleClass->setAttribute("color", $color);
            $spanActivator = true;
        }
        if ($fontWeight != 'normal') {
            $boldContainer = new HTMLElement(HTMLElement::STRONG);
        }
        if ($fontStyle != 'normal') {
            $italicContainer = new HTMLElement(HTMLElement::EM);
        }

        if ($fontSize) {
            $styleClass->setAttribute("font-size", (string)$fontSize."pt");
            //$spanActivator = true;
        }

        if ($fontFamily) {
            $styleFont = HWPFWrapper::getFontFamily($fontFamily);
            $styleClass->setAttribute("font-family", $styleFont);
            //$spanActivator = true;
        }
        if ($underlined != 'none') {
            $styleClass->setAttribute("text-decoration", $underlined);
            $spanActivator = true;
        }
        if (!is_null($backgroundColor)) {
            $styleClass->setAttribute("background-color", "#" . $backgroundColor->__toString());
            $spanActivator = true;
        }
        
        // Get and set class name on container
        $className = $this->mainStyleSheet->getClassName($styleClass);


        if( $spanActivator and $container->getTagName() != 'a') {
            $container = new HTMLElement(HTMLElement::SPAN);
        }

        $addNewLine = false;
        // Check for new line
        if (strlen($text) == 1 && (substr($text, -1, 1) == "\r" || ord(substr($text, -1, 1)) == HWPFWrapper::BEL_MARK))
        {
            $addNewLine = true;
        }

        //escape text for xhtml
        $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));

        if($addNewLine)
        {
            $text .= '<br />';
        }


        //TODO check why this fails with large documents
        //if($container->getTagName() == '') $container->setInnerElement($text);
        if($boldContainer and $italicContainer) {

            //Set Bold and italic semantic tags
            $container->addInnerElement($boldContainer);
            $boldContainer->addInnerElement($italicContainer);
            $italicContainer->setInnerText($text);

        }elseif($boldContainer){

            //Set bold strong tag
            $container->addInnerElement($boldContainer);
            $boldContainer->setInnerText($text);

        }elseif($italicContainer){

            // Set italic em tag
            $container->addInnerElement($italicContainer);
            $italicContainer->setInnerText($text);
        }else{

            // Set inner text to span tag
            $container->setInnerText($text);
        }

        // Get and set class name on container
        $container->setClass($className . ' textframe cke_focus');
        
        // Return container
        return $container;
    }
    
    /**
     * Process element style
     * @param   object  Style class
     * @param   string  Style XML
     * @return  object
     */
    private function processStyle($styleClass, $styleXML)
    {
        // Get style XML
        $styleXML = str_replace('w:', 'w', $styleXML);
        $xml = new SimpleXMLElement($styleXML);
        
        // TODO: Check what this is being used for (currently not in use)
        $based = $xml->xpath('*/wbasedOn');
        if ($based) $based = ((string)$based[0]['wval']);
        
        // Get font
        $font = $xml->xpath('*/wrFonts');
        $font = ($font) ? ((string)$font[0]['wascii']) : '';
        
        // Get background color
        $wshd = $xml->xpath('*/wshd');
        if ($wshd) $wshd = ((string)$wshd[0]['wfill']);
        
        // Get text color
        $color = $xml->xpath('*/wcolor');
        $color = ($color) ? ((string)$color[0]['wval']) : false;
	    if ($color == 'auto') $color = '000000';
	
        // Get font size
        $sz = $xml->xpath('*/wsz');
        $sz = ($sz) ? floor(((string)$sz[0]['wval'])/2) : '';
        
        // Get first line indentation
        $ident = $xml->xpath('*/wind');
        if ($ident) {
            $identNum = round(((string)$ident[0]['wfirstLine'])/11).'px';
            if ($identNum == '0px') {
                $identNum = round(((string)$ident[0]['wleft'])/11).'px';
            }
            $ident = $identNum;
        } else {
            $ident = '';
        }
        
        // Get top and bottom margins
        $spacing = $xml->xpath('*/wspacing');
        if ($spacing) {
            $spacingBefore = ((string)$spacing[0]['wbefore']);
            $spacingAfter = ((string)$spacing[0]['wafter']);
        } else {
            $spacingBefore = 0;
            $spacingAfter = 0;
        }
        
        // Get font weight
        $bold = $xml->xpath('*/wb');
        $weight = ($bold) ? 'bold' : 'normal';
        
        // Get font style
        $italic = $xml->xpath('*/wi');
        $italic = ($italic) ? true : false;
        
        // Get text transformation
        $allcaps = $xml->xpath('*/wcaps');
        $allcaps = ($allcaps) ? true : false;
        
        // Set margins
        $styleClass->setAttribute("margin-top", round(((string)$spacingBefore)/11).'px');
        $styleClass->setAttribute("margin-bottom", round(((string)$spacingAfter)/11).'px');
        
        // Set font styles
        $styleClass->setAttribute("font-family", $font);
        if ($color) $styleClass->setAttribute("color", '#'.$color);
        if (@$wshd) $styleClass->setAttribute("background-color", '#'.$wshd);
        if ($sz > '') $styleClass->setAttribute("font-size", $sz.'pt');
        if ($weight != 'normal') $styleClass->setAttribute("font-weight", $weight);
        if ($italic) $styleClass->setAttribute("font-style", 'italic');
        if ($allcaps) $styleClass->setAttribute("text-transform", 'uppercase');
        if ($ident > '') $styleClass->setAttribute("text-indent", $ident);
        
        // Return styled class
        return $styleClass;
    }
    
    /**
     * Parses a picture element of HWPF document
     * @param   object    Picture
     * @return  HTMLElement
     */
    private function processPicture($picture)
    {   
        // Get picture data
        $pictureData = $picture->getPictureData();
        $picContent = java_values($pictureData->getData());
        $picName = java_values($pictureData->getFileName());
        $picType = java_values($pictureData->getPictureType());
        
        // Get picture dimensions
        $ct_xml = java_values($picture->getCTPicture()->toString());
        $xml = new SimpleXMLElement($ct_xml);
        $picExt = $xml->xpath('/xml-fragment/pic:spPr/a:xfrm/a:ext');
        $picExt = $picExt[0];
       	$cx = $picExt['cx'];
       	$cy = $picExt['cy'];
       	$widthInch = round(intval($cx) / HWPFWrapper::EMUS_PER_INCH, 4);
       	$heightInch = round(intval($cy) / HWPFWrapper::EMUS_PER_INCH, 4);
        
        // Set ALT data
        $alt = '';
        if (isset($this->images_data[$this->images_currentIndex])) {
            $imageData = $this->images_data[$this->images_currentIndex];
            if (strlen($imageData['title']) > 0) {
                $alt = $imageData['title'];
                if (strlen($imageData['descr']) > 0) {
                    $alt .=': '.$imageData['descr'];
                }
            } else if (strlen($imageData['descr']) > 0) {
                $alt = $imageData['descr'];
            }
        }
        
        // Adjust current image index
        $this->images_currentIndex++;

        // Create path to image
        $path = $this->_tmp_path . '/images'.$picName;
        $dirname = dirname($path);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0775, true);
        }

        // Store picture in a tmp dir
        $fp = fopen($path, 'w');
        fwrite($fp, $picContent);
        fclose($fp);
        $this->images[$picName] = $picName;
        
        // Create fake ID
        $fakeId = strtolower(str_replace(' ', '', $picName)).'_'.$this->images_currentIndex;

        // Creating img element with path to newly created picture
        $container = new HTMLElement(HTMLElement::IMG);
        $container->setAttribute("style", "width: {$widthInch}in; height: {$heightInch}in; display: initial");
        $container->setAttribute("src",  '../images/'.$picName);
        $container->setAttribute("data-ch-file-id",  "{$picName}");
        $container->setAttribute("alt",  $alt);
        $container->setAttribute("title",  $alt);
        $container->setAttribute("id",  'image_'.$fakeId);
        
        // Return image container
        return $container;
    }

    /**
     * Collect all the document drawing properties
     * @param   object  Paragraph
     */
    private function collectDrawingProperties($paragraph)
    {
        // Prepare XML
        $xml = java_values($paragraph->getCTP()->toString());
        $xml = str_replace('w:', 'w', $xml);
        $xml = str_replace('wp:', 'wp', $xml);
        $xml = new SimpleXMLElement($xml);
        
        // Get object data
        $obj = $xml->xpath("wr/wdrawing/wpinline/wpdocPr");
        
        // Check if there are any properties
        if (count($obj) > 0) {
            
            // Loop through images for this paragraph
            foreach ($obj as $key => $imageData) {
                
                // Add to images data (append '' so it converts to string)
                $this->images_data[count($this->images_data)] = array(
                    'id' => $imageData[0]['id'].'',
                    'name' => $imageData[0]['name'].'',
                    'title' => (isset($imageData[0]['title'])) ? $imageData[0]['title'].'' : '',
                    'descr' => (isset($imageData[0]['descr'])) ? $imageData[0]['descr'].'' : ''
                );
            }
        }
    }
    
    /**
     * @depricated
     * Parses additional information of doc file like, title, keywords, author e.g.
     */
    private function parseAdditionalInformation()
    {
        /*
        // Retreive summary information object to work with
        $summaryInformation = $this->document->getSummaryInformation();

        if (java_values($summaryInformation) == null) return;

        // Get document title
        $title = java_values($summaryInformation->getTitle());

        // TODO: add author and keywords
        $this->htmlDocument->setTitle($title);
        */
    }

    /**
     * Get HTMLDocument
     * @return  HTMLDocument
     */
    public function getHTMLDocument()
    {
        return $this->htmlDocument;
    }

    /**
     * Get HTMLDocument Pages array
     * @return  HTMLDocument
     */
    public function getHTMLPages()
    {
        return $this->pages;
    }
        
    /**
     * Get images
     * @return  object  Images
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * Parse a list element of the document
     * @param $paragraph
     * @return mixed
     */
    private function parseList($paragraph)
    {
        //Create list item element
        $listItem = new HTMLElement(HTMLElement::LI);

        //Parse paragraph
        $paragraphContainer = $this->parseParagraph($paragraph, $this->listItemIterator);
        $paragraphContainer->setAttribute('style','text-indent:0px');

        $this->listItemIterator++;
        $listItem->setInnerElement($paragraphContainer);

        return $listItem;
    }

    /**
     * Process a list of the document
     * @param $numberingInfo
     * @param $container
     * @param $paragraph
     */
    private function processList($numberingInfo, $container ,$paragraph){

        //Get Numbering object
        $numberingObject = java_values($this->document->getNumbering());

        //Cast to XWPFNumbering
        $numbering = java_cast($numberingObject, 'org.apache.poi.xwpf.usermodel.XWPFNumbering');

        //Create java big integer object
        $numId = new Java('java.math.BigInteger',$numberingInfo['numId']);

        //get abstract numbering
        $absNumId = java_values($numbering->getAbstractNumId($numId)->toString());

        //Create java big integer objecthttps://metrodigi.atlassian.net/wiki/display/CA/Word+Parser
        $finalId = new Java('java.math.BigInteger',$absNumId);

        //get abstract numbering
        $abstractNum = java_values($numbering->getAbstractNum($finalId));

        //Check if the object was created
        if(is_object($abstractNum)) {

            //get xml structure
            $stringNumbering = java_values($abstractNum->getCTAbstractNum()->ToString());
            $numberingXml = str_replace('w:', 'w', $stringNumbering);
            $numXml = new SimpleXMLElement($numberingXml);
            $ilvl = $numXml->xpath('wlvl/wnumFmt');
            $ilvlSymbol = $numXml->xpath('wlvl/wlvlText');
            $listSymbol = $ilvlSymbol[$numberingInfo['lvl']]["wval"];

            //Calculate list indentation
            $listIndentation = $this->calculateListIndentation($numXml ,$numberingInfo);

            //check if the list type exist and get style rule
            if (is_array($ilvl)) {
                $enc = utf8_encode($listSymbol);
                $listType = utf8_encode($ilvl[$numberingInfo['lvl']]["wval"]);
                $listTypeStyle = HWPFWrapper::getListType($listType, $enc);
            }

        }else{
            //TODO Throw exception in case of failure
            //var_dump($numbering);Exception
            //var_dump($abstractNum);
        }

        //If this is set to true a new list container should be created
        $newListActivator = false;

        if($this->listNumId != $numberingInfo['numId'] ){
            $newListActivator = true;
        }

        $levelCount = $numberingInfo['lvl'];

        //Check if the list level state has change
        if($this->listLevelState != $levelCount){

            //Create new list
            $newList = new HTMLElement(HTMLElement::UL);
            $newList->setAttribute('style','list-style-type:'.$listTypeStyle);

            //Get last ul element to add the new list ul container
            $lastContainer = $container->getLastElement();

            for($i=0;$i<$this->listLevelState;$i++){
                $lastContainer = $lastContainer->getLastElement();
            }
            //Add new list in the last container
            if(is_object($lastContainer)) {
                $lastContainer->addInnerElement($newList);
            }
        }

        //Assign new list level state and num id
        $this->listLevelState = $numberingInfo['lvl'];
        $this->listNumId = $numberingInfo['numId'];

        // Parse list item
        $listItemHTMLElement = $this->parseList($paragraph);

        //Check if the container has a last element to avoid non object exception
        if(is_object($container->getLastElement()) and !$newListActivator){

            //Assign the tag name
            $containerLastElement = $container->getLastElement()->getTagName();

        }elseif($newListActivator){

            //Add another level list
            $containerLastElement = "innerul";
        }else{

            //Set default in case the container has no inner elements
            $containerLastElement = "text";
        }

        // Check if the actual container has a list container as last element
        if( $containerLastElement == "ul"  ){

            //Initialize last container
            $lastContainer = $container->getLastElement();

            //Find current list element
            for($i=0;$i<$this->listLevelState;$i++){
                $lastContainer = $lastContainer->getLastElement();
            }

            //Check if the container is fill
            if(is_object($lastContainer)) {
                $lastContainer->addInnerElement($listItemHTMLElement);
            }

        }elseif($containerLastElement == 'innerul' or $containerLastElement != "ul") {

            //Create a new list container
            $listContainer = new HTMLElement(HTMLElement::UL);

            //Set list type style
            $listContainer->setAttribute('style','list-style-type:'.$listTypeStyle.';'.'margin-left:'.$listIndentation.'px' );

            //Fill the list with the first li item
            $listContainer->addInnerElement($listItemHTMLElement);

            //Set the list to the container
            $container->addInnerElement($listContainer);
        }
    }

    /**
     * Get list indentation from the list
     * @param $numXml
     * @param $numberingInfo
     * @return int
     */
    public function calculateListIndentation($numXml ,$numberingInfo){

        $ipind = $numXml->xpath('wlvl/wpPr/wind');

        //Get indentation values
        try {
            $hanging = $ipind[$numberingInfo['lvl']]['whanging'];
            $wleft = $ipind[$numberingInfo['lvl']]['wleft'];
        }catch (Exception $exception){
            //Setting default
            $hanging = 1;
            $wleft = 1;
            var_dump($exception);
        }

        //Calculate list indentation
        $listIndentation = ((intval($wleft) / intval($hanging))/4) * 100;

        return intval($listIndentation);

    }

    /**
     * Parse the table of contents
     * @param $paragraph
     * @return HTMLElement
     */
    public  function parseToc($paragraph){

        $this->tocLevel++;

        //Create table toc table
        $this->toc = new HTMLElement(HTMLElement::TABLE);

        //Create row
        $rowContainer = new HTMLElement(HTMLElement::TR);

        //Get characters
        $runs = java_values($paragraph->getRuns());

        //Get all the HTML characters in the paragraph
        $tocRow = array();

        for($i=0;$i<count($runs);$i++){
            $character = $runs[$i];
            $runHTMLElement = $this->parseCharacterRun($character);
            $tocRow[$i] = $runHTMLElement;
        }

        $tocNum = $tocRow[0];
        $a = 1;

        //Find last element
        while($tocRow[count($runs)-$a]->getInnerText() == "<br />"){
            $a++;
        }

        $a++;
        $desLevel = count($runs)-$a;
        $tocPage = $tocRow[$desLevel];

        //Add all the elements of description
        $cellDescription = new HTMLElement(HTMLElement::TD);

        for($i=1;$i<$desLevel;$i++){
            $testText = $tocRow[$i]->getInnerText();
            $valid = strpos($testText,"<");
            if($valid == false){
                $cellDescription->addInnerElement($tocRow[$i]);
            }
        }

        //Add text to toc number cell element
        $cellNum = new HTMLElement(HTMLElement::TD);
        $cellNum->addInnerElement($tocNum);


        //Add text to description cell
        $cellPage = new HTMLElement(HTMLElement::TD);
        $cellPage->addInnerElement($tocPage);


        //Add elements to the toc table

        //Check if the cell contains text
        if($cellNum->tocCellContainsText()) {
            $cellNum->setAttribute('style','min-width:25px');
            $rowContainer->addInnerElement($cellNum);
        }else{
            $cellDescription->setAttribute('colspan','2');
        }

        //Check if the cell contains text
        if($cellDescription->tocCellContainsText()) {
            $rowContainer->addInnerElement($cellDescription);
        }else{
            $cellNum->setAttribute('colspan','2');
        }

        //Assign cell page
        $rowContainer->addInnerElement($cellPage);

        //Add if this is a toc table
        if($this->tocLevel == 1){
            $container = new HTMLElement(HTMLElement::TABLE);
            $container->addInnerElement($rowContainer);
        }else{
            $container = $rowContainer;
        }

        return $container;
    }

}
