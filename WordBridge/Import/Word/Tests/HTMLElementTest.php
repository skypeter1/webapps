<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/HTMLElement.php';

/**
 * Created by PhpStorm.
 * User: peter
 * Date: 7/8/15
 * Time: 10:32 AM
 */
class HTMLElementTest extends PHPUnit_Framework_TestCase
{

    public function testGetHTML()
    {
        $divContainer = new HTMLElement(HTMLElement::DIV);

        $pContainer1 = new HTMLElement(HTMLElement::P);
        $pContainer2 = new HTMLElement(HTMLElement::P);

        $spanContainer1 = new HTMLElement(HTMLElement::SPAN);
//        $spanContainer2 = new HTMLElement(HTMLElement::SPAN);
//        $spanContainer3 = new HTMLElement(HTMLElement::SPAN);
//
//        $textContainer1 =  new HTMLElement(HTMLElement::TEXT);
//        $textContainer2 =  new HTMLElement(HTMLElement::TEXT);
//        $textContainer3 =  new HTMLElement(HTMLElement::TEXT);
//        $textContainer4 =  new HTMLElement(HTMLElement::TEXT);

        $spanContainer1->setInnerText('Text inside span');
        $pContainer1->addInnerElement($spanContainer1);
        $divContainer->addInnerElement($pContainer1);

        $level = 0;
        $html = $divContainer->getHTML($level);

        $expected = "<div><p><span>Text inside span</span></p></div>";

        //$this->assertEquals($expected, $html, 'Testing HTML Container');
    }

    public function testGetInnerElementsByTag()
    {
        $divContainer = new HTMLElement(HTMLElement::DIV);
        $pContainer1 = new HTMLElement(HTMLElement::P);
        $spanContainer1 = new HTMLElement(HTMLElement::SPAN);
        $tableContainer1 = new HTMLElement(HTMLElement::TABLE);

        $pContainer1->addInnerElement($spanContainer1);
        $divContainer->addInnerElement($pContainer1);
        $divContainer->addInnerElement($tableContainer1);

        $elements = $divContainer->getInnerElementsByTagName("table");
        $this->assertInternalType('array', $elements);
        foreach ($elements as $element) {
            $this->assertEquals("table", $element->getTagName());
        }
    }

}
