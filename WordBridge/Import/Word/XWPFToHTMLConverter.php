<?php

ini_set('xdebug.var_display_max_depth', 15);
ini_set('xdebug.var_display_max_children', 5256);
ini_set('xdebug.var_display_max_data', 157784);
ini_set('max_execution_time', 600);
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "HTMLDocument.php";
include_once "HTMLElement.php";
include_once "HWPFWrapper.php";
include_once "StyleSheet.php";
include_once "XhtmlEntityConverter.php";
include_once "XWPF/XWPFTable.php";
include_once "XWPF/XWPFSDTCell.php";
include_once "XWPF/XWPFList.php";
include_once "XWPF/XWPFParagraph.php";
include_once "XWPF/TableOfContents.php";
include_once "XWPF/Shapes/ShapeCreator.php";
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/Helpers/ListHelper.php';


/**
 * HWPFToHTMLConverter is a class that takes a doc or docx file as input and converts it to a Simple HTMLDocument, keeping stylesheets, images e.g.
 * The main idea is to reuse Apache POI parser that is integrated with JavaBridge web app on a tomcat server.
 * @author Avetis Zakharyan
 */
class XWPFToHTMLConverter
{

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
     * Numbering
     */
    private $numbering;

    /**
     * HTML Document pages and styles
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
    private $unsupportedImageFormats;

    private $_tmp_path;
    private $_progress;

    /**
     * Table of contents
     */
    private $toc;
    private $tocLevel;
    private $tocNumbering;
    private $hasTOC;
    private $tableOfContents;
    private $documentXml;

    /**
     * XWPF Document Information
     */
    private $numPages;
    private $currentProcessedPart;

    /**
     * Set default page break
     */
    const PAGE_BREAK = -1;
    public static $defaultPageBreak;
    public static $breakH1;
    public static $breakH2;
    public static $manualPageBreak;

    /**
     * Body Part
     */
    const HEADER = 0;
    const BODYELEMENT = 0;
    const FOOTER = 0;
    const CUSTOMLIST = 0;

    /**
     * OPC
     */
    private $headers;
    private $footers;
    private $auxContainer;
    private $auxList;

    /**
     * Section Numbering
     */
    private $grandNumbering;
    private $firstLevel;
    private $secondLevel;
    private $levelCounter;

    /**
     * Custom Style List
     */
    private $customListLevel;
    private $pageCounter;

    /**
     * Headlines
     */
    private $headlineElements;
    private $headlineId;
    private $headLineList;

    /**
     * Sections
     */
    private $sectionContainer;
    private $contador;
    private $articleNumber;
    private $auxArticleContainer;

    /**
     * Java
     */
    private $localJava;
    private $headlineIdSection;
    public static $imagesPath;

    /**
     *
     */

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
        self::$imagesPath = $tmp_path;

