<?php

App::import('Vendor', 'Chaucer/Common/ProgressUpdater');

App::import('Import/Word', 'HTMLDocument');
App::import('Import/Word', 'HTMLElement');
App::import('Import/Word', 'HWPFWrapper');
App::import('Import/Word', 'StyleSheet');

/**
* HWPFToHTMLConverter is a class that takes a doc or docx file as input and converts it to a Simple HTMLDocument, keeping stylesheets, images e.g.
* The main idea is to reuse Apache POI parser that is integrated with JavaBridge web app on a tomcat server.
* @author Avetis Zakharyan
*/
class HWPFToHTMLConverter {

	/**
	* instance of Simple HTML document, that converter will be filling with data
	*/
	private $htmlDocument;

	/**
	* string, path to the file that needs to be parsed
	*/
	private $parsingFile;

	/**
	* java file system stream to work with
	*/
	private $javaFileStream;

	/**
	* HWPF document from parsed word file
	*/
	private $document;

	/**
	* HWPF range object to work with 
	*/
	private $range;

	private $numberingState;

	private $listInProgress = false;

	private $fieldsDocumentPart;

    private $images;

	/**
	* Constructor that needs to get a Java Bridge classes
	*/
	function __construct() {

        $bridge_host = JFactory::getApplication()->getCfg('word_converter_bridge_host');
        if($bridge_host == null || $bridge_host == '') $bridge_host = 'http://word.chaucercloud.com:8080/';

        $url = "{$bridge_host}WordBridge/java/Java.inc";
        $local = "tmp/Java.inc";
        $remote_contents = file_get_contents($url); file_put_contents($local, $remote_contents);
        define ("JAVA_SERVLET", "/WordBridge/servlet.phpjavabridge");
        if(substr($bridge_host, -1) == '/') $bridge_host = substr($bridge_host, 0, -1);
        if(substr($bridge_host, 0, 7) == 'http://') $bridge_host = substr($bridge_host, 7);
        define ("JAVA_HOSTS", $bridge_host);
        include($local);
	}



	/**
	*does no parsing, but sets the parsing file, prepares java file stream and document file
	*/

	public function setDocFileToParse($path) {
		$this->parsingFile = $path;

        $this->inputStream = $this->createJavaInputStream($path);

        $this->document = new Java("org.apache.poi.hwpf.HWPFDocument", $this->inputStream);

		$this->fieldsDocumentPart = new Java("org.apache.poi.hwpf.model.FieldsDocumentPart");

		$this->numberingState = array();
	}

    /**
     * @param $path
     * @return Java object Input Stream
     */
    public function createJavaInputStream($path) {

        $handle = fopen($path, "r");
        $contents = fread($handle, filesize($path));
        fclose($handle);

        $out = new Java("java.io.ByteArrayOutputStream");

        $arr = array();
        $strlen = strlen($contents);
        for ($i = 0; $i < $strlen; $i++ ) {
            $val = ord(substr($contents, $i, 1));
            if ($val >= 128) {
                $val = ($val) - 256;
            }
            $arr[] = $val;
        }

        $out->write($arr);

        $value = new Java("java.io.ByteArrayInputStream", $out->toByteArray());

        return $value;
    }

	/**
	* takes the previously opnened file and converts it to simple HTMLDocument object.
	*/
	public function convertToHTML() {

		$this->htmlDocument = new HTMLDocument();

		$this->parseAdditionalInformation();
		
		$this->htmlDocument->styleSheet = new StyleSheet();

		$this->range = $this->document->getRange();

		// create empty div to put all sections into, that will be a parent container for entire document
		$container = new HTMLElement(HTMLElement::DIV);

		// Usually word document has just one section

		$numSections = java_values($this->range->numSections());
		for ($iterator = 0; $iterator < $numSections; $iterator++) {
			$section = $this->range->getSection( $iterator );
			$sectionHTMLElement = $this->parseSection($section);

			$container->addInnerElement($sectionHTMLElement);
		}
		
		$this->htmlDocument->setBody($container);
	}

