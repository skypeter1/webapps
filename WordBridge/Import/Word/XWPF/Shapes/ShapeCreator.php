<?php


class ShapeCreator
{
    private $xml;

    function __construct($graphic)
    {
        $this->xml = $this->getXMLObject($graphic);
    }

    private function getXMLObject($stringXml)
    {
        $w = str_replace("w:", 'w', $stringXml);
        $mc = str_replace("mc:", 'mc', $w);
        $wp = str_replace("wp:", 'wp', $mc);
        $a = str_replace("a:", 'wa', $wp);
        $paragraphXmlString = str_replace("wps:", 'wps', $a);

        $paragraphXml = new SimpleXMLElement($paragraphXmlString);
        return $paragraphXml;
    }

    public function processShape(){
        $wr = $this->xml->xpath("wr/mcAlternateContent");
        if(!empty($wr)){
            foreach($wr as $alternate){
                //var_dump($alternate->xpath("mcChoice/wdrawing"));
            }
        }
    }

}