<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/HTMLElement.php';
include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPF/XWPFTable.php';


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
        $table = new XWPFTable();
        $isObject = is_object($table);
        $this->assertEquals($isObject,true);
    }
}