	/**
	* parses a section element of HWPF document
	* @param HWPFSection $section
	* @return HTMLElement
	*/
	private function parseSection($section) {
		
		$container = new HTMLElement(HTMLElement::DIV);

		$styleClass = new StyleClass();

		$left = java_values($section->getMarginLeft())/HWPFWrapper::TWIPS_PER_INCH;
		$right = java_values($section->getMarginRight())/HWPFWrapper::TWIPS_PER_INCH;

		// Getting section margins and applying to our stylesheet
        /*
	   	$styleClass->setAttribute("margin-top", java_values($section->getMarginTop())/HWPFWrapper::TWIPS_PER_INCH.'in');
	   	$styleClass->setAttribute("margin-left", $left.'in');
	   	$styleClass->setAttribute("margin-right", $right.'in');
	   	$styleClass->setAttribute("margin-bottom", java_values($section->getMarginBottom())/HWPFWrapper::TWIPS_PER_INCH.'in');
        */

		$widthInInches = java_values($section->getPageWidth())/HWPFWrapper::TWIPS_PER_INCH;
		$actualWidth = $widthInInches - $left-$right;

		$pageWidth = $actualWidth.'in';

	  	$styleClass->setAttribute("width", $pageWidth);

 		$className = $this->htmlDocument->styleSheet->getClassName($styleClass);

 		$container->setClass($className);

 		$ulElement = null;

 		// Every Word document section consists of paragraphs, so we iterate through them, and parse them one by one

		$numParagraphs = java_values($this->range->numParagraphs());

		for ($i=0; $i < $numParagraphs; $i++) { 
			$paragraph = $this->range->getParagraph($i);

			$label = null;

			if ( java_values($paragraph->isInList()) ) {
				try { 
					$hwpfList = $paragraph->getList();
					$label = HWPFWrapper::getBulletText($this->numberingState, $hwpfList, $paragraph->getIlvl());					
				} catch(Exception $e) {

				}
			}

			$prevListStatus = $this->listInProgress;
			
			$paragraphHTMLElement = $this->parseParagraph($paragraph, $label);

			if($prevListStatus == false && $this->listInProgress == false) {
				$container->addInnerElement($paragraphHTMLElement);
			}	else {
				if($prevListStatus == false && $this->listInProgress == true) {
					$ulElement = new HTMLElement(HTMLElement::UL);
					$ulElement->addInnerElement($paragraphHTMLElement);
				}
				if($prevListStatus == true && $this->listInProgress == true) {
					$ulElement->addInnerElement($paragraphHTMLElement);
				}
				if($prevListStatus == true && $this->listInProgress == false) {
					$container->addInnerElement($ulElement);
					$container->addInnerElement($paragraphHTMLElement);
				}	
			}		
			
			
		}

		return $container;
	}

	/**
	* parses a paragraph element of HWPF document
	* @param HWPFSection $paragraph
	* @return HTMLElement
	*/
	private function parseParagraph($paragraph, $label = null) {
		
		$container = new HTMLElement(HTMLElement::DIV);

		$charRuns = $paragraph->numCharacterRuns();

		$processingField = false;


		for($j =0; $j < java_values($charRuns); $j++) {

			// character run is a simple part of text (of paragraph), every character or word of character run shares the same style properties within intire character run.

 			$characterRun = $paragraph->getCharacterRun($j);

 			$quickCheckText = java_values($characterRun->text());
 			
 			if(@ord($quickCheckText[0]) == HWPFWrapper::FIELD_BEGIN_MARK) {
 				$processingField = true;

 				$aliveField = $this->document->getFields()->getFieldByStartOffset($this->fieldsDocumentPart->MAIN, $characterRun->getStartOffset());
 				if(java_values($aliveField) != null) {
 					$link = $this->processField( $aliveField, $paragraph, $j );
 					$container->addInnerElement($link);
 				} else {
 					// Dead Field
 				}
 			}

 			if(@ord($quickCheckText[0]) == HWPFWrapper::FIELD_END_MARK) {
 				$processingField = false;
 			}

 			if(!$processingField) {
	 			$charRunHTMLElement = $this->parseCharacterRun($characterRun);
		
				$container->addInnerElement($charRunHTMLElement);
			}
 		}

 		if($label != null) {
			if(substr($label, -1) == "\t") {
				$li = new HTMLElement(HTMLElement::LI);
				$li->addInnerElement($container);
				
				$this->listInProgress = true;

				return $li;
			}
		} else {
			$this->listInProgress = false;
		}

		
	
		$styleClass = new StyleClass();

        if ( java_values($paragraph->pageBreakBefore()) ) {
            $styleClass->setAttribute( "break-before", "page" );
        }
		
		$styleClass->setAttribute("hyphenate", ( java_values($paragraph->isAutoHyphenated()) ? "auto" : "none" ) );      
        if (java_values($paragraph->keepOnPage()) ) {            
			$styleClass->setAttribute("keep-together.within-page", "always" );
        }

        if ( java_values($paragraph->keepWithNext()) ) {
            $styleClass->setAttribute("keep-with-next.within-page", "always" );
        }

        $justification = HWPFWrapper::getJustification(java_values($paragraph->getJustification()));

        $styleClass->setAttribute("text-align", $justification);
 		
 		$className = $this->htmlDocument->styleSheet->getClassName($styleClass);

 		$container->setClass($className);

 		return $container;
	}

