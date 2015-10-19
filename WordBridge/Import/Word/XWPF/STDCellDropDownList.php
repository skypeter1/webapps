<?php

class STDCellDropDownList
{
    public $xml;

    /**
     * @param $xml
     * @throws Exception
     */
    function __construct($xml)
    {
        if (is_a($xml, 'SimpleXMLElement')) {
            $this->xml = $xml;
        } else {
            throw new Exception("[STDCellDropDownList::new STDCellDropDownList()] Incorrect type of");
        }
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        $options = $this->xml->xpath('wlistItem');
        return $options;
    }

    /**
     * @return HTMLElement
     */
    public function parseDropDownList()
    {
        $dropDownListContainer = new HTMLElement(HTMLElement::SELECT);
        $options = $this->getOptions();

        foreach ($options as $option) {
            $optionContainer = new HTMLElement(HTMLElement::OPTION);
            $optionText = (string)$option[0]['wdisplayText'];
            $optionValue = (string)$option[0]['wvalue'];
            $optionContainer->setInnerText($optionText);
            $optionContainer->setAttribute('value', $optionValue);
            $dropDownListContainer->addInnerElement($optionContainer);
        }
        return $dropDownListContainer;
    }

}