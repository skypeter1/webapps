<?php

//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFParagraph.php';
//include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/Helpers/ListHelper.php';

/**
 * Created by PhpStorm.
 * User: root
 * Date: 11/2/15
 * Time: 12:52 PM
 */
class XWPFList
{
    private $paragraph;
    private $mainStyleSheet;
    private $id;
    private $listItemIterator;
    private $tocNumber;
    private $firstLevel;
    private $secondLevel;
    private $thirdLevel;
    private $headlineList;
    private $currentNumID;
    private $level;
    private $sectionNumbering;
    const UL = 'ul';
    const INNER_UL = 'innerul';

    /**
     * @param $paragraph
     * @param $stylesheet
     * @param $key
     */
    function __construct($paragraph, $stylesheet, $key)
    {
        $this->paragraph = $paragraph;
        $numberingObject = java_values($this->paragraph->getDocument()->getNumbering());
        $this->mainStyleSheet = $stylesheet;
        $this->id = $key;
        $this->numbering = java_cast($numberingObject, 'org.apache.poi.xwpf.usermodel.XWPFNumbering');
        $this->currentNumID = $this->getNumId();
    }

    /**
     * @return mixed
     */
    private function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     * Possible values: bullet or decimal
     */
    private function getListFormat(){
        $listType = java_values($this->paragraph->getNumFmt());
        return $listType;
    }

    public function getNumLvl(){
        $numLvl = java_values($this->paragraph->getNumIlvl());
        return $numLvl;
    }

    public function getNumLevelText(){
        $numLevelText = java_values($this->paragraph->getNumLevelText());
        return $numLevelText;
    }

    public function getNumId(){
        $numId = java_values($this->paragraph->getNumId());
        return $numId;
    }

    public function setLevels(&$first,&$second,&$third,&$level){
        $this->firstLevel = &$first;
        $this->secondLevel = &$second;
        $this->thirdLevel = &$third;
        $this->level = &$level;
    }

    /**
     * @param $listItemIterator
     */
    public function setLevel(&$listItemIterator){
        $this->listItemIterator = &$listItemIterator;
    }

    /**
     * @param $listItemIterator
     */
    public function setListItemLevel(&$listItemIterator){
        $this->listItemIterator = &$listItemIterator;
    }

    /**
     * @param $headlineList
     * @internal param $listItemIterator
     */
    public function setHeadlineList(&$headlineList){
        $this->headlineList = &$headlineList;
    }

    /**
     * @param $tocNumber
     * @internal param $listItemIterator
     */
    public function setTocNumber(&$tocNumber){
        $this->tocNumber = &$tocNumber;
    }

    public function setSectionNumbering($sectionNumbering){
        $this->sectionNumbering = $sectionNumbering;
    }

    /**
     * Parse a list element of the document
     * @param $paragraph
     * @return mixed
     */
    private function parseListItem($paragraph)
    {
        //Create list item element
        $listItem = new HTMLElement(HTMLElement::LI);

        //Parse paragraph
        $paragraphHTML = new XWPFParagraph($paragraph, $this->mainStyleSheet, $this->getId());
        $paragraphContainer = $paragraphHTML->parseParagraph();

        if (is_object($paragraphContainer)) {
            if(isset($this->sectionNumbering)){
                ListHelper::setListNumbering($this->sectionNumbering,$paragraphContainer);
            }
            $paragraphContainer->setAttribute('style', 'text-indent: 0px; margin-bottom: 0px');
            if (isset($this->listItemIterator)) {
                $listItem->setAttribute('value',$this->listItemIterator);
                $this->listItemIterator++;
            }
            $listItem->addInnerElement($paragraphContainer);
        } elseif (!is_object($paragraphContainer)) {
            var_dump(java_values($paragraph->getText()));
        }
        return $listItem;
    }

    /**
     * Process a list of the document
     * @param $numberingInfo
     * @param $container
     * @param $paragraph
     * @param $listNumId
     * @param $listLevelState
     * @internal param $key
     */
    public function parseList($numberingInfo, $container, $paragraph, &$listNumId, &$listLevelState, &$listItemIterator)
    {
        //Extract List Properties
        $abstractNum = $this->getAbstractNum();
        $listProperties = ListHelper::extractListProperties($abstractNum, $numberingInfo);

        //If this is set to true a new list container should be created
        $isNewList = ($listNumId != $numberingInfo['numId']) ? true : false;
        $levelCount = $numberingInfo['lvl'];

        if($isNewList) {
            $this->listItemIterator = 1;
        }

        //Check if the list level state has change
        if ($listLevelState != $levelCount) {
            $this->createNewLevelList($container, $listLevelState, $listProperties);
        }

        //Assign new list level state and num id
        $listLevelState = $numberingInfo['lvl'];
        $listNumId = $numberingInfo['numId'];

        // Parse list item
        $listItemHTMLElement = $this->parseListItem($paragraph);
        $listItemHTMLElement->setId($this->getId());
        $containerLastElement = $this->selectLastElementOfContainer($container, $isNewList);

        // Check if the actual container has a list container as last element
        if ($containerLastElement == self::UL) {
            $this->addListItem($container, $listLevelState, $listItemHTMLElement);
        } elseif ($containerLastElement == self::INNER_UL || $containerLastElement != self::UL) {
            $this->createNewListContainer($container, $listProperties, $listItemHTMLElement);
        }
    }

