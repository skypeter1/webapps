<?php
/**
* HWPFWrapper is a static class that holds constants and variables relevant to HWPF or XWFP format, to help parsing
* @author Avetis Zakharyan
*/
class HWPFWrapper {

    const TWIPS_PER_INCH = 1440;
    const TWIPS_PER_PT = 20;
    const EMUS_PER_INCH = 914400;

    /**
     * List of special characters of charRun, that can represent something else then a simple text
     */
    const SPECCHAR_IMAGE = 1;
    const SPECCHAR_AUTONUMBERED_FOOTNOTE_REFERENCE = 2;
    const SPECCHAR_DRAWN_OBJECT = 8;
    const UNICODECHAR_NO_BREAK_SPACE = '\u00a0';
    const UNICODECHAR_NONBREAKING_HYPHEN = '\u2011';
    const UNICODECHAR_ZERO_WIDTH_SPACE = '\u200b';
    const BEL_MARK = 7;
    const FIELD_BEGIN_MARK = 19;
    const FIELD_END_MARK = 21;
    const FIELD_SEPARATOR_MARK = 20;
    
    /**
     * Create a color fix
     * @param   string  Color in HEX
     * @return  string
     */
    public static function colorFix($color)
    {
        return ($color == 'auto') ? '#000000' : '#'.$color;
    }
    
    /**
     * Get color
     * @param   int Color integer
     * @return  string
     */
    public static function getColor($ico)
    {
        // Get color
        switch ($ico) {
            case 1:     $color = "black";       break;
            case 2:     $color = "blue";        break;
            case 3:     $color = "cyan";        break;
            case 4:     $color = "green";       break;
            case 5:     $color = "magenta";     break;
            case 6:     $color = "red";         break;
            case 7:     $color = "yellow";      break;
            case 8:     $color = "white";       break;
            case 9:     $color = "darkblue";    break;
            case 10:    $color = "darkcyan";    break;
            case 11:    $color = "darkgreen";   break;
            case 12:    $color = "darkmagenta"; break;
            case 13:    $color = "darkred";     break;
            case 14:    $color = "darkyellow";  break;
            case 15:    $color = "darkgray";    break;
            case 16:    $color = "lightgray";   break;
	    default:
                
                $ico = str_replace('#', '', $ico);
                $color = (preg_match('/^[a-f0-9]{3,6}$/i', $ico)) ? '#'.$ico : 'black';
                break;
        }
        
        // Return color
        return $color;
    }
//
//    public function getTableCellBorderType($name){
//
//        switch($name){
//            case 'SINGLE'
//            default: $type = $name; break;
//        }
//        return $type;
//    }

    /**
     * Get font family type type
     * @link  Reference of web safe fonts http://www.w3schools.com/cssref/css_websafe_fonts.asp
     * @param   string  font
     * @return  string
     */
    public static function getFontFamily($font)
    {
        // Create font family
        switch($font){

            case 'Arial':               $fontFamily = 'Arial, Helvetica, sans-serif';                                           break;
            case 'Helvetica Neue':      $fontFamily = 'Arial, Helvetica, sans-serif';                                           break;
            case 'Calibri':             $fontFamily = 'Arial, Helvetica, sans-serif';                                           break;
            case 'Helvetica':           $fontFamily = 'Arial, Helvetica, sans-serif';                                           break;
            case 'Palatino':            $fontFamily = '"'.'Palatino Linotype'.'", "'.'Book Antiqua'.'", '.'Palatino, serif';    break;
            case 'Palatino Linotype':   $fontFamily = '"'.'Palatino Linotype'.'", "'.'Book Antiqua'.'", '.'Palatino, serif';    break;
            case 'Times':               $fontFamily = '"'.'Times New Roman'.'",'.'Times, serif';                                break;
            case 'Times New Roman':     $fontFamily = '"'.'Times New Roman'.'",'.'Times, serif';                                break;
            case 'Cambria':             $fontFamily = '"'.'Times New Roman'.'",'.'Times, serif';                                break;
            case 'Georgia':             $fontFamily = 'Georgia,serif';                                                          break;
            case 'Helvetica':           $fontFamily = 'Arial, Helvetica, sans-serif';                                           break;
            case 'Tahoma':              $fontFamily = 'Tahoma, Geneva, sans-serif';                                             break;
            case 'Trebuchet MS':        $fontFamily = '"'.'Trebuchet MS'.'", '.'Helvetica, sans-serif';                         break;
            case 'Trebuchet':           $fontFamily = '"'.'Trebuchet MS'.'", '.'Helvetica, sans-serif';                         break;
            case 'Courier':             $fontFamily = '"'.'Courier New'.'", '.'Courier, monospace';                             break;
            case 'Courier New':         $fontFamily = '"'.'Courier New'.'", '.'Courier, monospace';                             break;
            case 'Verdana':             $fontFamily = 'Verdana, Geneva, sans-serif';                                            break;
            case 'Geneva':              $fontFamily = 'Verdana, Geneva, sans-serif';                                            break;
            case 'Lucida Console':      $fontFamily = '"'.'Lucida Console'.'", '.'Monaco, monospace';                           break;
            case 'Monaco':              $fontFamily = '"'.'Lucida Console'.'", '.'Monaco, monospace';                           break;
            default:                    $fontFamily = $font;                                                                    break;
        }

        //Return font family Style
        return $fontFamily;

    }