	private function processField($field, $paragraph, $index) {

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
			default:
				break;
		}
	}

	/**
	* parses a characterRun element of HWPF document
	* @param HWPFSection $characterRun
	* @return HTMLElement
	*/
	private function parseCharacterRun($characterRun) {

		$container = null;

		// Even non text elements will have a text property
		$text = nl2br(java_values($characterRun->text())); 

		// in case the picture table contains that particular character run, then it is a picture, which needs to be parced seperately
		if(java_values($this->document->getPicturesTable()->hasPicture($characterRun))) {
			$isPicture = (ord(substr($text, 0, 1)) == HWPFWrapper::SPECCHAR_IMAGE);
			$picture = $this->document->getPicturesTable()->extractPicture($characterRun, true);

			if(java_values($picture) != null) {
				// if it is proven to be picture, then let's parse it
				$container = $this->processPicture($picture, $isPicture);
				return $container;
			} else {
				//TODO: what to do here?
			}
			
		}


		// Some other types of character runs, can be a drawn object, hyperlink, note e.g. 
		if ( java_values($characterRun->isSpecialCharacter()) ) {

			// TODO: add all type parsings
		 	if(ord(substr($text, 0, 1)) == HWPFWrapper::SPECCHAR_DRAWN_OBJECT) {
		 		
		 	} 

		} 

		// in every other case, if we got here we do simple text parsing

		//create empty span element		
		$container = new HTMLElement(HTMLElement::SPAN);

		$color = HWPFWrapper::getColor(java_values($characterRun->getColor()));

		$isItalic = java_values($characterRun->isItalic()); 
		if($isItalic) {
 			$fontStyle = 'italic';
 		} else {
 			$fontStyle = 'normal';
 		}

 		$isBold = java_values($characterRun->isBold()); 
		if($isBold) {
 			$fontWeight = 'bold';
 		} else {
 			$fontWeight = 'normal';
 		}

 		$fontSize = java_values($characterRun->getFontSize())/2; 

 		$fontFamily = java_values($characterRun->getFontName()); 

 		// create empty class, and attach it to span element

 		$styleClass = new StyleClass();
 		$styleClass->setAttribute("color", $color);
 		$styleClass->setAttribute("font-weight", $fontWeight);
 		$styleClass->setAttribute("font-style", $fontStyle);
 		$styleClass->setAttribute("font-size", $fontSize."pt");
 		$styleClass->setAttribute("font-family", $fontFamily);

 		$className = $this->htmlDocument->styleSheet->getClassName($styleClass);

 		$container->setClass($className);

 		if(strlen($text) == 1 && (substr($text, -1, 1) == "\r" || ord(substr($text, -1, 1)) == HWPFWrapper::BEL_MARK)) {
 			$text = $text.'<br />';
 		}

 		// set span inner text
 		$container->setInnerText($text);
	 	

 		return $container;
	}

	/**
	* parses a picture element of HWPF document
	* @param HWPFSection $picture
	* @param isPicture - if false, then it is an external picture URL that needs to be treated like external picture
	* @return HTMLElement
	*/
	private function processPicture($picture, $isPicture) {
		// take aspect ratio if picture
		$aspectRatioX = java_values($picture->getHorizontalScalingFactor());
        $aspectRatioY = java_values($picture->getVerticalScalingFactor());

        //not sure what this means yet
        $dxaGoal = java_values($picture->getDxaGoal());
        $dyaGoal = java_values($picture->getDyaGoal());

        // calculate the used image width and height, we can either save picture file in that size, or save them in buigger size, but use size on img element
        $imageWidth = $aspectRatioX > 0 ?  $dxaGoal
                    * $aspectRatioX / 1000 / HWPFWrapper::TWIPS_PER_INCH
                    :  $dxaGoal / HWPFWrapper::TWIPS_PER_INCH;
        $imageHeight = $aspectRatioY > 0 ? $dyaGoal
                    * $aspectRatioY / 1000 / HWPFWrapper::TWIPS_PER_INCH
                    : $dyaGoal / HWPFWrapper::TWIPS_PER_INCH;

        //taking main picture data and saving it to the file system (this part needs to be enhanced)
        $picContent = java_values($picture->getContent());
        $picType = java_values($picture->suggestPictureType()->getMime());
        $picName = java_values($picture->suggestFullFileName());

        $path = "tmp/$picName";
        $fp = fopen( $path, 'w');
		fwrite($fp,  $picContent);
		fclose($fp);
        $this->images[$picName] = $picName;

		//creating img element with path to newly created picture
		$container = new HTMLElement(HTMLElement::IMG);
		$container->setAttribute("src",  '../images/'.$picName);
		//$container->setAttribute("width",  $imageWidth.'in');
		//$container->setAttribute("height",   $imageHeight.'in');

        return $container;
	}

    public function getImages() {
        return $this->images;
    }

	/**
	* parses additional infomration of doc file like, title, keywords, author e.g.
	*/
	private function parseAdditionalInformation() {
		
		// retreive summary information object to work with
		$summaryInformation = $this->document->getSummaryInformation();

		if(java_values($summaryInformation) == null) return;

		// get document title
		$title = java_values($summaryInformation->getTitle());

		// TODO: add author and keywords

		$this->htmlDocument->setTitle($title);
	}


	/**
	* Get HTMLDocument
	* @return HTMLDocument
	*/
	public function getHTMLDocument() {
		return $this->htmlDocument;
	}
}

?>