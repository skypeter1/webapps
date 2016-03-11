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
    }

    /**
     * @return mixed
     */
    private function getId()
    {
        return $this->id;
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
        $paragraph = new XWPFParagraph($paragraph, $this->mainStyleSheet,$this->getId());
        $paragraphContainer = $paragraph->parseParagraph();

        if (is_object($paragraphContainer)) {
            $paragraphContainer->setAttribute('style', 'text-indent: 0px; margin-bottom: 0px');
            $listItem->setInnerElement($paragraphContainer);
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
    public function parseList($numberingInfo, $container, $paragraph, &$listNumId,&$listLevelState)
    {

        //Extract List Properties
        $abstractNum = $this->getAbstractNum($numberingInfo);
        $listProperties = ListHelper::extractListProperties($abstractNum, $numberingInfo);

        //If this is set to true a new list container should be created
        $isNewList = ($listNumId != $numberingInfo['numId']) ? true : false;
        $levelCount = $numberingInfo['lvl'];

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
     * @param $numberingInfo
     * @return mixed
     */
    private function getAbstractNum($numberingInfo)
    {
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
        $listContainer->setAttribute('style', 'list-style-type:' . $listProperties['type'] . ';' . 'margin-left:' . $listProperties['indentation'] . 'px');
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
        $newList->setAttribute('style', 'list-style-type:' . $listProperties['type']);
        $lastContainer = $container->getLastElement();
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

}