        //Set default pageBreak
        self::$defaultPageBreak = false;

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
        $url = $bridge_host . 'WordBridge/java/Java.inc';
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
        $this->localJava = $local;
        // Adjust progress step
        //$this->_progress->incrementStep();
    }

    public static function setPageBreaks($breakPageH1, $breakPageH2)
    {
        if (is_bool($breakPageH1) && is_bool($breakPageH2)) {
            self::$breakH1 = $breakPageH1;
            self::$breakH2 = $breakPageH2;
            self::$manualPageBreak = ($breakPageH1 == false && $breakPageH2 == false) ? true : false;
        }
    }

    public static function getDirectoryPath(){
        return self::$imagesPath;
    }

    public function getMainStyleSheet()
    {
        return $this->mainStyleSheet;
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
        $this->toc = false;
        $this->currentProcessedPart = null;
        $this->headers = array();
        $this->footers = array();
        $this->tocNumbering = array();
        $this->grandNumbering = "";
        $this->firstLevel = 0;
        $this->secondLevel = 0;
        $this->levelCounter = 0;
        $this->pageCounter = 0;
        $this->customListLevel = 0;
        $this->headlineElements = array("h1", "h2", "h3", "h4", "h5", "h6");
        $this->sectionContainer = null;
        $this->contador = null;
        $this->parsingFile = $docx_file;
        $this->unsupportedImageFormats = array('emf' => 'emf', 'wmf' => 'wmf');
        $this->hasTOC = false;
        $this->articleNumber = 0;
        $this->headlineId = 0;
        $this->tableOfContents = array();
        $this->headlineIdSection = -1;
        $this->headLineList = array();

        //Load document and update progress
        $this->document = $this->loadDocx($docx_file);
        //$this->updateStep($docx_file);

        $this->numPages = java_values($this->document->getProperties()->getExtendedProperties()->getUnderlyingProperties()->getPages());

        //Get Numbering object
        $numberingObject = java_values($this->document->getNumbering());

        //Cast to XWPFNumbering
        $this->numbering = java_cast($numberingObject, 'org.apache.poi.xwpf.usermodel.XWPFNumbering');
        $this->styles = $this->document->getStyles();

    }

    /**
     * @param $path
     * @return Java
     * @throws Exception
     */
    public function loadDocx($path)
    {

        $fileInputStream = new Java("java.io.FileInputStream", $path);
        $OPCPackage = new Java("org.apache.poi.openxml4j.opc.OPCPackage");

        if (java_is_null($fileInputStream)) {
            throw new Exception('[XWPFToHTMLConverter::loadDocx] Null Input Stream');
        }
        $input = java_values($OPCPackage->open($fileInputStream));

        if (java_is_null($input)) {
            throw new Exception('[XWPFToHTMLConverter::loadDocx] Java Input Stream Error');
        }

        $document = new Java("org.apache.poi.xwpf.usermodel.XWPFDocument", $input);
//        $this->documentXml = $this->getXMLDocument($document);
//        $valueTest = $this->documentXml->xpath("wbody/wsdt");
//        var_dump($valueTest);
//        var_dump(java_values($document->getDocument()->toString()));
//            w:sdt
//        var_dump(java_values($document->getDocument()->getBody()->getSectPr()->getPgSz()->getW()->doubleValue()->toString()));
//        $pageSize = java_values($-document->getDocument()->getBody()->getSectPr()->getPgSz());
//        $pageHeight = java_values($pageSize->getH()->doubleValue()->toString());
//        $pageWidht =  java_values($pageSize->getW()->doubleValue()->toString());
//        var_dump((int) $pageHeight / 20);
//        var_dump((int) $pageWidht / 20);

        if (is_null($document)) {
            throw new Exception('[XWPFToHTMLConverter::loadDocx] POI XWPFDocument Internal Error');
        }

        return $document;
    }

    /**
     * @return SimpleXMLElement
     */
    private function getXMLDocument($document)
    {
        $strDocument =  java_values($document->getDocument()->toString());
        $charXml = str_replace('w:', 'w', $strDocument);
        $runXml = new SimpleXMLElement($charXml);

        return $runXml;
    }

    /**
     * Update step in Progress Updater
     * @param   string  Path to Word document
     */
    private function updateStep($path)
    {
        // Get contents of Word document
        $handle = fopen($path, "r");
        $contents = fread($handle, filesize($path));
        fclose($handle);

        $chunks = str_split($contents, 10000);
        $arrsize = count($chunks);

        // Update progress with new steps
        //$this->_progress->adjustMaxSteps($arrsize, TRUE);

        // Iterate through content chunks
        for ($i = 0; $i < $arrsize; $i++) {
            $this->_progress->incrementStep();
        }

    }

    public function hasTOC()
    {
        return $this->hasTOC;
    }


    public function getTocNumbering()
    {
        return $this->tocNumbering;
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

//    /**
//     * @param $header
//     *
//     * @return HTMLElement
//     */
//    private function processHeaderFooter($header)
//    {
//
//        //Set current processed part
//        $this->currentProcessedPart = "HEADER";
//
//        //Create header container
//        $headerContainer = new HTMLElement(HTMLElement::DIV);
//
//        //Read header body elements
//        $headerElements = java_values($header->getBodyElements());
//
//        foreach ($headerElements as $keyHead => $headerElement) {
//
//            // Check if element is a table
//            if (java_instanceof($headerElement, java('org.apache.poi.xwpf.usermodel.XWPFTable'))) {
//
//                // Get table out of element
//                $tableHead = java_cast($headerElement, 'org.apache.poi.xwpf.usermodel.XWPFTable');
//
//
//
//                // Parse table
//                $tableElement = $this->parseTable($tableHead, $keyHead);
//
//                //Add table to the
//                $headerContainer->addInnerElement($tableElement);
//            }
//
//            //Check if element is a paragraph
//            if (java_instanceof($headerElement, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {
//
//                // Get paragraph out of element
//                $paragraph = java_cast($headerElement, 'org.apache.poi.xwpf.usermodel.XWPFParagraph');
//
//                // Parse paragraph
//                $paragraphHTMLElementHeader = $this->parseParagraph($paragraph, $keyHead);
//
//                //Add paragraph to the container
//                $headerContainer->addInnerElement($paragraphHTMLElementHeader);
//            }
//        }
//
//        return $headerContainer;
//
//    }

    /**
     * Collect the header and footer objects for the current document
     */
    private function collectHeaderFooterInformation()
    {

        //Setting doc headers
        $headerListSize = java_values($this->document->getHeaderList()->size());

        for ($i = 0; $i < $headerListSize; $i++) {
            $header = java_values($this->document->getHeaderArray($i));
            $this->headers[] = $header;
        }
        //echo "<br/>";
        //Setting doc footers
        $footerListSize = java_values($this->document->getFooterList()->size());
        //echo $footerListSize;

        for ($i = 0; $i < $footerListSize; $i++) {
            $footer = java_values($this->document->getFooterArray($i));
            $this->footers[] = $footer;
        }
    }

    /**
     * Return the current type of element from the document
     * Posible values are PARAGRAPH,TABLE and CONTENTCONTROL
     * @param $element
     * @return mixed
     */
    private function getElementType($element){
        $elementType = java_values($element->getElementType()->toString());
        return $elementType;
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

        //Initialize headers and footers
        $this->collectHeaderFooterInformation();

        // Get document body elements
        $elements = java_values($this->document->getBodyElements());

        //Set current processed part
        $this->currentProcessedPart = "BODY";

        $tocNumber = 0;

        // Every Word document section consists of paragraphs, so we iterate through them, and parse them one by one
        foreach ($elements as $key => $element) {

            $elementType = $this->getElementType($element);

            // Check if element is a table
            if (java_instanceof($element, java('org.apache.poi.xwpf.usermodel.XWPFTable'))) {
                $table = new XWPFTable($element,$key,$this->mainStyleSheet);
                $tableElement = $table->parseTable();
                $container->addInnerElement($tableElement);
            }

            // Check if element is a paragraph
            if (java_instanceof($element, java('org.apache.poi.xwpf.usermodel.XWPFParagraph'))) {

                // Get paragraph out of element
                $paragraph = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFParagraph');

                $numberingInfo = ListHelper::paragraphExtractNumbering($paragraph);
                if ($numberingInfo) {
                    $xwpfList = new XWPFList($paragraph,$this->mainStyleSheet,$key);
                    $xwpfList->parseList($numberingInfo, $container, $paragraph, $this->listNumId, $this->listLevelState);
                } else {
                    $paragraphHTMLElement = $this->parseParagraph($paragraph, $key);
                    $container = $this->processContainer($element, $paragraphHTMLElement, $container, $key);
                }
            }

            // Adjust progress step
            //$this->_progress->incrementStep();

            if($elementType == "CONTENTCONTROL"){
                $tableOfContents = new TableOfContents($element);
                $tocContainer = $tableOfContents->processToc($tocNumber);
                $container->addInnerElement($tocContainer);
                $tocNumber++;
            }

        }

        // Add container to inner body
        $this->htmlDocument->setBody($container);

    }


    private function setCustomList($container, $key)
    {

        if (is_object($this->auxList)) {
            $listTag = $this->auxList->getTagName();

            if ($listTag == "ul") {
                $container->setId($key);
                $container->addInnerElement($this->auxList);
            } elseif ($listTag == "li") {
                $listContainer = $container->getLastElement();
                if (is_object($listContainer)) {
                    $listContainer->addInnerElement($this->auxList);
                }
            }
        }
    }


    /**
     * @param $styleXML
     * @return HTMLElement
     */
    private function processSectionNumberingToc($styleXML)
    {
        //Get item level
        $styleXML = str_replace('w:', 'w', $styleXML);
        $xmlStyle = new SimpleXMLElement($styleXML);
        $lvl = $xmlStyle->xpath("wpPr/wnumPr/wilvl")[0]['wval'];

        //Set numbering
        if ($lvl == 1) {
            $this->firstLevel++;
            $this->secondLevel = 0;
        } elseif ($lvl == 2) {
            $this->secondLevel = 1;
        }

        //Construct numbering string
        $sectionString = "";

        if ($this->secondLevel == 0) {
            $sectionString = $this->grandNumbering . "." . $this->firstLevel;
            $this->levelCounter = 0;
        } elseif ($this->secondLevel == 1) {
            $this->levelCounter++;
            $sectionString = $this->grandNumbering . "." . $this->firstLevel . "." . $this->levelCounter;
        }

        //Create HTML Element to add numbering
        $container = new HTMLElement(HTMLElement::SPAN);
        $container->setInnerText($sectionString);

        return $container;
    }

    /**
     * @param $paragraph
     * @param $container
     */
    private function parseTocBookmarks($paragraph, $container)
    {

        //Get the bookmarks from the xml of the paragraph
        $sectionStyleXml = java_values($paragraph->getCTP()->toString());
        $sectionParagraphXml = str_replace('w:', 'w', $sectionStyleXml);
        $sectionXml = new SimpleXMLElement($sectionParagraphXml);
        $tocStyle = $sectionXml->xpath('wbookmarkStart');

        //Parse section numbering
        foreach ($tocStyle as $bookmark) {

            //Get bookmark name
            $mark = (string)$bookmark['wname'];

            //Compare the current bookmark to the one in toc numbering
            if (array_key_exists($mark, $this->tocNumbering)) {

                //Add the container to the paragraph
                $sectionContainer = new HTMLElement(HTMLElement::SPAN);
                $sectionText = $this->tocNumbering[$mark];
                $this->grandNumbering = $sectionText;
                $this->firstLevel = 0;
                $this->secondLevel = 0;
                $sectionContainer->setInnerText($sectionText);
                $container->addInnerElement($sectionContainer);
            }
        }
    }

    /**
     * @param $numberingInfo
     * @return mixed
     */
    public function getAbstractNum($numberingInfo)
    {
        $numbering = $this->numbering;
        $numId = new Java('java.math.BigInteger', $numberingInfo['numId']);
        $abstractNumId = java_values($numbering->getAbstractNumId($numId)->toString());
        $finalId = new Java('java.math.BigInteger', $abstractNumId);
        $abstractNum = java_values($numbering->getAbstractNum($finalId));

        return $abstractNum;
    }

//    /**
//     * @param $abstractNum
//     * @param $numberingInfo
//     * @return array
//     */
//    private function extractListProperties($abstractNum, $numberingInfo)
//    {
//
//        //Check if the object was created
//        if (is_object($abstractNum)) {
//
//            //get xml structure
//            $stringNumbering = java_values($abstractNum->getCTAbstractNum()->ToString());
//            $numberingXml = str_replace('w:', 'w', $stringNumbering);
//            $numXml = new SimpleXMLElement($numberingXml);
//            $ilvl = $numXml->xpath('wlvl/wnumFmt');
//            $ilvlSymbol = $numXml->xpath('wlvl/wlvlText');
//            $listSymbol = $ilvlSymbol[$numberingInfo['lvl']]["wval"];
//
//            //Calculate list indentation
//            $listIndentation = $this->calculateListIndentation($numXml, $numberingInfo);
//
//            //check if the list type exist and get style rule
//            if (is_array($ilvl)) {
//                $enc = utf8_encode($listSymbol);
//                $listType = utf8_encode($ilvl[$numberingInfo['lvl']]["wval"]);
//                $listTypeStyle = ListHelper::getListType($listType, $enc);
//            }
//
//            $listProperties = array('type' => $listTypeStyle, 'indentation' => $listIndentation);
//
//        } else {
//            //Default settings
//            $listProperties = array('type' => '', 'indentation' => '');
//        }
//
//        return $listProperties;
//    }


    /**
     * @param $paragraph
     * @param $styleXML
     */
    public function parseCustomStyleList($paragraph, $styleXML)
    {

        $styleXML = str_replace('w:', 'w', $styleXML);
        $styleXML = str_replace('-', '', $styleXML);
        $xmlStyle = new SimpleXMLElement($styleXML);
        $numStyle = intval($xmlStyle->xpath('wpPr/wnumPr/wnumId')[0]['wval']);

        $numberingStyleList = array('numId' => $numStyle, 'lvl' => 0);

        $abstractNum = $this->getAbstractNum($numberingStyleList);
        if (!is_null($abstractNum)) {
            $listProperties = ListHelper::extractListProperties($abstractNum, $numberingStyleList);
        } else {
            $listProperties = array('type' => 'disc');
        }
        $listItemStyle = new HTMLElement(HTMLElement::LI);
        $text = java_values($paragraph->getText());
        $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));
        $listItemStyle->setInnerText($text);
        $listItemStyleContainer = null;

        if ($this->customListLevel == 0) {

            $newList = new HTMLElement(HTMLElement::UL);
            $newList->setAttribute('style', 'list-style-type:' . $listProperties['type']);
            $newList->addInnerElement($listItemStyle);
            $this->customListLevel++;
            $listItemStyleContainer = $newList;

        } else {
            $listItemStyleContainer = $listItemStyle;
        }

        $this->auxList = $listItemStyleContainer;
        //return $listItemStyleContainer;
    }