    /**
     * @param $container
     * @return HTMLElement
     * @internal param $tagName
     */
    public static function selectBreakPageContainer($container){
        $tagName = $container->getTagname();
        switch ($tagName) {
            case 'h1':
                $runHTMLElement = new HTMLElement(HTMLElement::H1);
                break;
            case 'h2':
                $runHTMLElement = new HTMLElement(HTMLElement::H2);
                break;
            case 'h3':
                $runHTMLElement = new HTMLElement(HTMLElement::H3);
                break;
            default :
                $runHTMLElement = new HTMLElement(HTMLElement::P);
                break;
        }
        return $runHTMLElement;
    }

    /**
     * Get border type
     * @param   string  Name
     * @return  string
     */
    public static function getBorder($name)
    {
        // Create border types dictionary
        $types = array('SINGLE' => 'solid',
                       'DASHED' => 'dashed',
                       'DOTTED' => 'dotted',
                       'DOUBLE' => 'double',
                        'NIL' => 'nil');
        
        // Return border type
        return (@isset($types[strtoupper($name)])) ? $types[strtoupper($name)] : 'solid';
    }

    public static function getTableBorders(){
        $border = null;
        switch($border){
            case 'single':
        }
        return $border;
    }
    
    /**
     * Get justification type
     * @param   int Justification type
     * @return  string
     */
    public static function getJustification($js)
    {
        // Get type
        switch ($js) {
            case 0:     $type = "start";        break;
            case 1:     $type = "center";       break;
            case 2:     $type = "end";          break;
            case 3:
            case 4:     $type = "justify";      break;
            case 5:     $type = "center";       break;
            default:
            case 6:     $type = "left";         break;
            case 7:     $type = "start";        break;
            case 8:     $type = "end";          break;
            case 9:     $type = "justify";      break;
        }
        
        // Return type
        return $type;
    }
    
    /**
     * Get alignment
     * @param   int Alignment
     * @return  string
     */
    public static function getAlignment($al)
    {
        // Get type
    	switch ($al) {
            default:
            case 1:     $type = 'both';             $css_type = 'left';     break;
            case 2:     $type = 'center';           $css_type = 'center';   break;
            case 3:     $type = 'distribute';       $css_type = 'right';    break;
            case 4:     $type = 'high_kashida';     $css_type = 'justify';  break;
            case 5:     $type = 'left';             $css_type = 'left';     break;
            case 6:     $type = 'low_kashida';      $css_type = 'left';     break;
            case 7:     $type = 'medium_kashida';   $css_type = 'left';     break;
            case 8:     $type = 'num_tab';          $css_type = 'left';     break;
            case 9:     $type = 'right';            $css_type = 'right';    break;
            case 10:    $type = 'thai_distribute';  $css_type = 'left';     break;
    	}
        
        // Return alignment type
        return $css_type;
    }
    
