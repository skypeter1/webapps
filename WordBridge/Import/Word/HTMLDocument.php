<?php
/**
* Simple HTMLDocument class, that you can construct it's body, header, stylesheet information, and things like that
* and the be able to generate a final HTML based on provided data;
* @author Avetis Zakharyan
*/
class HTMLDocument{

    /**
     * An instance of HTMLElement, that will be parent of everything in a body tag. (maybe make a body tag in future?)
     */
    private $bodyInner;

    /**
     * An instanceof StyleSheet class, conatins all style sheet information of document, can be put inline or in separate file
     */
    public $styleSheet;

    private $title;
    private $inlineStyle = false;

    /**
     * Set style inline
     * @param   boolean Boolean
     */
    public function setStyleInline($boolean)
    {
        $this->inlineStyle = $boolean;
    }

    /**
     * Construct header
     * @return  string
     */
    private function constructHeader()
    {
        // Create head, title, and meta
        $str = "<head>\n";
        $str .= "<title>{$this->title}</title>\n";
        $str .= '<meta charset="UTF-8"/>'."\n";
        
        // Check if this is inline style or linked style sheet
        if ($this->inlineStyle) {
            $str .= "<style> \n";
            $str .= $this->styleSheet->getFinalCSS();
            $str .= "</style>\n";
        } else {
            $str .= "<link href=\"style.css\" media=\"screen\" rel=\"stylesheet\" type=\"text/css\" /> \n";
        }
        
        // Close head tag
        $str .= "</head>\n";
        
        // Return header
        return $str;
    }

    /**
     * Set title
     * @param   string  Title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
    
    /**
     * Get title
     * @return  string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set inner body
     * @param   object  Element
     */
    public function setBody($element)
    {
        $this->bodyInner = $element;
    }
    
    /**
     * Get HTML
     * @return  string
     */
    public function getHTML()
    {
        // Construct HTML
        $str  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n";
        $str .= '<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">'."\n";
        $str .= $this->constructHeader();
        $str .= "<body>".$this->bodyInner->getHTML()."</body>\n";
        $str .= "</html>";
        
        // Return HTML
        return $str;
    }
    
    /**
     * Get HTML Body
     * @return  string
     */
    public function getBodyHTML()
    {
        // Construct body
        $str = "<body>".$this->bodyInner->getHTML()."</body>\n";
        
        // Return body
        return $str;
    }
}