    /**
     * @return mixed
     * @internal param $numberingInfo
     */
    private function getAbstractNum()
    {
        $numberingInfo = ListHelper::paragraphExtractNumbering($this->paragraph);
        $numbering = $this->numbering;
        $numId = new Java('java.math.BigInteger', $numberingInfo['numId']);
        $abstractNumId = java_values($numbering->getAbstractNumId($numId)->toString());
        $finalId = new Java('java.math.BigInteger', $abstractNumId);
        $abstractNum = java_values($numbering->getAbstractNum($finalId));

        return $abstractNum;
    }

    /**
     * @param $container
     * @param $listProperties
     * @param $listItemHTMLElement
     * @internal param $key
     */
    private function createNewListContainer($container, $listProperties, $listItemHTMLElement)
    {
        $listContainer = new HTMLElement(HTMLElement::UL);
        $listContainer->setId($this->getId());
        $paragraphHTML = new XWPFParagraph($this->paragraph, $this->mainStyleSheet,$this->getId());
        if (isset($this->tocNumber) && $paragraphHTML->isHeadline()) {
            $listContainer->setAttribute('style', 'list-style-type: none;' . 'margin-left:' . $listProperties['indentation'] . 'px');
        }else{
            $listContainer->setAttribute('style', 'list-style-type:' . $listProperties['type'] . ';' . 'margin-left:' . $listProperties['indentation'] . 'px');
        }

        $firstItemId = $this->getId() + 1;
        $listItemHTMLElement->setId($firstItemId);
        $listContainer->addInnerElement($listItemHTMLElement);
        $container->addInnerElement($listContainer);
    }

    /**
     * @param $container
     * @param $listLevelState
     * @param $listProperties
     * @return array
     */
    private function createNewLevelList($container, &$listLevelState, $listProperties)
    {
        $newList = new HTMLElement(HTMLElement::UL);
        $paragraphHTML = new XWPFParagraph($this->paragraph, $this->mainStyleSheet,$this->getId());
        if (isset($this->tocNumber) && $paragraphHTML->isHeadline()) {
            $newList->setAttribute('style', 'list-style-type: none;' . 'margin-left:' . $listProperties['indentation'] . 'px');
        }else {
            $newList->setAttribute('style', 'list-style-type:' . $listProperties['type']);
        }
        $lastContainer = $container->getLastElement();
        if(isset($this->listItemIterator)) $this->listItemIterator = 1;
        for ($i = 0; $i < $listLevelState; $i++) {
            if (is_object($lastContainer->getLastElement())) $lastContainer = $lastContainer->getLastElement();
        }
        if (is_object($lastContainer)) $lastContainer->addInnerElement($newList);
    }

    /**
     * @param $container
     * @param $isNewList
     * @return string
     */
    private function selectLastElementOfContainer($container, $isNewList)
    {
        if (is_object($container->getLastElement()) && !$isNewList) {
            $containerLastElement = $container->getLastElement()->getTagName();
        } elseif ($isNewList) {
            $containerLastElement = self::INNER_UL;
        } else {
            $containerLastElement = "text";
        }
        return $containerLastElement;
    }

    /**
     * @param $container
     * @param $listLevelState
     * @param $listItemHTMLElement
     * @internal param $key
     */
    private function addListItem($container, &$listLevelState, $listItemHTMLElement)
    {
        //Initialize last container
        $lastContainer = $container->getLastElement();

        //Find current list element
        for ($i = 0; $i < $listLevelState; $i++) {
            if (is_object($lastContainer)) {
                $lastContainer = $lastContainer->getLastElement();
            }
        }

        //Check if the container is fill
        if (is_object($lastContainer)) {
            $listItemHTMLElement->setId($this->getId());
            $lastContainer->addInnerElement($listItemHTMLElement);
        }
    }

    /**
     * @param $paragraph
     * @return HTMLElement
     */
    private function setChapterNumbers($paragraph)
    {
        $paragraphHTML = new XWPFParagraph($paragraph, $this->mainStyleSheet, $this->getId());
        $paragraphContainer = $paragraphHTML->parseParagraph();

        if (isset($this->tocNumber) && $paragraphHTML->isHeadline()) {
            $styleName = $paragraphHTML->getStyleName();
            switch ($styleName) {
                case 'Heading 1':
                    $this->firstLevel++;
                    $sectionText = (string)$this->firstLevel;
                    $this->secondLevel = 0;
                    $this->thirdLevel = 0;
                    break;
                case 'Heading 2':
                    $this->secondLevel++;
                    $sectionText = $this->firstLevel . "." . $this->secondLevel;
                    $this->thirdLevel = 0;
                    break;
                case 'Heading 3':
                    $this->thirdLevel++;
                    $sectionText = $this->firstLevel . "." . $this->secondLevel . "." . $this->thirdLevel;
                    break;
                default:
                    $sectionText = "";
                    break;
            }

//            var_dump($sectionText);
//            var_dump(java_values($paragraph->getText()));

            $sectionContainer = new HTMLElement(HTMLElement::SPAN);
            $sectionContainer->setInnerText($sectionText);
            $elements = $paragraphContainer->getLastElement()->getInnerElements();
            array_unshift($elements, $sectionContainer);
            $paragraphContainer->getLastElement()->setInnerElementsArray($elements);
            return $paragraphContainer;
        }
        return $paragraphContainer;
    }

}