//    /**
//     * Parse Word table
//     * @static var   int Unique ID
//     * @param       object  Table
//     * @param       string  Element key
//     * @return      HTMLElement
//     */
//    private function parseTable($table, $parIterator)
//    {
//        // Set initial unique id for element
//        static $uniqueId = 10000;
//
//        // Create new HTML table element
//        $container = new HTMLElement(HTMLElement::TABLE);
//
//        //Get Styles
//        $this->styles = $this->document->getStyles();
//
//        // Create new table style class
//        $tableStyleClass = new StyleClass();
//
//        // Check if table has style ID assigned
//        if (java_values($table->getStyleID()) != null) {
//
//            // Get style name
//            $style = $this->styles->getStyle($table->getStyleID());
//            //$style_name = java_values($style->getName());
//
//            // Apply style to table style class
//            $styleXML = java_values($style->getCTStyle()->toString());
//            $tableStyleClass = $this->processStyle($tableStyleClass, $styleXML);
//
//            // Get XML out of XML style
//            $tmpStyleXML = str_replace('w:', 'w', $styleXML);
//            $xml = new SimpleXMLElement($tmpStyleXML);
//        }
//
//        // Get horizontal border attributes (color, size, space)
//        $inHBsz = java_values($table->getInsideHBorderSize());
//        $inHBsp = java_values($table->getInsideHBorderSpace());
//
//        //Set default borders
//        if (!java_is_null($table->getInsideHBorderColor())) {
//            $inHBc = java_values($table->getInsideHBorderColor());
//        } else {
//            $inHBc = '00000A';
//        }
//
//        // Get horizontal border attributes (type)
//        if (!java_is_null($table->getInsideHBorderType())) {
//            $inHBtp = java_values($table->getInsideHBorderType()->name());
//        } else {
//            $inHBtp = 'SINGLE';
//        }
//
//        // Get vertical border attributes (color, size, space)
//        $inHVBsz = java_values($table->getInsideVBorderSize());
//        $inVBsp = java_values($table->getInsideVBorderSpace());
//
//        if (!java_is_null($table->getInsideHBorderColor())) {
//            $inVBc = java_values($table->getInsideHBorderColor());
//        } else {
//            $inVBc = '00000A';
//        }
//
//        // Get vertical border attributes (type)
//        if (!java_is_null($table->getInsideVBorderType())) {
//            $inVBtp = java_values($table->getInsideVBorderType()->name());
//        } else {
//            $inVBtp = 'SINGLE';
//        }
//
//        //Set default borders to header tables
//        if ($this->currentProcessedPart == "HEADER") {
//            $inVBc = 'C0C0C0';
//            $inHBc = 'C0C0C0';
//        }
//
//        // Set border attributes
//        $tableStyleClass->setAttribute("border-collapse", "inherit");
//        $tableStyleClass->setAttribute("border-left", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//        $tableStyleClass->setAttribute("border-right", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//        $tableStyleClass->setAttribute("border-top", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//        $tableStyleClass->setAttribute("border-bottom", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//
//        // Preset default width of the table to 100%
//        $tableStyleClass->setAttribute("width", "100%");
//
//        // Get rows and iterate through them
//        $rows = java_values($table->getRows());
//
//        foreach ($rows as $rowKey => $row) {
//
//            // Create new row tag
//            $rowTag = new HTMLElement(HTMLElement::TR);
//            $rowXmlStr = java_values($row->getCtRow()->toString());
//            $tmpRowXmlStr = str_replace('w:', 'w', $rowXmlStr);
//            $rowXml = new SimpleXMLElement($tmpRowXmlStr);
//
//            // Get row height
//            $height = $rowXml->xpath('wtrPr/wtrHeight');
//            $height = (isset($height[0]['wval'])) ? round(intval($height[0]['wval']) / 15.1) . 'px' : 'auto';
//
//            // Set style attributes on the row
//            $trStyle = new StyleClass();
//            $trStyle->setAttribute("border-left", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//            $trStyle->setAttribute("border-right", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//            $trStyle->setAttribute("border-top", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//            $trStyle->setAttribute("border-bottom", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//            $trStyle->setAttribute("height", $height);
//
//            // Set class on the row
//            $cnm = $this->mainStyleSheet->getClassName($trStyle);
//            $rowTag->setClass($cnm);
//
//            // Get and iterate through the row cells
//            $cells = java_values($row->getTableICells());
//            foreach ($cells as $cellKey => $cell) {
//
//                if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFTableCell'))) {
//                    // Create new cell tag
//                    $cellTag = new HTMLElement(HTMLElement::TD);
//                    $xmlstr = java_values($cell->getCTTc()->toString());
//                    $xmlstr = str_replace('w:', 'w', $xmlstr);
//                    $xml = new SimpleXMLElement($xmlstr);
//
//                    // Create cell attributes
//                    $cellDescribers = array();
//                    if ($rowKey == 0) $cellDescribers[] = 'firstRow';
//                    if ($rowKey == count($rows) - 1) $cellDescribers[] = 'lastRow';
//                    if ($cellKey == 0) $cellDescribers[] = 'firstCol';
//                    if ($cellKey == count($cells) - 1) $cellDescribers[] = 'lastCol';
//                    if ($cellKey % 2 == 0) $cellDescribers[] = 'band1Vert';
//                    if ($cellKey % 2 == 1) $cellDescribers[] = 'band2Vert';
//                    if ($rowKey % 2 == 0) $cellDescribers[] = 'band1Horz';
//                    if ($rowKey % 2 == 1) $cellDescribers[] = 'band2Horz';
//                    if ($rowKey == 0 && $cellKey == count($cells) - 1) $cellDescribers[] = 'neCell';
//                    if ($rowKey == 0 && $cellKey == 0) $cellDescribers[] = 'nwCell';
//                    if ($rowKey == count($rows) - 1 && $cellKey == count($cells) - 1) $cellDescribers[] = 'seCell';
//                    if ($rowKey == 0 && $cellKey == 0) $cellDescribers[] = 'swCell ';
//
//                    // Set cell attributes
//                    $tdStyle = new StyleClass();
//
//                    // Set colspan
//                    $gridspan = $xml->xpath('*/wgridSpan');
//                    if ($gridspan) {
//                        $gridspan = ((string)$gridspan[0]['wval']);
//                        $cellTag->setAttribute('colspan', $gridspan);
//                    }
//
//                    // Set cell width
//                    $cellwidth = $xml->xpath('*/wtcW');
//                    if ($cellwidth) {
//                        $cellwidth = ((string)$cellwidth[0]['ww']);
//                        $cellwidth = round($cellwidth / 15.1);
//                        $tdStyle->setAttribute('width', $cellwidth . 'px');
//                    }
//
//                    // Set cell background color
//                    $color = java_values($cell->getColor());
//                    if ($color) {
//                        $tdStyle->setAttribute("background-color", "#" . "$color");
//                    }
//
//                    // Set border type
//
//                    if ($inVBc == 'auto') $inVBc = '000000';
//                    if ($inHBc == 'auto') $inHBc = '000000';
//                    if ($this->currentProcessedPart == "HEADER") {
//                        $inVBc = 'auto';
//                        $inHBc = 'auto';
//                    }
//                    if ($inVBc > '') {
//                        $tdStyle->setAttribute("border-left", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//                        $tdStyle->setAttribute("border-right", "1px " . HWPFWrapper::getBorder($inVBtp) . " #$inVBc");
//                        $tdStyle->setAttribute("border-top", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//                        $tdStyle->setAttribute("border-bottom", "1px " . HWPFWrapper::getBorder($inHBtp) . " #$inHBc");
//                    }
//
//                    // Set class on the cell
//                    $cnm = $this->mainStyleSheet->getClassName($tdStyle);
//                    $cellTag->setClass($cnm);
//
//                    // Get cell text
//                    //$text = java_values($cell->getText());
//
//                    // Get and iterate through cell paragraphs
//                    $paragraphs = java_values($cell->getParagraphs());
//                    foreach ($paragraphs as $key => $paragraph) {
//
//                        // Adjust unique ID for paragraph
//                        $uniqueId++;
//
//                        // Parse paragraph
//                        $paragraphHTMLElement = $this->parseParagraph($paragraph, $uniqueId);
//
//                        // Set cell margins on temp style
//                        $tmpStyle = new StyleClass();
//
//                        // Apply margins to paragraph
//                        $className = $this->mainStyleSheet->getClassName($tmpStyle);
//
//                        if (is_object($paragraphHTMLElement)) {
//                            $paragraphHTMLElement->setClass($paragraphHTMLElement->getClass() . ' ' . $className);
//                            // Add paragraph to cell tag
//                            $cellTag->addInnerElement($paragraphHTMLElement);
//                        } else {
//                            CakeLog::debug('[XWPFToHTMLConverter::parseTable] Empty HTML Element on' . java_values($paragraph->getText()));
//                        }
//
//                    }
//
//                    // Add cell tag to row
//                    $rowTag->addInnerElement($cellTag);
//                }
//
//                if (java_instanceof($cell, java('org.apache.poi.xwpf.usermodel.XWPFSDTCell'))) {
//                    $rowXml = java_values($row->getCtRow()->ToString());
//                    $xwpfSdtCell = new XWPFSDTCell($cell, $rowXml);
//                    $container = $xwpfSdtCell->parseSDTCell();
//                }
//            }
//
//            // Add row to the container
//            $container->addInnerElement($rowTag);
//        }
//
//        // Get and set class name on container
//        $className = $this->mainStyleSheet->getClassName($tableStyleClass);
//        $container->setClass($className);
//
//        // Return container
//        return $container;
//    }


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
        $ctpParagraph = java_values($paragraph->getCTP()->toString());
        //var_dump($ctpParagraph);

        if (strpos($ctpParagraph, 'mc:AlternateContent') !== false) {
            $shapeCreator = new ShapeCreator($ctpParagraph);
            $shapeCreator->processShape();
        }

        // Get character runs
        $charRuns = java_values($paragraph->getIRuns());

        //Check paragraph numbering
        $numberingInfo = ListHelper::paragraphExtractNumbering($paragraph);

        if (java_values($paragraph->getStyleID()) != null) {

            // Get style class
            $style = $this->styles->getStyle($paragraph->getStyleID());
            $styleXML = java_values($style->getCTStyle()->toString());

            //Check section numbering
            if (strpos($styleXML, '<w:ilvl') !== false) {
                $sectionContainer = $this->processSectionNumberingToc($styleXML);
            }

            //Check if paragraph has custom style list
            if (strpos($styleXML, '<w:numId') !== false and strpos($styleXML, 'w:customStyle="1"') !== false and !$numberingInfo) {
                //Parse Custom List
                $this->parseCustomStyleList($paragraph, $styleXML);
                return self::CUSTOMLIST;
            }

//            //Get style name
//            $styleName = java_values($style->getName());
//
//            //Apply heading html tag and section numbering if exists
//            if (strpos(strtolower($styleName), 'heading') !== false) {
//
//                //Create headline container
//                $container = $this->selectHeadlineContainer($styleName);
//            }
//
//            //Process paragraph style
//            $styleClass = $this->processStyle($styleClass, $styleXML);

            //Get style name
            $styleName = java_values($style->getName());

            //Process paragraph style
            $styleClass = $this->processStyle($styleClass, $styleXML);

            //Apply heading html tag and section numbering if exists
            if (strpos(strtolower($styleName), 'heading') !== false) {

                //Create headline container
                $container = $this->selectHeadlineContainer($styleName);

                // Add default p margins to headline
                $mTop = (int)(str_replace('px', '', $styleClass->getAttributeValue('margin-top'))) + 16;
                $mBottom = (int)(str_replace('px', '', $styleClass->getAttributeValue('margin-bottom'))) + 16;
                $styleClass->setAttribute('margin-top', $mTop . 'px');
                $styleClass->setAttribute('margin-bottom', $mBottom . 'px');
            }

        } else {

            // Normal style (margin bottom 0.14in)
            $styleClass->setAttribute("margin-bottom", '0.14in');
        }

        //Reset list level for custom lists
        if (!$numberingInfo) {
            $this->customListLevel = 0;
            $this->auxList = "";
        }

        // Set indentation
        $indentation = java_values($paragraph->getIndentationFirstLine());

        // Check if is list indentation
        $numberingInfo = ListHelper::paragraphExtractNumbering($paragraph);

        //Set indentation to paragraphs except for list items
        if ($indentation > 0 and !$numberingInfo) {
            $styleClass->setAttribute("text-indent", round($indentation / 11) . 'px');
        }

        // Get xml of this paragraph
        $paragraph_xml = java_values($paragraph->getCTP()->toString());
        //var_dump($paragraph_xml);

        //Check for book mark links to TOC
        if (strpos($paragraph_xml, '<w:bookmarkStart') !== false) {

            //Parse numbering bookmark
            $this->parseTocBookmarks($paragraph, $container);
        }

        //Check if is a table of contents
        if (strpos($paragraph_xml, '<w:fldChar w:fldCharType="begin"/>') !== false and $this->currentProcessedPart == "BODY") {
            $tocContainer = $this->parseToc($paragraph);
            $this->hasTOC = true;
            return $tocContainer;
        }

        $this->setLineSpace($paragraph, $styleClass);

        if((strpos($paragraph_xml, '<w:br w:type="page"/>') !== false) && self::$manualPageBreak ){
            return self::PAGE_BREAK;
        };

        // Iterate through paragraph characters
        if(count($charRuns)>0) {
            if ($this->checkLastRenderedPage($paragraph) && self::$defaultPageBreak) {
                $runContainer = $this->parseLastParagraphBreak($charRuns, $container);
                $container = $runContainer['firstPart'];
            }else{
                $container = $this->parseRuns($charRuns, $container);
                $runContainer = array();
            }
        }else{
            $container->addInnerText('<br/>');
            return $container;
        }

        // Get alignment
        $alignment = java_values($paragraph->getAlignment()->getValue());
        $justification = HWPFWrapper::getAlignment($alignment);

        // Set alignment on paragraph
        $styleClass->setAttribute("text-align", $justification);

        //Set class to html element
        $className = $this->mainStyleSheet->getClassName($styleClass);
        $container->setClass('textframe horizontal common_style1 ' . $className);

        // Add id attribute to container for this paragraph
        $container->setAttribute('id', 'div_' . $parIterator);

        // Wrap inside header tag if is a headlines
        if (in_array($container->getTagName(), $this->headlineElements)) {
            $headline = $container;
            $container = new HTMLElement(HTMLElement::HEADER);
            $exists = $styleClass->attributeExists('font-size');
            if (!$exists) {
                $styleClass->setAttribute("font-size", 'medium');
            }
            $container->addInnerElement($headline);
        }

        if(array_key_exists('lastPart',$runContainer)){
            $breakParagraph = array('firstPart'=>$container,'lastPart'=>$runContainer['lastPart']);
            return $breakParagraph;
        }

        // Return container
        return $container;

    }

    /**
     * @param $styleName
     * @return HTMLElement
     */
    private function selectHeadlineContainer($styleName)
    {

        switch ($styleName) {
            case 'heading 1':
                $headlineContainer = new HTMLElement(HTMLElement::H1);
                break;
            case 'heading 2':
                $headlineContainer = new HTMLElement(HTMLElement::H2);
                break;
            case 'heading 3':
                $headlineContainer = new HTMLElement(HTMLElement::H3);
                break;
            case 'heading 4':
                $headlineContainer = new HTMLElement(HTMLElement::H4);
                break;
            case 'heading 5':
                $headlineContainer = new HTMLElement(HTMLElement::H5);
                break;
            case 'heading 6':
                $headlineContainer = new HTMLElement(HTMLElement::H6);
                break;
            default:
                $headlineContainer = new HTMLElement(HTMLElement::H1);
                break;
        }

        return $headlineContainer;
    }

    /**
     * @param $paragraph
     * @return bool
     */
    private function checkLastRenderedPage($paragraph)
    {
        $paragraph_xml = java_values($paragraph->getCTP()->toString());
        $isLastPage = (strpos($paragraph_xml, '<w:lastRenderedPageBreak/>') !== false && $this->toc) ? true : false;
        if ($isLastPage) {
            $this->pageCounter++;
            $this->processPageBreak();
        }

//        if (strpos($paragraph_xml, '<w:lastRenderedPageBreak/>') !== false and $this->toc) {
//            $this->pageCounter++;
//            $this->processPageBreak();
//            $isLastPage = true;
//        } else {
//            $isLastPage = false;
//        }
        return $isLastPage;
    }

    /**
     * Process the last paragraph and set header
     * and footer on the body of the current page
     * @param $container
     */
    private function processPageBreak()
    {

        //Init footer element
        $footerHtml = new HTMLElement(HTMLElement::TEXT);

        //Process Header
//        if(is_object($this->headers[1])) {
//            $headerHtml =  $this->processHeaderFooter($this->headers[1]);
//        }else{
//            $headerHtml =  $this->processHeaderFooter($this->headers[0]);
//        }

        //Process footer
//        if(is_object($this->footers[0])){
//            $footer =  $this->processHeaderFooter($this->footers[0]);
//
//            //Add footer tag
//            $footerHtml = new HTMLElement(HTMLElement::FOOTER);
//            if(is_object($footer)) {
//                $footerHtml->setInnerElement($footer);
//            }
//        }

        //Set styles
        //$headerHtml->setAttribute('style','margin-top:100px;margin-bottom:30px');

        //Setting container
        //$this->auxContainer = array($footerHtml, $headerHtml);

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
                $path = XWPFToHTMLConverter::getDirectoryPath();
                $pictureContainer = new XWPFPicture($picture, $this->mainStyleSheet, $path);
                $container = $pictureContainer->processPicture();
                return $container;
            }
        }

        //Get Run xml
        $charXml = java_values($characterRun->getCTR()->ToString());

        //Check for section numbering
        $this->checkSectionNumbering($characterRun, $charXml);

        $charXml = str_replace('w:', 'w', $charXml);
        $xml = new SimpleXMLElement($charXml);

        //Get the value of hyperlink
        $link = $xml->xpath("wrPr/wrStyle");

        //Check if the hyperlink xml tag exists
        if (!empty($link)) {
            $linkValue = $link[0]['wval'];
        } else {
            $linkValue = "none";
        }

        //Check if is valid internet link
        if ($linkValue == 'InternetLink') {

            //Create empty A tag for the hyperlink and the text
            $container = new HTMLElement(HTMLElement::A);
            $container->setAttribute('href', java_values($characterRun->getText(0)));

        } else {
            /* In every other case, if we got here we do simple text parsing */
            // Create empty text element
            $container = new HTMLElement(HTMLElement::SPAN);
        }

        $styleClass = $this->processCharacterRunStyle($characterRun, $xml);

        if ($container->getTagName() != 'a') {
            $container = new HTMLElement(HTMLElement::SPAN);
        }

        $addNewLine = false;

        // Check for new line
        if (strlen($text) == 1 && (substr($text, -1, 1) == "\r" || ord(substr($text, -1, 1)) == HWPFWrapper::BEL_MARK)) {
            $addNewLine = true;
        }

        //escape text for xhtml
        $text = XhtmlEntityConverter::convertToNumericalEntities(htmlentities($text, ENT_COMPAT | ENT_XHTML));

        if ($addNewLine) {
            $text .= '<br />';
        }

        $boldContainer = $styleClass['containers']['bold'];
        $italicContainer = $styleClass['containers']['italic'];

        //TODO check why this fails with large documents
        //if($container->getTagName() == '') $container->setInnerElement($text);

        if ($boldContainer and $italicContainer) {

            //Set Bold and italic semantic tags
            $container->addInnerElement($boldContainer);
            $boldContainer->addInnerElement($italicContainer);
            $italicContainer->setInnerText($text);

        } elseif ($boldContainer) {

            //Set bold strong tag
            $container->addInnerElement($boldContainer);
            $boldContainer->setInnerText($text);

        } elseif ($italicContainer) {

            // Set italic em tag
            $container->addInnerElement($italicContainer);
            $italicContainer->setInnerText($text);
        } else {

            // Set inner text to span tag
            $container->setInnerText($text);
        }

        // Get and set class name on container
        $container->setClass($styleClass['style'] . ' textframe cke_focus');

        // Return container
        return $container;
    }

    /**
     * @param $characterRun
     * @param $xml
     * @return array
     */
    private function processCharacterRunStyle($characterRun, $xml)
    {

        // Get color
        $color = java_values($characterRun->getColor());
        if (is_null($color)) {
            $color = 'black';
        } else {
            $color = '#' . $color;
        }

        //Get background highlight color
        $runCharShadows = $xml->xpath("wrPr/wshd");
        if (!empty($runCharShadows)) {
            $backgroundColor = $runCharShadows[0]['wfill'];
        } else {
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

        // Get underline
        $underlined_type = java_values($characterRun->getUnderline()->getValue());

        //Default underline set to none
        if (!is_int($underlined_type)) {
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

        //Initiate semantic containers
        $boldContainer = null;
        $italicContainer = null;

        // Set style attributes
        if ($color != 'black' and $color != '#000000') {
            $styleClass->setAttribute("color", $color);
        }

        if ($fontWeight != 'normal') {
            $boldContainer = new HTMLElement(HTMLElement::STRONG);
        }

        if ($fontStyle != 'normal') {
            $styleClass->setAttribute("font-style", $fontStyle);
        }

        if ($fontSize) {
            $styleClass->setAttribute("font-size", (string)$fontSize . "pt");
        }

        if ($fontFamily) {
            $styleFont = HWPFWrapper::getFontFamily($fontFamily);
            $styleClass->setAttribute("font-family", $styleFont);
        }

        if ($underlined != 'none') {
            $styleClass->setAttribute("text-decoration", $underlined);
        }

        if (!is_null($backgroundColor)) {
            $styleClass->setAttribute("background-color", "#" . $backgroundColor->__toString());
        }

        $className = $this->mainStyleSheet->getClassName($styleClass);

        $runStyle = array("style" => $className, "containers" => array("bold" => $boldContainer, "italic" => $italicContainer));

        return $runStyle;

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
        $sz = ($sz) ? floor(((string)$sz[0]['wval']) / 2) : '';

        // Get first line indentation
        $ident = $xml->xpath('*/wind');
        if ($ident) {
            $identNum = round(((string)$ident[0]['wfirstLine']) / 11) . 'px';
            if ($identNum == '0px') {
                $identNum = round(((string)$ident[0]['wleft']) / 11) . 'px';
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
        $styleClass->setAttribute("margin-top", round(((string)$spacingBefore) / 11) . 'px');
        $styleClass->setAttribute("margin-bottom", round(((string)$spacingAfter) / 11) . 'px');

        // Set font styles
        $styleClass->setAttribute("font-family", $font);
        if ($color) $styleClass->setAttribute("color", '#' . $color);
        if (@$wshd) $styleClass->setAttribute("background-color", '#' . $wshd);
        if ($sz > '') $styleClass->setAttribute("font-size", $sz . 'pt');
        if ($weight != 'normal') $styleClass->setAttribute("font-weight", $weight);
        if ($italic) $styleClass->setAttribute("font-style", 'italic');
        if ($allcaps) $styleClass->setAttribute("text-transform", 'uppercase');
        if ($ident > '') $styleClass->setAttribute("text-indent", $ident);

        // Return styled class
        return $styleClass;
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
     * Get list indentation from the list
     * @param $numXml
     * @param $numberingInfo
     * @return int
     */
    private function calculateListIndentation($numXml, $numberingInfo)
    {

        $ipind = $numXml->xpath('wlvl/wpPr/wind');

        //Get indentation values
        try {
            $hanging = $ipind[$numberingInfo['lvl']]['whanging'];
            $wleft = $ipind[$numberingInfo['lvl']]['wleft'];
        } catch (Exception $exception) {
            //Setting default
            $hanging = 1;
            $wleft = 1;
            var_dump($exception);
        }

        //Calculate list indentation
        $listIndentation = ((intval($wleft) / intval($hanging)) / 4) * 100;

        return intval($listIndentation);

    }

    /**
     * Parse the table of contents
     * @param $paragraph
     * @return HTMLElement
     */
    public function parseToc($paragraph)
    {
        $this->tocLevel++;
        $this->toc = true;

        //Create row
        $toc_row = new HTMLElement(HTMLElement::TR);

        //Get characters
        $runs = java_values($paragraph->getRuns());

        //Get all the HTML characters in the paragraph
        $tocRow = array();

        for ($i = 0; $i < count($runs); $i++) {
            $character = $runs[$i];
            $run = new XWPFRun($character, $this->mainStyleSheet);
            $runHTMLElement = $run->parseRun();
            //$runHTMLElement = $this->parseCharacterRun($character);
            $tocRow[$i] = $runHTMLElement;
        }

        $tocNum = $tocRow[0];
        $a = 1;

        //Find last element
        while ($tocRow[count($runs) - $a]->getInnerText() == "<br />") {
            $a++;
        }

        $a++;
        $desLevel = count($runs) - $a;
        if ($desLevel >= 0) {
            $tocPage = $tocRow[$desLevel];
        } else {
            $tocPage = $tocRow[0];
        }

        //Add all the elements of description
        $toc_cell = new HTMLElement(HTMLElement::TD);
        $toc_link = new HTMLElement(HTMLElement::A);

        for ($i = 1; $i < $desLevel; $i++) {
            $testText = $tocRow[$i]->getInnerText();
            $valid = strpos($testText, "<");
            if ($valid == false) {
                if (is_a($tocRow[$i], 'HTMLElement')) {
                    if (!empty(trim($tocRow[$i]->getInnerText()))) {
                        $toc_link->addInnerElement($tocRow[$i]);
                    }
                }
            }
        }

        //Apply styles for toc num and toc page number
        $tocNum->setAttribute('style', 'margin-right:10px');
        $toc_link->setAttribute('style', 'margin-right:10px');
        $toc_link->setAttribute('style', 'text-decoration:inherit');
        $tocPage->setAttribute('style', 'float:right');

        //Set link direction
        $link = '#toc'.$this->headlineId;
        $toc_link->setAttribute('href', $link);
        $this->headlineId++;

        //Add elements to toc cell
        $toc_cell->addInnerElement($tocNum);
        $toc_cell->addInnerElement($toc_link);
        $toc_cell->addInnerElement($tocPage);

        //Get table of contents
        $needles = array('<strong >','</strong>');
        $textList = $toc_link->getLastElement();

        if(is_a($textList, 'HTMLElement')) {
            $text = trim(str_replace($needles, "", $textList->getInnerText() ));
        } else {
            $text = trim(str_replace($needles, "", $textList));
        }
        $textNum = trim(str_replace($needles,"",$tocNum->getInnerText()));
        $textPage = trim(str_replace($needles,"",$tocPage->getInnerText()));
        $this->tableOfContents[] = array('num' => $textNum, 'description' => $text, 'page' => $textPage);

        //Add cell to toc row
        $toc_row->addInnerElement($toc_cell);

        //Add row to table or create table if it's the first toc entry
        if ($this->tocLevel == 1) {
            $container = new HTMLElement(HTMLElement::NAV);
            $tableContainer = new HTMLElement(HTMLElement::TABLE);
            $tableContainer->addInnerElement($toc_row);
            $container->addInnerElement($tableContainer);
        } else {
            $container = $toc_row;
        }

        return $container;
    }

    public function getTableOfContents(){
        return $this->tableOfContents;
    }

    public function getHeadLineList(){
        return $this->headLineList;
    }

    private function printBreakPage($container){
        $pageBreakBlock = new HTMLElement(HTMLElement::P);
        $pageBreakBlock->setInnerText(
            "-----------------------------------------
            ------------------------------------------
            ------------------------------------------
            ----------------PageBreak-----------------
            ------------------------------------------
            ----------------------------------
            -----------------------------------------");
        $container->addInnerElement($pageBreakBlock);
    }

    private function pageBreak($container){

        $this->htmlDocument->setBody($container);

        // Create new page
        $this->createPage();

        // Create new HTML element and set its class name
        $container = new HTMLElement(HTMLElement::DIV);
        $styleClass = new StyleClass();
        $className = $this->mainStyleSheet->getClassName($styleClass);
        $container->setClass($className);
        $this->printBreakPage($container);
        return $container;

    }

    /**
     * @param $element
     * @param $paragraphHTMLElement
     * @param $container
     * @param $key
     * @return HTMLElement
     */
    private function processContainer($element, $paragraphHTMLElement, $container, $key)
    {
        if(is_a($paragraphHTMLElement,'HTMLElement')) {
            $tagName = $paragraphHTMLElement->getTagName();
        }

        if(is_array($paragraphHTMLElement)){
            if(array_key_exists('lastPart',$paragraphHTMLElement)){
                $container->addInnerElement($paragraphHTMLElement['firstPart']);
                $lastParagraph = $paragraphHTMLElement['lastPart'];
                $paragraphHTMLElement = self::PAGE_BREAK;
            }
        }

        // Check manual page break
//        $elementType = $this->getElementType($element);
//        if (($elementType == "PARAGRAPH") && self::$manualPageBreak ) {
//            $paragraph = java_cast($element, 'org.apache.poi.xwpf.usermodel.XWPFParagraph');
//            $paragraphXML = java_values($paragraph->getCTP()->ToString());
//            $pageBreakMarkup = array('manual' => '<w:br w:type="page"/>', 'byPage' => '<w:lastRenderedPageBreak/>');
//            $isManualPageBreak = (strpos($paragraphXML, $pageBreakMarkup['manual']) !== false);
//            if ($isManualPageBreak) {
//                $lastParagraph = $paragraphHTMLElement;
//                $paragraphHTMLElement = self::PAGE_BREAK;
//            }
//        }

        // Check if this is a page break
        if (is_int($paragraphHTMLElement) && $paragraphHTMLElement == self::PAGE_BREAK) {
            $container = $this->pageBreak($container);
            if(isset($lastParagraph) && is_a($lastParagraph,'HTMLElement')) {
                $container->addInnerElement($lastParagraph);
                }
        } elseif (is_int($paragraphHTMLElement) && $paragraphHTMLElement == self::CUSTOMLIST) {
            $this->setCustomList($container, $key);
        } elseif (isset($tagName) && $tagName == "tr") {

            //Add Toc to the container
            if ($this->tocLevel <= 1) {
                $nav = new HTMLElement(HTMLElement::NAV);
                $table = new HTMLElement(HTMLElement::TABLE);
                $table->addInnerElement($paragraphHTMLElement);
                $nav->addInnerElement($table);
                $container->addInnerElement($nav);
            } else {
                $lastTable = $container->getLastElement()->getLastElement();
                if (is_object($lastTable) and is_a($lastTable, 'HTMLElement')) {
                    $lastTable->addInnerElement($paragraphHTMLElement);
                }
            }

        } elseif (isset($tagName) && $tagName == "header") {
            $headline = $paragraphHTMLElement->getLastElement();
            $container = $this->parseHeaderParagraph($headline,$container);
            $container->addInnerElement($paragraphHTMLElement);
        } else {
            $container->addInnerElement($paragraphHTMLElement);
        }

        return $container;
    }

    /**
     * @param $headline
     * @param $container
     * @return HTMLElement
     */
    private function parseHeaderParagraph($headline,$container){
        if (is_object($headline)) {
            $tagHeadline = $headline->getTagName();
            if (!is_int($headline->getInnerText())) {
                $this->headLineList[] = array(
                    'tag' => $tagHeadline,
                    'content' => trim($headline->getLastElement()->getInnerText())
                );
            }
            $breakAtH1 = ($tagHeadline == "h1" && self::$breakH1);
            $breakAtH2 = ($tagHeadline == "h2" && self::$breakH2);
            if ($breakAtH1 || $breakAtH2) {
                if (!is_null($headline->getInnerText())) {
                    $headline->setAttribute('id', "toc" . $this->headlineIdSection);
                    $this->headlineIdSection++;
                    $container = $this->pageBreak($container);
                }
            }
        }
        return $container;
    }

    /**
     * @param $paragraph
     * @param $styleClass
     */
    private function setLineSpace($paragraph, $styleClass)
    {
        // Check if there is a line spacing for the paragraph
        $PStyleXml = java_values($paragraph->getCTP()->toString());

        $paragraphXml = str_replace('w:', 'w', $PStyleXml);
        $xml = new SimpleXMLElement($paragraphXml);
        $PSpacingStyle = $xml->xpath('*/wspacing');

        if ($PSpacingStyle) {

            //Get the line space value
            $PSpacingLineValue = ((string)$PSpacingStyle[0]['wline']);

            if ($PSpacingLineValue == '240') {
                $lineValue = '100';
            } else {
                $tmpSpace = ((int)$PSpacingLineValue - 240);
                $addSpace = $tmpSpace + 100;
                $lineValue = ((string)$addSpace);
            }

            //Set paragraph line spacing
            $styleClass->setAttribute("line-height", $lineValue . "%");
        }
    }

    /**
     * @param $charRuns
     * @param $container
     */
    private function parseRuns($charRuns, $container)
    {
        $prevCharRunHTMLElement = null;
        foreach($charRuns as $charRun) {
            $characterRun = $charRun;
            $pictures = java_values($characterRun->getEmbeddedPictures());
            $run = new XWPFRun($characterRun, $this->mainStyleSheet);
            $charRunHTMLElement = $run->parseRun();

            // Check if this is a picture
            if (count($pictures) > 0) {
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            } else if (@isset($prevCharRunHTMLElement) && $charRunHTMLElement->getClass() == $prevCharRunHTMLElement->getClass()) {
                $container->getLastElement()->addInnerText($charRunHTMLElement->getInnerText());
            } else {
                $container->addInnerElement($charRunHTMLElement);
                $prevCharRunHTMLElement = clone $charRunHTMLElement;
            }
        }
        return $container;
    }

    /**
     * @param $charRuns
     * @param $container
     * @return array|null
     */
    private function parseLastParagraphBreak($charRuns, $container)
    {
        $runHTMLElement = HWPFWrapper::selectBreakPageContainer($container);
        $prevCharRunHTMLElement = null;
        $numberOfRuns = count($charRuns);
        $runContainer = null;

        foreach ($charRuns as $key => $charRun) {
            $run = new XWPFRun($charRun, $this->mainStyleSheet);
            $charRunHTMLElement = $run->parseRun();
            $isDefaultPageBreak = ($run->hasDefaultPageBreak()) ? true : false;

            if ($isDefaultPageBreak && ($numberOfRuns > 0)) {
                while (($key < $numberOfRuns) ) {
                    $nextRun = $key;
                    $lastRuns = new XWPFRun($charRuns[$nextRun], $this->mainStyleSheet);
                    $lastRunHTML = $lastRuns->parseRun();
                    $runHTMLElement->addInnerElement($lastRunHTML);
                    $runContainer = array('firstPart'=>$container,'lastPart'=>$runHTMLElement);
                    $key++;
                }
                break;
            }else {
                $container->addInnerElement($charRunHTMLElement);
                $runContainer = array('firstPart' => $container);
            }
        }

        return $runContainer;
    }

    /**
     * @param $characterRun
     * @param $charXml
     */
    private function checkSectionNumbering($characterRun, $charXml)
    {
        if (strpos($charXml, '<w:instrText xml:space="preserve">PAGEREF') !== false) {

            //Trim the xml REF
            $start = strpos($charXml, '_Toc');
            $end = strpos($charXml, '\h');
            $lenght = $end - $start;

            $tocRef = trim(substr($charXml, $start, $lenght));

            //Calculate index value
            $textParagraph = java_values($characterRun->getParagraph()->getText());
            $indexValue = str_split(substr($textParagraph, 0, 3));
            $value = '';
            foreach ($indexValue as $char) {
                if (is_numeric($char) and !empty($char) or $char == ".") $value .= $char;
            }

            if (!$value == "") {
                $this->tocNumbering[$tocRef] = $value;
            }
        }
    }

}
