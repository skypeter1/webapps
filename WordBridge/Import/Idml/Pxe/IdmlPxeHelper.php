<?php

class IdmlPxeHelper 
{
    /**
     * attribute names for PXE data components
     */
    const PXE_ATTRIB_TAG        = 'data-pxe-tag';
    const PXE_ATTRIB_CLASS      = 'data-pxe-class';
    const PXE_ATTRIB_PARENTS    = 'data-pxe-parents';
    const PXE_ATTRIB_HASH       = 'data-pxe-hash';
    const PXE_ATTRIB_ATTRIBUTES = 'data-pxe-attributes';
    
    public static function setPxeTag($element, $tagName)
    {
        $element->attributes[self::PXE_ATTRIB_TAG] = $tagName;            
    }
    
    public static function getPxeTag($element)
    {
        if(self::hasPxeTag($element))
        {
            return $element->attributes[self::PXE_ATTRIB_TAG];
        }
        return "";
    }
    
    public static function setPxeHash($element, $hashTag)
    {
        $element->attributes[self::PXE_ATTRIB_HASH] = $hashTag;
    }
    
    public static function getPxeHash($element)
    {
        if(self::hasPxeHash($element))
        {
            return $element->attributes[self::PXE_ATTRIB_HASH];
        }
        return "";
    }    
    
    public static function setPxeAttributes($element, array $attributes)
    {
        $element->attributes[self::PXE_ATTRIB_ATTRIBUTES] = json_encode($attributes);
    }
    
    public static function getPxeAttributes($element)
    {
        if(self::hasPxeAttributes($element))
        {
            return $element->attributes[self::PXE_ATTRIB_ATTRIBUTES];
        }
        return "";
    }       
    
    public static function setPxeClass($element, $className)
    {
        $element->attributes[self::PXE_ATTRIB_CLASS] = $className;
    }    
    
    public static function getPxeClass($element)
    {
        if(self::hasPxeClass($element))
        {
            return $element->attributes[self::PXE_ATTRIB_CLASS];
        }
        return "";
    }    
    
    /**
     * Clears PXE attributes
     * @param IdmlElement $element
     */
    public static function clearPxeData($element)
    {
        self::setPxeTag($element, '');
        self::setPxeHash($element, '');
        self::setPxeParents($element, '');
        self::setPxeClass($element, '');
    }
    
    public static function hasPxeData($element)
    {
        return (self::hasPxeTag($element) || 
                self::hasPxeParents($element) || 
                self::hasPxeClass($element) || 
                self::hasPxeAttributes($element));
    }
    
    public static function setPxeParents($element, $parents)
    {
        if(is_array($parents))
        {
            $parents = implode(' ',$parents);
            if(strlen($parents)>0)
            {            
                $element->attributes[self::PXE_ATTRIB_PARENTS] = $parents;
            }
        }
        else
        {
            $element->attributes[self::PXE_ATTRIB_PARENTS] = $parents;
        }
    }    
    
    public static function getPxeParents($element, $bArray=false)
    {
        if($bArray)
        {
            if(!self::hasPxeParents($element))
            {
                return array();
            }
            return explode(' ',$element->attributes[self::PXE_ATTRIB_PARENTS]);
        }
        else
        {
            if(!self::hasPxeParents($element))
            {
                return "";
            }
            return $element->attributes[self::PXE_ATTRIB_PARENTS];
        }
    }
    
    /**
     * Determines if an element has an XmlElement as a parent somewhere
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasXmlElementParent(IdmlElement $element)
    {
        $hasParent = false;    
        if($element->parentIdmlObject())
        {        
            $parent = $element->parentIdmlObject();
            while(is_object($parent) && !is_a($parent,"IdmlXmlElement"))
            {
                $parent = $parent->parentIdmlObject();
            }
            if(is_object($parent) && is_a($parent,"IdmlXmlElement") && $parent->markupTag != "XMLTag/Story")
            {
                $hasParent = true;
            }
        }
        return $hasParent;
    }
    
    /**
     * determies if the element contains an IdmlContent element somewhere
     * @param IdmlElement $element
     * @return boolean
     */
    public static function elementHasContent(IdmlElement $element)
    {
        if(count($element->childrenElements)==0)
        {
            return false;
        }
        $bHasContent = false;
        $x=0;
        while(!$bHasContent && $x<count($element->childrenElements))
        {
            if(is_a($element->childrenElements[$x],"IdmlParagraphRange") || 
               is_a($element->childrenElements[$x],"IdmlCharacterRange") ||
               is_a($element->childrenElements[$x],"IdmlXmlElement") ||
               is_a($element->childrenElements[$x],"IdmlGroup") ||
               is_a($element->childrenElements[$x],"IdmlTextFrame"))
            {
                $bHasContent = self::elementHasContent($element->childrenElements[$x]);
            }
            else 
            {
                if(!is_a($element->childrenElements[$x],"IdmlBrContent") || 
                   (is_a($element->childrenElements[$x],"IdmlContent") && $element->childrenElements[$x]->hasContent()))
                {
                    $bHasContent = true;
                }
            }
            $x++;
        }
        return $bHasContent;
    }    
        
