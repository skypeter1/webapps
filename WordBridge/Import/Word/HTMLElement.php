<?php

class HTMLElement{

    const TEXT = "text";
    const A = "a";
    const SPAN = "span";
    const DIV  = "div";
    const IMG  = "img";
    const P    = "p";
    const LI   = "li";
    const UL   = "ul";
    const TABLE = "table";
    const TR    = "tr";
    const TD    = "td";
    const STRONG = "strong";
    const EM = "em";
    const HEADER = "header";
    const FOOTER = "footer";
    const H1 = "h1";
    const H2 = "h2";
    const H3 = "h3";
    const H4 = "h4";
    const H5 = "h5";
    const H6 = "h6";
    const FIGURE = "figure";
    const ARTICLE = "article";
    const SECTION = "section";
    const NAV = "nav";
    const ASIDE = "aside";
    const SEC = "sec";

    private $tagName;
    private $id;
    private $attributes;
    private $innerElements;
    
    /**
     * Construct HTML Element
     * @param  string  tagname
     */
    function __construct($tagName)
    {
        $this->tagName = $tagName;
        $this->attributes = array();
        $this->innerElements = array();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function setId($id){
        $this->id = $id;
        return $this->id;
    }

    /**
     * @return int
     */
    public function getId(){
        if(is_null($this->id)){
            return -1;
        }else{
            return $this->id;
        }
    }

    /**
     * Get tagName
     * @return mixed
     */
    public function getTagName()
    {
        return $this->tagName;
    }
    
    /**
     * Set attribute
     * @param   mixed   $key
     * @param   mixed   $value
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }
    
    /**
     * Remove attribute
     * @param   mixed   $key
     */
    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
    }	
    
    /**
     * Get attribute
     * @param   mixed   $key
     */
    public function getAttribute($key)
    {
        $attribute = "";
        if(!is_null($this->attributes[$key])){
            $attribute =  $this->attributes[$key];
        }
       return $attribute;
    }

    public function getAttributes(){
        return $this->attributes;
    }

    
    /**
     * Set class by name
     * @param   mixed  Class name
     */
    public function setClass($className)
    {
        $this->attributes['class'] = $className;
    }
    
    /**
     * Get class
     * @return  mixed
     */
    public function getClass()
    {
        if (array_key_exists('class', $this->attributes)){
            return $this->attributes['class'];
        }
        return '';
    }

    /**
     * Set inner element
     * @param   array   Inner element
     */
    public function setInnerElement($innerElement)
    {
        $this->innerElements = array($innerElement);
    }
    
    /**
     * Get last element
     * @return  array
     */
    public function getLastElement()
    {
        return end($this->innerElements);
    }
    
    /**
     * Add inner element
     * @param   array   Inner element
     */
    public function addInnerElement($innerElement)
    {
        $this->innerElements[] = $innerElement;
    }
    
    /**
     * Set inner text
     * @param   string  Text
     */
    public function setInnerText($text)
    {
        // Create text element
        $textElement = new HTMLElement(self::TEXT);
        $textElement->setInnerElement($text);
        
        // Set inner text element
        $this->setInnerElement($textElement);
    }
    
    /**
     * Add inner text
     * @param   string  Text
     */
    public function addInnerText($text)
    {
        // Create new text element
        $textElement = new HTMLElement(self::TEXT);
        
        // Assign text to text element
        if ($this->innerElements != null && isset($this->innerElements[0])) {
            $text = $this->innerElements[0]->getHTML().$text;
            $text = str_replace("\n", "", $text);
        }
        
        // Set inner text element
        $textElement->setInnerElement($text);
        $this->setInnerElement($textElement);
    }
    
    /**
     * Get inner text
     * @return  mixed
     */
    public function getInnerText()
    {
        return ($this->innerElements[0]) ? @$this->innerElements[0]->getHTML() : 0;
    }
    
    /**
     * Get inner elemens
     * @return mixed
     */
    public function getInnerElements()
    {
        return $this->innerElements;
    }

    /**
     * @param $tagName
     * @return array
     */
    public function getInnerElementsByTagName($tagName)
    {
        $elements = $this->innerElements;
        $tagElements = array();
        foreach ($elements as $element) {
            if ($element->getTagName() == $tagName) {
                $tagElements[] = $element;
            }
        }
        return $tagElements;
    }

    /**
     * Check if the toc cell contains text
     * @return bool
     */
    public function tocCellContainsText()
    {
        $contains = true;
        $text = "";
        if ($this->getTagName() == 'td') {
            $inner = $this->getInnerElements();
            for ($i = 0; $i < count($inner); $i++) {
                $text .= $inner[$i]->getInnerText();
            }
            $needles = array("<strong >","</strong>","<em >","</em>","<br />");
            $text = trim(str_replace($needles, "", $text));

            if (strlen($text) == 0) {
                $contains = false;
            }
        }

        return $contains;
    }
    
    /**
     * Get HTML
     * @param   int Level
     * @return  string
     */
    public function getHTML($level = 0)
    {
        // Get tab for string
        $tabstr = ($level > 0) ? str_repeat("\t", $level) : '';
        
        // Check for text
        if ($this->tagName == self::TEXT) {
            return $tabstr.$this->innerElements[0]."\n";
        }
        
        // Create tag with its attributes
        $str = $tabstr."<".$this->tagName." ";
        foreach ($this->attributes as $key => $value) {
            $value = addslashes($value);
            $str .= $key.' = "'.$value.'" ';
        }
        
        // Create inner HTML out of elements
        $innerHTML = '';
        foreach ($this->innerElements as $element) {
            if ($element) $innerHTML .= $element->getHTML($level + 1);
        }
        
        // Add tag type
        switch ($this->tagName) {
            case self::IMG:     $str .= "/>\n";                                             break;
            case self::SPAN:    $str .= '>'.trim($innerHTML).'</'.$this->tagName.">\n";     break;
            case self::TEXT:    $str .= trim($innerHTML);                                   break;
            default:            $str .= ">\n".$innerHTML.$tabstr.'</'.$this->tagName.">\n"; break;
        }
        
        // Return HTML
        return $str;
    }
}
