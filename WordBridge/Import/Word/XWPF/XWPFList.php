<?php

//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFParagraph.php';

/**
 * Created by PhpStorm.
 * User: root
 * Date: 11/2/15
 * Time: 12:52 PM
 */
class XWPFList
{
    /**
     * List level state
     */
    private $listLevelState;

    /**
     * List num id
     */
    private $listNumId;
    private $paragraph;
    private $mainStyleSheet;
    private $listItemIterator;


    function __construct($paragraph, $stylesheet){
        $this->listLevelState = 0;
        $this->listNumId = 1;
        $this->paragraph = $paragraph;
        $this->listItemIterator = null;
        $numberingObject = java_values($this->paragraph->getDocument()->getNumbering());
        $this->mainStyleSheet = $stylesheet;

        //Cast to XWPFNumbering
        $this->numbering = java_cast($numberingObject, 'org.apache.poi.xwpf.usermodel.XWPFNumbering');
    }

    private function setMainStyleSheet($stylesheet){
        $this->mainStyleSheet = $stylesheet;
    }

    private function setListNumId($listNumId){
        $this->listNumId = $listNumId;
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
        $XWPFparagraph = new XWPFParagraph($paragraph, $this->mainStyleSheet);
        $paragraphContainer = $XWPFparagraph->parseParagraph();

        if(is_object($paragraphContainer)){
            $paragraphContainer->setAttribute('style', 'text-indent:0px');
            $this->listItemIterator++;
            $listItem->setInnerElement($paragraphContainer);
        }elseif(!is_object($paragraphContainer)){
            var_dump(java_values($paragraph->getText()));
        }
        return $listItem;
    }

    /**
     * Process a list of the document
     * @param $numberingInfo
     * @param $container
     * @param $paragraph
     * @param $key
     */
    public function processList($numberingInfo, $container, $paragraph, $key, $listNumId){

        //Extract List Properties
        $abstractNum = $this->getAbstractNum($numberingInfo);
        $listProperties = $this->extractListProperties($abstractNum, $numberingInfo);

        //If this is set to true a new list container should be created
        $newListActivator = false;
        echo $this->listNumId;
        echo "-------------------";
        echo $numberingInfo['numId'];
        echo "<br/>";

        if($listNumId != $numberingInfo['numId'] ){
            echo "Activado "."<br/>";
            $newListActivator = true;
        }

        $levelCount = $numberingInfo['lvl'];

        //Check if the list level state has change
        if($this->listLevelState != $levelCount){

            //Create new list
            $newList = new HTMLElement(HTMLElement::UL);
            $newList->setAttribute('style','list-style-type:'.$listProperties['type']);

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

//        echo $this->listLevelState;
//        echo "-------------------";
//        echo $this->listNumId;
//        echo "<br/>";

        // Parse list item
        $listItemHTMLElement = $this->parseList($paragraph);
        $listItemHTMLElement->setId($key);

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
        if( $containerLastElement == "ul" ){

            //Initialize last container
            $lastContainer = $container->getLastElement();

                //Find current list element
                for ($i = 0; $i < $this->listLevelState; $i++) {
                    if(is_object($lastContainer)) {
                        $lastContainer = $lastContainer->getLastElement();
                    }
                }

            //Check if the container is fill
            if(is_object($lastContainer)) {
                $listItemHTMLElement->setId($key);
                $lastContainer->addInnerElement($listItemHTMLElement);
            }

        }elseif($containerLastElement == 'innerul' or $containerLastElement != "ul") {

            //Create a new list container
            $listContainer = new HTMLElement(HTMLElement::UL);
            $listContainer->setId($key);

            //Set list type style
            $listContainer->setAttribute('style','list-style-type:'.$listProperties['type'].';'.'margin-left:'.$listProperties['indentation'].'px' );

            //Fill the list with the first li item
            $firstItemId = $key+1;
            $listItemHTMLElement->setId($firstItemId);
            $listContainer->addInnerElement($listItemHTMLElement);

            //Set the list to the container
            $container->addInnerElement($listContainer);
        }
    }

    /**
     * @param $numberingInfo
     * @return mixed
     */
    private function getAbstractNum($numberingInfo){

        //Cast to XWPFNumbering
        $numbering = $this->numbering;

        //Create java big integer object
        $numId = new Java('java.math.BigInteger',$numberingInfo['numId']);

        $abstractNumId = java_values($numbering->getAbstractNumId($numId));

        if(!is_object($abstractNumId)){
            return null;
        }

        //get abstract numbering
        $absNumId = java_values($abstractNumId->toString());

        //Create java big integer object
        $finalId = new Java('java.math.BigInteger', $absNumId);

        //get abstract numbering
        $abstractNum = java_values($numbering->getAbstractNum($finalId));

        return $abstractNum;

    }

    /**
     * @param $abstractNum
     * @param $numberingInfo
     * @return array
     */
    private function extractListProperties($abstractNum, $numberingInfo){

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
            $listProperties = array('type' => $listTypeStyle, 'indentation' => $listIndentation);

        }else{
            //Default settings
            $listProperties = array('type' => '', 'indentation' => '');
        }

        return $listProperties;
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

}