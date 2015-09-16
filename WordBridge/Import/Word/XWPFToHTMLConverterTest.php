<?php

include '/var/lib/tomcat7/webapps/WordBridge/Import/Word/XWPFToHTMLConverter.php';
/**
 * Created by PhpStorm.
 * User: peter
 * Date: 7/15/15
 * Time: 4:56 PM
 */
class XWPFToHTMLConverterTest extends PHPUnit_Framework_TestCase
{
    private $document;

    protected function setUp()
    {
        $workingDir = "/vagrant";
        $progressUpdater = $this->getMockBuilder('ProgressUpdater')
            ->disableOriginalConstructor()
            ->getMock();

        $this->document = new XWPFToHTMLConverter($workingDir, $progressUpdater);
    }

    public function testLoadDocx()
    {
        $document = $this->document->loadDocx("/home/peter/Documents/Images.docx");
        var_dump($document);
        $this->assertNotNull($document);
    }


}