    /**
     * checks if an element has a pxe tag set
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasPxeTag(IdmlElement $element)
    {
        return (array_key_exists(self::PXE_ATTRIB_TAG,$element->attributes) && strlen($element->attributes[self::PXE_ATTRIB_TAG])>0);
    }
    
    /**
     * checks if an element has a pxe tag set
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasPxeClass(IdmlElement $element)
    {
        return (array_key_exists(self::PXE_ATTRIB_CLASS,$element->attributes) && strlen($element->attributes[self::PXE_ATTRIB_CLASS])>0);
    }    
    
    /**
     * checks if an element has a pxe tag set
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasPxeHash(IdmlElement $element)
    {
        return (array_key_exists(self::PXE_ATTRIB_HASH,$element->attributes) && strlen($element->attributes[self::PXE_ATTRIB_HASH])>0);
    }      

    /**
     * checks if an element has a pxe parents set
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasPxeAttributes(IdmlElement $element)
    {
        return (array_key_exists(self::PXE_ATTRIB_ATTRIBUTES,$element->attributes) && strlen($element->attributes[self::PXE_ATTRIB_ATTRIBUTES])>0);
    }    

    /**
     * checks if an element has a pxe parents set
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasPxeParents(IdmlElement $element)
    {
        return (array_key_exists(self::PXE_ATTRIB_PARENTS,$element->attributes) && strlen($element->attributes[self::PXE_ATTRIB_PARENTS])>0);
    }    
    
    
    /**
     * Generates a new hash value for pxe tags 
     * @return string
     */    
    public static function getNewTagHash()
    {
       $hashStr = "a0";
        for($x=0;$x<5;$x++)
        {
            $hashStr .= str_pad(dechex(rand(0,255)), 2, '0', STR_PAD_LEFT);
        }
        return $hashStr;
    }
    
    /**
     * Determines if an element has already been assigned a hash value
     * @param IdmlElement $element
     * @return boolean
     */
    public static function hasHashValue($element)
    {
        if(array_key_exists("data-pxe-hash", $element->attributes) && strlen($element->attributes["data-pxe-hash"])>0)
        {
            return true;
        }
        return (strpos($element->idmlTag,'#') !== false);
    }   
    
    /**
     * Returns the full PXE tag string, with tag, class and hash ie:h1.title#ababab
     * @param IdmlElement $element
     * @return string
     */
    public static function getFullPxeTag(IdmlElement $element)
    {
        $tagName = $element->attributes["data-pxe-tag"];
        if(array_key_exists("data-pxe-class",$element->attributes) && strlen($element->attributes['data-pxe-class'])>0)
        {
            $tagName .= ".".$element->attributes['data-pxe-class'];
        }
        $tagName .= "#".$element->attributes['data-pxe-hash'];
        return $tagName;        
    }    
    
    /**
     * Utility function to remove the hash value from a tag, including the #
     * @param string $tag
     * @return string
     */
    public static function stripHash($tag)
    {
        if(substr($tag,0,1)=='+')
        {
            $tag = substr($tag,1);
        }
        if(strpos($tag,'#') !== false)
        {
             $tag = substr($tag,0,strpos($tag,'#'));
        }       
        return $tag;
    }
   
    /**
     * Returns just the tag name, removing the class and hash value
     * @param string $tag
     * @return string
     */
    public static function stripClass($tag)
    {
         if(strpos($tag,'.'))
         {
             $tag = substr($tag,0,strpos($tag,'.'));       
         }
         return $tag;
    }    
}

?>
