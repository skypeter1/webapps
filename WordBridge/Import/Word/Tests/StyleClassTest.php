<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/StyleClass.php';
/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/26/15
 * Time: 11:36 AM
 */
class StyleClassTest extends PHPUnit_Framework_TestCase
{
    private $style;

    public function setUp(){
        $this->style = new StyleClass();
        $this->style->setAttribute('color','black');
        $this->style->setAttribute('border-style','solid');
        $this->style->setAttribute('font-weight','bold');
    }

    public function testMergeClass()
    {
        $styleToMerge = new StyleClass();
        $styleToMerge->setAttribute('font-family','Arial');
        $styleToMerge->setAttribute('display','inline');
        $styleToMerge->setAttribute('background-color','#FFFFFF');
        $styleToMerge->setAttribute('color','white');
        $styleToMerge->setAttribute('font-weight','italic');

        $mergedStyle = $this->style->mergeStyleClass($styleToMerge);

        $numAttributes = count($mergedStyle->getAttributes());
        $this->assertEquals(6,$numAttributes);

        $color = $mergedStyle->getAttributeValue('color');
        $fontWeight = $mergedStyle->getAttributeValue('font-weight');
        $this->assertEquals($color,'black');
        $this->assertEquals($fontWeight,'bold');
    }
}