    /**
     * Get underline type
     * @link    https://poi.apache.org/apidocs/org/apache/poi/xwpf/usermodel/UnderlinePatterns.html
     * @param   int Underlined enum type
     * @return  string
     */
    public static function getUnderline($underline_type)
    {
        // Get type
    	switch ($underline_type) {
            case 1:     $type = 'DASH';                 $css_type = 'underline';    break;
            case 2:     $type = 'DASH_DOT_DOT_HEAVY';   $css_type = 'underline';    break;
            case 3:     $type = 'DASH_DOT_HEAVY';       $css_type = 'underline';    break;
            case 4:     $type = 'DASH_LONG';            $css_type = 'underline';    break;
            case 5:     $type = 'DASH_LONG_HEAVY';      $css_type = 'underline';    break;
            case 6:     $type = 'DASHED_HEAVY';         $css_type = 'underline';    break;
            case 7:     $type = 'DOT_DASH';             $css_type = 'underline';    break;
            case 8:     $type = 'DOT_DOT_DASH';         $css_type = 'underline';    break;
            case 9:     $type = 'DOTTED';               $css_type = 'underline';    break;
            case 10:    $type = 'DOTTED_HEAVY';         $css_type = 'underline';    break;
            case 11:    $type = 'DOUBLE';               $css_type = 'underline';    break;
            default:
            case 12:    $type = 'NONE';                 $css_type = 'none';         break;
            case 13:    $type = 'SINGLE';               $css_type = 'underline';    break;
            case 14:    $type = 'THICK';                $css_type = 'underline';    break;
            case 15:    $type = 'WAVE';                 $css_type = 'underline';    break;
            case 16:    $type = 'WAVY_DOUBLE';          $css_type = 'underline';    break;
            case 17:    $type = 'WAVY_HEAVY';           $css_type = 'underline';    break;
            case 18:    $type = 'WORDS';                $css_type = 'none';         break;
    	}
        
        // Return css type
        return $css_type;
    }

    /**
     * Get list type
     * @param $type
     * @param $listSymbol
     * @return string
     */
    public static function getListType($type ,$listSymbol){

        switch($type){

            case 'bullet':
                $listType = '';
                if($listSymbol === "Ã¯Â‚Â·"){
                    $listType = "disc";
                    echo "si llego aca";
                }else{
                    //echo "no es igual".$listSymbol." a Ã¯Â‚Â·";
                }

                if($listSymbol === "o"){
                    $listType = "circle";
                }else{
                    //echo "no es igual".$listSymbol." a o";
                }

                if($listSymbol === "Ã¯Â‚Â§"){
                    $listType = 'square';
                }else{
                    //echo "no es igual".$listSymbol." a Ã¯Â‚Â§";
                }

                break;
            case 'ï‚·': $listType = 'disc'; break;
            case 'o': $listType = 'circle'; break;
            case 'ï‚§': $listType = 'square'; break;

            case 'decimal': $listType = 'decimal'; break;
            case 'upperRoman': $listType = 'upper-roman'; break;
            case 'lowerRoman': $listType = 'lower-roman'; break;
            case 'upperLetter': $listType = 'upper-alpha'; break;
            case 'lowerLetter': $listType = 'lower-alpha'; break;
            case 'decimalZero': $listType = 'decimal-leading-zero'; break;
        }

        return $listType;
    }

    public static function getHeadlineType($styleName){

    }
    
    /**
     * Get bullet point text
     * @param   mixed   Numbering state
     * @param   object  Object list
     * @param   mixed   Level
     * @return  string
     */
    public static function getBulletText($numberingState, $list, $level)
    {
        // Get bullet level and create an empty buffer
        $bulletBuffer = "";
        $level = chr(java_values($level));
        $xst = java_values($list->getNumberText($level));
        
        // Loop through bullet points
        for ($i = 0; $i < strlen($xst); $i++) {
            
            // Get element and bullet point in ASCII
            $element = $xst[$i];
            $el = ord($element);
            
            // Check bullet point
            if ($el < 9) {
                
                // Prepare key and num
                $num = 0;
                $lsid = java_values($list->getLsid());
                $key = $lsid . "#" . $el;
                
                // Add numbering state
                if (!java_values($list->sStartAtOverriden($element)) && array_key_exists($key, $numberingState)) {
                    
                    $num = (int) $numberingState[$key];
                    if ($level == $element) {
                        $num++;
                        $numberingState[$key] = $num;
                    }
                    
                } else {
                    $num = java_values($list->getStartAt($element));
                    $numberingState[$key] = $num;
                }
                
                // Check for numbering reset
                if ($level == $element) {
                    
                    // Cleaning states of nested levels to reset numbering
                    for ($i = $el + 1; $i < 9; $i++) {
                        $childKey = $lsid . "#" . $i;
                        unset($numberingState[$childKey]);
                    }
                }
                
                // Add number to buffer
                $bulletBuffer .= $num;
            
            } else {
                
                // Add element to buffer
                $bulletBuffer .= $element;
            }
        } 
        
        // Get type of char follwoing the number
        $follow = java_values($list->getTypeOfCharFollowingTheNumber($level)); 
        switch ($follow) {
            case 0:     $bulletBuffer .= "\t";      break;
            case 1:     $bulletBuffer .= " ";       break;
            default: break;
        }
        
        // Return bullet points
        return $bulletBuffer;
    }
}
