<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/HTMLElement.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTable.php';
include "/var/lib/tomcat7/webapps/WordBridge/java/Java.inc";

/**
 * Created by PhpStorm.
 * User: root
 * Date: 10/9/15
 * Time: 11:05 AM
 */
class XWPFTableTest extends PHPUnit_Framework_TestCase
{

    public function testNewXWPFTable()
    {
        $element = new Java("org.apache.poi.xwpf.usermodel.XWPFTable");
        $stylesheet = new StyleSheet();
        $table = new XWPFTable($element,"2",$stylesheet);
        $isObject = is_object($table);
        $this->assertEquals($isObject,true);
    }
}
