<?php
/**
 * Provides interaction with the PXE json rule set stored on the file system
 *
 */

App::uses('IdmlPxeHelper','Import/Idml/Pxe');


class PxeRules 
{
    /**
     * PHP array containing the json data contents
     * @var array 
     */
    public $rules;

    /**
     * loads the PXE json data into memory
     */
    public function __construct()
    {
         $this->rules = json_decode(file_get_contents(APP.'Data/PxeStructure.json'), true);
    }
   
    /**
     * Returns the tag's rule set or false if the tag is not found
     * @param string $tag
     * @return array or false
     */
    public function getPxeTagRule($tag)
    {
        if(is_array($this->rules))
        {
            $tag = IdmlPxeHelper::stripHash($tag);
            if(array_key_exists($tag, $this->rules))
            {
                return $this->rules[$tag];
            }
            elseif(strpos($tag,'.'))
            {
                $tag = IdmlPxeHelper::stripClass($tag);
                return $this->getPxeTagRule($tag);
            }
        }
        return false;
    }
    
    /**
     * Determines if a tag is a valid pxe tag.  div and spans are hard coded as
     * valid.
     * @param string $tag
     * @return boolean
     */
    public function isPxeTag($tag)
    {
        return ($this->getPxeTagRule($tag)!==false || $tag=='div' || $tag=='span');   
    }
    
    /**
     * returns a list of required parents (this will almost always be one element, if any)
     * @param string $tag
     * @return array or false if tag is not valid
     */
    public function getRequiredParent($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            if(array_key_exists("requiresParent", $rule['validation']))
            {
                return $rule['validation']['requiresParent'];
            }
        }
        return false;
    }   
    
    /**
     * Returns an array of tag names allowed by the parent tag 
     * @param string $parentTag
     * @return array
     */
    public function getAllowedChildren($parentTag)
    {
        $allowedChildren = array();
        if(strlen($parentTag))
        {
            $rule = $this->getPxeTagRule($parentTag);     
            if(is_array($rule) && array_key_exists('validation', $rule))
            {
                if(array_key_exists("requiresChild", $rule['validation']))
                {
                    if(is_array($rule['validation']['requiresChild']))
                    {
                        $allowedChildren = array_merge($allowedChildren, $rule['validation']['requiresChild']);
                    }
                }
                if(array_key_exists("allowsChildren", $rule['validation']))
                {
                    if(is_array($rule['validation']['allowsChildren']))
                    {
                        $allowedChildren = array_merge($allowedChildren, $rule['validation']['allowsChildren']);
                    }
                }
            }
        }
        return $allowedChildren;
    }
    
    /**
     * Determiens if the child tag may be a direct descendent of the parent
     * @param string $parentTag
     * @param string $tag
     * @return boolean
     */
    public function isChildAllowed($parentTag, $tag)
    {        
        $tag = IdmlPxeHelper::stripHash($tag);
        if(strlen($parentTag)>0 && $parentTag != "div" && $parentTag != "span")
        {
            $allowedChildren = $this->getAllowedChildren($parentTag);
            return (in_array($tag, $allowedChildren));
        }
        return true;
    }
    
    /**
     * Checks if the tag may not have siblings of the same type. 
     * (ie, the parent may contian only one instance of this tag)
     * @param string $tag
     * @return boolean
     */
    public function isSingleton($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            $bSingleton = false;
            if(array_key_exists('singleton', $rule['validation']))
            {
                $bSingleton = ($rule['validation']['singleton']);
            }        
            return $bSingleton;
        }
        return false;
    }
    
    /**
     * Determines if the tag may contain text directly.  Tags which provide structure generally do not
     * @param string $tag
     * @return boolean
     */
    public function canContainText($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            $bCanContainText = true;
            if(array_key_exists('canContainText', $rule['validation']))
            {
                $bCanContainText = ($rule['validation']['canContainText']);
            }
            $bRequiresChild = false;
            $requiredChildren =$this->requiredChildren($tag);
            if(is_array($requiredChildren) && count($requiredChildren)>0)
            {
                $bRequiresChild = true;
            }
            
            return ($bCanContainText && !$bRequiresChild); // && !$this->isInline($tag));
        }
        return true;
    }
    
    /**
     * returns a list of tags required by the tag, or false if the tag is not valid
     * @param string $tag
     * @return boolean
     */
    public function requiredChildren($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            if(array_key_exists('requiresChild', $rule['validation']))
            {
                return ($rule['validation']['requiresChild']);
            }
        }
        return false;
    }
    
    /**
     * Checks if a tag has any required descendants
     * @param stirng $tag
     * @return boolean
     */
    public function hasRequiredDescendant($tag)
    {
        $req = $this->requiredDescendant($tag);
        return (is_array($req) && count($req)>0);
    }

    /**
     * Returns a list of tags that are required to be descendants of the tag, or false if 
     * the tag is invalid
     * @param string $tag
     * @return array or false
     */
    public function requiredDescendant($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            if(array_key_exists('requiresDescendant', $rule['validation']))
            {
                return ($rule['validation']['requiresDescendant']);
            }
        }
        return false;
    }    
    
    /**
     * Checks if an attribute is required by the tag
     * @param string $tag
     * @param string $attrName
     * @return boolean
     */
    public function requiresAttribute($tag, $attrName)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            if(array_key_exists('requiresAttributes', $rule['validation']))
            {
                return (in_array($attrName, $this->requiredAttributes($tag))); // $rule['validation']['requiresAttributes']));
            }
        }        
    }
    
    public function requiredAttributes($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {        
            if(array_key_exists('requiresAttributes', $rule['validation']))
            {
                return $rule['validation']['requiresAttributes'];
            }        
        }
        return array();
    }
    
    public function allowedAttributes($tag)
    {
        $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {        
            if(array_key_exists('allowsAttributes', $rule['validation']))
            {
                return $rule['validation']['allowsAttributes'];
            }        
        }
        return array();        
    }
    /**
     * Returns true if the tag is an inline tag
     * @param string $tag
     * @return boolean
     */
    public function isInline($tag)
    {
         $rule = $this->getPxeTagRule($tag);
        if(is_array($rule))
        {
            if(array_key_exists('inline', $rule['categories']))
            {
                return ($rule['categories']['inline']=='true');
            }   
        }
        return true;
    }
}
