<?php
/**
 * @package /app/Import/Idml/Pxe
 * 
 * @class   IdmlPxeProducer
 * 
 * @description Adds pxe attributes to elements before html parsing
 *                  
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlVisitor',    'Import/Idml');
App::uses('PxeRules',       'Import/Idml/Pxe');
App::uses('IdmlPxeHelper',  'Import/Idml/Pxe');


class IdmlPxeProducer implements IdmlVisitor
{    
    public $pxeRules = null;
    
    public $parentStack = array();
    
    private $bodyHash = '';
    
    private $chapterHash = '';
    
    private $matterHash = '';
    
    private $currentStoryID = '';
    
    private $ignoredPxeTags = array('story','chaucerhidden','chaucereditorbreak', 'etmfile', 'section.chapter',
                                    'table','thead','th','tbody', 'tr', 'td', 'tfoot', 'cell', 'row');
    
    /**
     * Sets PXE attributes on the IdmlElement object
     * @param IdmlElement $element
     * @param type $styleName
     */
    public function setPxeAttributes(IdmlElement $element, $pxeTag, $pxeParents, $hashTag)
    {
        if($this->shouldProcessElement($element))
        {        
            if(strlen($pxeTag)>0)
            {           
                if(strpos($pxeTag, '.')>0)
                {
                    $tagParts = explode('.',$pxeTag,2);
                    $tagName = $tagParts[0];
                    $className = $tagParts[1];
                }
                else
                {
                    $tagName = $pxeTag;
                    $className = "";
                }
                IdmlPxeHelper::setPxeTag($element, $tagName);
                if(strlen($className))
                {
                    IdmlPxeHelper::setPxeClass($element, $className);
                }           
                    
                IdmlPxeHelper::setPxeParents($element, $pxeParents);
                if(!is_null($hashTag))
                {
                    IdmlPxeHelper::setPxeHash($element, $hashTag);
                }
            }
        }
    }   
    
    /**
     * Returns the current (closest) parent tag from the parent stack without removing it
     * @return string
     */
    public function getParentTag()
    {
        if(count($this->parentStack)>0)
        {
            return $this->parentStack[count($this->parentStack)-1];
        }
        return "";
    }
    
    /**
     * checks if the current parent tag is $tag.  Will match the class is $tag has a class associated
     * in tag.class format, or will match any tag if there is no class included
     * @param string $tag
     * @return boolean
     */
    public function isParentTag($tag)
    {
        $tag = IdmlPxeHelper::stripHash($tag);
        $parentTag = IdmlPxeHelper::stripHash($this->getParentTag());
        if(!strpos($tag, "."))
        {
            $parentTag = IdmlPxeHelper::stripClass($parentTag);
        }
        return ($parentTag==$tag);
    }
    
    /**
     * Finds the first parent tag that matches any tag in the $tags array and returns it as a string
     * @param array $tags
     * @return string
     */
    public function findFirstParent($tags)
    {
        $currentParent = count($this->parentStack)-1;
        while($currentParent>-1)
        {
             $parentTag = IdmlPxeHelper::stripHash($this->parentStack[$currentParent]);
             if(in_array($parentTag, $tags))
             {
                 return $parentTag;
             }
             $currentParent--;
        }
        return "";
    }
    
    /**
     * Returns the closest IdmlXmlElement parent object that has a pxe tag associated with it
     * @param IdmlElement $element
     * @return IdmlXmlElement
     */
    protected function getPxeParent(IdmlElement $element)
    {
        $parent = $element->parentIdmlObject();
        $pxeParent = null;
        while(is_object($parent) && !is_object($pxeParent))
        {
            if(is_a($parent,"IdmlXmlElement"))
            {
                if(array_key_exists("data-pxe-tag",$parent->attributes))
                {
                    $pxeParent = $parent;
                }
            }
            $parent = $parent->parentIdmlObject();
        }
        return $pxeParent;
    }    
    
    /**
     * Checks if one of the tags in array $requiredParents is on the parent stack
     * @param array $requiredParents
     * @return boolean
     */
    public function parentExists($requiredParents)
    {
        if(count($requiredParents)>0)
        {
            foreach($requiredParents as $parentTag)
            {
                if($this->isParentTag($parentTag))
                {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * In cases where a pxe tag can only contain one instance of a child tag,
     * we store the hash of that tag in the element temporarily, so if we encounter
     * tags that belong inside the single instance tag, we can use it on subsequent
     * child elements
     * @param IdmlElement $element
     * @param string $childTag
     * @return string
     */
    public function getSingletonHash($element, $childTag)
    {
        $childHash = "";
        if(array_key_exists("_pxeSingletons", $element->attributes))
        {
            if(array_key_exists($childTag, $element->attributes["_pxeSingletons"]))
            {
                $childHash = $element->attributes["_pxeSingletons"][$childTag];
            }
        }
        if(!strlen($childHash))
        {
            $childHash = IdmlPxeHelper::getNewTagHash();
            $element->attributes["_pxeSingletons"][$childTag] = $childHash;
        }        
        return $childHash;
    }
    
    /**
     * Responsible for adding tags to the parent stack.  
     * - If the tag being added requires a parent, that parent will be pushed onto the stack first
     * - If the tag being added is not allowed by the parent, the list of allowed children of the parent
     * are searched until one is found that may contain the tag being added.
     * @param string $idmlTag
     * @param string $hashTag
     * @param IdmlElement $element element being processed
     */
    public function pushParentTag($idmlTag, $hashTag, IdmlElement $element)
    {
        if($this->pxeRules->isPxeTag($idmlTag))
        {
            // check if this tag always requires a parent and set it
            $requiredParent = $this->pxeRules->getRequiredParent($idmlTag);
            if(is_array($requiredParent))
            {
                if(!$this->parentExists($requiredParent))
                {                    
                    $this->pushParentTag($requiredParent[0], IdmlPxeHelper::getNewTagHash(), $element);
                }
            }
            if(!$this->pxeRules->isChildAllowed($this->getParentTag(), $idmlTag))
            {
                $allowedChildren = $this->pxeRules->getAllowedChildren($this->getParentTag());
                $pxeParent = $this->getPxeParent($element);
                foreach($allowedChildren as $childTag)
                {
                    if($this->pxeRules->isChildAllowed($childTag, $idmlTag))
                    {
                        $childHash = "";                        
                        if($this->pxeRules->isSingleton($childTag) && is_object($pxeParent))
                        {
                           $childHash = $this->getSingletonHash($pxeParent, $childTag);
                        }
                        else
                        {
                            $childHash = IdmlPxeHelper::getNewTagHash();
                        }
                        $this->pushParentTag($childTag, $childHash, $element);
                        break;
                    }
                }
            }
            if(strlen($idmlTag)>0)
            {
                array_push($this->parentStack, $idmlTag."#".$hashTag);
            }
        }
    }
    
    /**
     * Performs a string replacement, but only if the 2 strings begin the same
     * @param string $currentParent
     * @param string $oldParentString
     * @param string $newParentString
     * @return string
     */
    protected function replaceParentString($currentParent, $oldParentString, $newParentString)
    {
        if(substr($currentParent, 0, strlen($oldParentString))== $oldParentString)
        {
            $currentParent = $newParentString.substr($currentParent,strlen($oldParentString));
        }
        return $currentParent;
    }

    /**
     * When a parent element is changed, it is necessary to walk down the children and update their
     * pxe parents attribute accordingly, if they have already been processed
     * @param IdmlElement $element
     * @param string $oldParentString
     * @param string $newParentString
     */
    public function updateElementParents(IdmlElement $element, $oldParentString, $newParentString)
    {
        if(array_key_exists('data-pxe-parents', $element->attributes))
        {
            $element->attributes['data-pxe-parents'] = $this->replaceParentString($element->attributes['data-pxe-parents'], 
                                                                                  $oldParentString, $newParentString);
        }
        if(count($element->childrenElements)>0)
        {
            foreach($element->childrenElements as $child)
            {
                $this->updateElementParents($child, $oldParentString, $newParentString);
            }
        }
    }

    /**
     * Moves the element's pxe tag, class, and hash into the parents attribute and assigns 
     * a new pxe tag to the element.  The new pxe tag is pushed onto the parent stack
     * @param IdmlElement $element
     * @param string $newTag
     * @param string $newHash
     */
    public function pushTagToElement(IdmlElement $element, $newTag, $newHash)
    {
        $currentTag = IdmlPxeHelper::getPxeTag($element); 
        if(IdmlPxeHelper::hasPxeClass($element)) 
        {
            $currentClass = IdmlPxeHelper::getPxeClass($element); 
            if(strlen($currentClass))
            {
                $currentTag .= ".".$currentClass;
            }
        }
        $currentHash = $element->attributes["data-pxe-hash"];
        $parentList = IdmlPxeHelper::getPxeParents($element)." ".$currentTag."#".$currentHash;
        if(strpos($newTag,"."))
        {
            List($tagName, $tagClass) = explode(".",$newTag);
        }
        else
        {
            $tagName = $newTag;
            $tagClass = "";
        }
        IdmlPxeHelper::setPxeTag($element, $tagName); 
        IdmlPxeHelper::setPxeClass($element, $tagClass); 
        $element->attributes["data-pxe-hash"] = $newHash;
        IdmlPxeHelper::setPxeParents($element, trim($parentList));
        $this->pushParentTag($newTag, $newHash, $element);
        
        //Apply this change to all the child elements
        if(count($element->childrenElements)>0)
        {
            $newChildParentList = $parentList.' '.  IdmlPxeHelper::getFullPxeTag($element);
            foreach($element->childrenElements as $child)
            {
                $this->updateElementParents($child, $parentList, $newChildParentList);
            }
        }
    }   
    
    /**
     * ensures that a section.bodymatter is present in the structure following the section.chapter tag.
     * It assumes the verifyChapterSectionTag method has already been called.  Does not add a tag if any of the
     * section.*matter tags exist
     */
    public function verifySectionMatter()
    {
        if(!strlen($this->matterHash))
        {
            $this->matterHash = IdmlPxeHelper::getNewTagHash();
        }        

        if(count($this->parentStack)<3)
        {
            array_push($this->parentStack, "section.bodymatter#".$this->matterHash);
        }
        elseif(strlen($this->findFirstParent(array("section.bodymatter", "section.frontmatter","section.backmatter")))==0)
        {
            $this->parentStack = array_merge(
                                    array_slice($this->parentStack, 0,1),
                                    array("section.bodymatter#".$this->chapterHash),
                                    array_slice($this->parentStack,1)
                    );
        }        
    }
    
    /**
     * verifies the parent list has a section.chapter after the body tag.  This method assumes verifyBodyTag has already been called on this string
     */
    public function verifyChapterSectionTag()
    {
        if(!strlen($this->chapterHash))
        {
            $this->chapterHash = IdmlPxeHelper::getNewTagHash();
        }               
        if(count($this->parentStack) < 2 )
        {
            array_push($this->parentStack, "section.chapter#".$this->chapterHash);
        }
        elseif(substr($this->parentStack[1],0,15) != "section.chapter")
        {
            $this->parentStack = array_merge(
                                    array_slice($this->parentStack, 0,1),
                                    array("section.chapter#".$this->chapterHash),
                                    array_slice($this->parentStack,1)
                    );
        }
    }
    
    /**
     * Ensures the list of parents begins with a body.bodymatter tag 
     */
    public function verifyBodyTag()
    {
        if(!strlen($this->bodyHash))
        {
            $this->bodyHash = IdmlPxeHelper::getNewTagHash();
        }        
        if(count($this->parentStack)==0)
        {
            array_push($this->parentStack, "body.bodymatter#".$this->bodyHash);
        }
        elseif(substr($this->parentStack[0],0,5) != "body.")
        {
            $bodyStr = "body.bodymatter#".$this->bodyHash;
            $this->parentStack = array_merge(array($bodyStr),$this->parentStack);
        }
    }
    
    /**
     * Ensures the dom parent list has all the required structure
     */
    public function verifyParentStructure()
    {
        $this->verifyBodyTag();
        $this->verifyChapterSectionTag();
        $this->verifySectionMatter();        
    }
    
    /**
     * Stores the number of elements currently on the parent stack in the elment, for resetting later
     * @param IdmlElement $element
     */
    private function stashParentCount($element)
    {
        $element->attributes["_pxeParentCount"] = count($this->parentStack);
    }
    
    /**
     * restores the parent stack to the state stored in the element. (The stack count is stored, not the whole stack)
     * @param IdmlElement $element
     */
    private function restoreParentStack($element)
    {
        if(array_key_exists("_pxeParentCount", $element->attributes))
        {
            while(count($this->parentStack)>$element->attributes["_pxeParentCount"] && count($this->parentStack)>0)
            {
                array_pop($this->parentStack);        
            }
            unset($element->attributes["_pxeParentCount"]);                
        }
        if(array_key_exists("_pxeSingletons", $element->attributes))
        {
            unset($element->attributes["_pxeSingletons"]);
        }
    }
            
    /**
     * Returns the hash value assigned to the element, if it has one, or an empty string
     * @param IdmlElement $element
     * @return string
     */
    private function getElementHashTag($element)
    {
        if(array_key_exists("data-pxe-hash", $element->attributes) && strlen($element->attributes["data-pxe-hash"])>0)
        {
            return $element->attributes["data-pxe-hash"];
        }        
        elseif(strlen($element->idmlTag)>0)
        {
            if(strpos($element->idmlTag,'#'))
            {
                return substr($element->idmlTag,strpos($element->idmlTag,'#'));
            }
        }
        return "";
    }
    
    /**
     * Returns the index of the element in the parent stack which has the passed in hash value
     * or -1 if not found
     * @param string $hashValue
     * @return int
     */
    private function findParentByHash($hashValue)
    {
        for($x=count($this->parentStack)-1;$x>-1;$x--)
        {
            if(strpos($this->parentStack[$x], '#'.$hashValue)>0)
            {
                return $x;
            }
        }
        return -1;
    }        

    /**
     * Returns the number of parent PXE tags the element has. 
     * if $bContainerOnly is true, only tags that are containers are counted
     * @param IdmlElement $element
     * @param bool $bContainerOnly
     * @return int
     */
    protected function numPxeElementChildren($element, $bContainerOnly=false)
    {
        if(count($element->childrenElements)>0)
        {
            $num = 0;
            foreach($element->childrenElements as $el)
            {
                if(is_a($el,"IdmlXmlElement"))
                {
                    $tagName = str_replace("XMLTag/", "", $el->markupTag);        
                    if(strlen($tagName) && !in_array($tagName, $this->ignoredPxeTags)) 
                    {                
                        if(($bContainerOnly && $this->pxeRules->canContainText($tagName) && !$this->pxeRules->isInline($tagName)) 
                                || $bContainerOnly==false)
                        {
                            $num++;
                        }
                    }
                }
            }
            return $num;
        }
        return 0;        
    }
    
    /**
     * Returns the number of children PXE tags the element has. 
     * @param IdmlElement $element
     * @return int
     */
    protected function numPxeElementDecendents($element)
    {
        $numElements = $this->numPxeElementChildren($element);
        foreach($element->childrenElements as $child)
        {
            $numElements += $this->numPxeElementChildren($child);
        }
        return $numElements;
    }
    
    /**
     * Returns the number of children PXE tags the element has which are structural tags. 
     * @param IdmlElement $element
     * @return int
     */    
    protected function numPxeElementContainerDecendents($element)
    {
        $numElements = $this->numPxeElementChildren($element, true);
        foreach($element->childrenElements as $child)
        {
            $numElements += $this->numPxeElementChildren($child, true);
        }
        return $numElements;        
    }


    /**
     * Called when the visiting of elements begins, this loads the pxe definition json object
     * 
     * @param IdmlAssembler $item
     * @param type $depth
     */
    public function visitAssembler(IdmlAssembler $item, $depth = 0) 
    {
        $this->pxeRules = new PxeRules();
        $item->pxeTagsAdded = true;     
        
    }
    
    public function visitAssemblerEnd(IdmlAssembler $item, $depth = 0) 
    {
    }
    
    /**
     * Sets the element ID if required by the PXE tag assigned and isnt already set.
     * Uses the element's hash value if it has one, or uses a brand new hash value if necessary
     * @param IdmlElement $element
     */
    public function assignIdAttribute(IdmlElement $element)
    {
        if(strlen($element->idAttribute)==0 && 
           $this->pxeRules->requiresAttribute(IdmlPxeHelper::getPxeTag($element), "id"))
        {
            if(IdmlPxeHelper::hasHashValue($element))
            {
                $element->idAttribute = $this->getElementHashTag($element);
            }
            else
            {
                $element->idAttribute = IdmlPxeHelper::getNewTagHash();
            }
        }
    }
    
    public function elementXmlContent(IdmlElement $element)
    {
        $xmlContent = null;
        $parentElement = $element;
        while(!strlen($xmlContent) && is_object($parentElement))
        {
            if(is_a($element,"IdmlXmlElement"))
            {
                $xmlContent = $parentElement->xmlContent;
                $parentElement = false;
            }
            else
            {
                $parentElement = $parentElement->parentIdmlObject();
            }
        }
        return $xmlContent;
    }
    
    /**
     * Assigns the element (usually an IdmlXmlElement) with the pxe tag $tagname and hash value $hashTag.
     * this tag is also pushed onto the parent stack
     * @param IdmlElement $element
     * @param string $tagName
     * @param string $hashTag
     */
    private function assignPxeTag($element, $tagName, $hashTag="")
    {
        if(!strlen($hashTag))
        {
            if(strlen($tagName)>0 && !IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($tagName))
            {
                $hashTag = IdmlPxeHelper::getNewTagHash();
            }      
            elseif(IdmlPxeHelper::hasHashValue($element))
            {
                $hashTag = $this->getElementHashTag($element);
            }            
        }
        if($this->pxeRules->isPxeTag($tagName))
        {        
            $parentIndex = $this->findParentByHash($hashTag);
            if($parentIndex>-1)
            {
                $this->parentStack[$parentIndex] = $tagName.'#'.$hashTag;
            }

            if($parentIndex==-1)
            {
                $this->pushParentTag($tagName, $hashTag, $element);
            }
            $parents = implode(' ', $this->parentStack);            
            //remove the current element from the parent list, if necessary
            $parents = trim(str_replace($tagName."#".$hashTag, "", $parents));   
            $this->setPxeAttributes($element, $tagName, $parents, $hashTag);             
            $element->idmlTag = $tagName;
            $this->assignIdAttribute($element);
        }                  
    }
        
    /**
     * this logic is needed in more than one location since the hierarchy is being navigated and used in several ways
     * at this point it only filters out XMLElements that are really just duplicate structures that sometimes get created
     * when Tags are applied in an Indesign document
     * @param IdmlElement $element
     * @return boolean
     */
    private function shouldProcessElement(IdmlElement $element)
    {
        $elementType = IdmlParserHelper::getIdmlObjectType($element);
        if (($elementType != 'XmlElement') ||
            ($elementType == 'XmlElement' && (!$element->xmlContent || $element->xmlContent == $this->currentStoryID ))
        )
        { 
           return true;
        }
        else {  
           return false;
        }
    }    
    
    public function clearParentElementWithHash(IdmlElement $element, $parentHashValue)
    {
        $bCleared = false;
        $parentElement = $element->parentIdmlObject();
        while(!$bCleared && is_object($parentElement))
        {
            if(IdmlPxeHelper::getPxeHash($parentElement)==$parentHashValue)
            {
                IdmlPxeHelper::clearPxeData($parentElement);
                $bCleared = true;
            }
            $parentElement = $parentElement->parentIdmlObject();
        }
        return $bCleared;
    }
    
    public function handleTagSectionFrontmatter(IdmlXmlElement $element)
    {
        $this->verifyParentStructure();
        $this->parentStack[0] = "body.frontmatter#".$this->bodyHash;
        $this->parentStack[2] = "section.frontmatter#".$this->matterHash;
    }
    
    public function handleTagSectionBackmatter(IdmlXmlElement $element)
    {
        $this->verifyParentStructure();
        $this->parentStack[0] = "body.backmatter#".$this->bodyHash;
        $this->parentStack[2] = "section.backmatter#".$this->matterHash;
    }    
    
    public function handleTagSectionBodymatter(IdmlXmlElement $element)
    {
        $this->verifyParentStructure();
        $this->parentStack[0] = "body.bodymatter#".$this->bodyHash;
        $this->parentStack[2] = "section.bodymatter#".$this->matterHash;
    }        
    
    public function handleTagBodyFrontmatter(IdmlXmlElement $element)
    {
       $this->verifyParentStructure(); 
       $this->parentStack[0] = "body.frontmatter#".$this->bodyHash;
       $this->parentStack[2] = "section.frontmatter#".$this->matterHash;       
    }
    
    public function handleTagBodyBodymatter(IdmlXmlElement $element)
    {
       $this->verifyParentStructure(); 
       $this->parentStack[0] = "body.bodymatter#".$this->bodyHash;
       $this->parentStack[2] = "section.bodymatter#".$this->matterHash;       
    }    
    
    public function handleTagBodyBackmatter(IdmlXmlElement $element)
    {
       $this->verifyParentStructure(); 
       $this->parentStack[0] = "body.backmatter#".$this->bodyHash;
       $this->parentStack[2] = "section.backmatter#".$this->matterHash;       
    }        
    
    public function tagHandlerMethodName($tag)
    {
        $result = "handleTag";
        $tag = strtolower($tag);
        if(strpos($tag,".") !== false)
        {
            $parts = explode(".",$tag);
            $result .= ucfirst($parts[0]).ucfirst($parts[1]);
        }
        else
        {
            $result .= ucfirst($tag);
        }
        return $result;
    }
    
    /**
     * copies attributes from the xmlAttributes property of the IdmlXmlElement to the
     * attributes property if the attribute is listed in the json rules
     * @param IdmlXmlElement $element
     */
    public function handleXmlAttributes(IdmlXmlElement $element)
    {
        $allowedAttribs = array_merge(
                $this->pxeRules->requiredAttributes($element->idmlTag),
                $this->pxeRules->allowedAttributes($element->idmlTag)
                );
        if(count($allowedAttribs)>0)
        {
            foreach($element->xmlAttributes as $name=>$value)
            {
                if(in_array($name, $allowedAttribs))
                {
                    $element->attributes[$name] = $value;
                }
            }
        }
    }
            
    /**
     * Processing of IdmlXmlElements.  This is the primary procesing engine for PXE tags
     * @param IdmlXmlElement $element
     * @param int $depth
     * @return null
     */
    public function visitXmlElement(IdmlXmlElement $element, $depth = 0) 
    {        
        if($this->shouldProcessElement($element))
        {        
            $xmlContent = $this->elementXmlContent($element);
            if(strlen($xmlContent)>0 && strlen($this->currentStoryID)>0 && $xmlContent != $this->currentStoryID)
            {
                return;
            }
            if($xmlContent=='u615c' || $xmlContent=='u4d93')
            {
                $a=1;
            }
            $tagName = strtolower(str_replace("XMLTag/", "", $element->markupTag));
            if (in_array($tagName, $this->ignoredPxeTags))                 
            {
                return;
            }
            
            $tagMethod = $this->tagHandlerMethodName($tagName);
            if(method_exists($this, $tagMethod))
            {
                $this->$tagMethod($element);
                return;
            }
            $this->verifyParentStructure();
            $pxeParent = $this->getPxeParent($element);
            if(is_object($pxeParent))
            {
                $parentHash = $this->getElementHashTag($pxeParent);
                if(strlen($parentHash))
                {
                    $hashIndex = $this->findParentByHash($parentHash);
                    if($hashIndex<0)
                    {
                        $singletonTagName = IdmlPxeHelper::stripHash(IdmlPxeHelper::getFullPxeTag($pxeParent));
                        if($this->pxeRules->isSingleton($tagName))
                        {
                            $this->attributes["_pxeSingletons"][$singletonTagName] = $parentHash;
                        }
                        $this->pushParentTag($singletonTagName, $parentHash, $element);
                    }
                }
            }
            $this->stashParentCount($element);
            
            if(!strlen($tagName))
            {
                return;
            }        


            if(strlen($tagName)>0 && !IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($tagName))
            {
                $hashTag = IdmlPxeHelper::getNewTagHash();
            }      
            elseif(IdmlPxeHelper::hasHashValue($element))
            {
                $parts = explode('#', $tagName);            
                $hashTag = $parts[1];
            }       
            else
            {
                CakeLog::debug("[IdmlPxeProducer::visitXmlElement] Unable to process tag $tagName. This tag is not valid pxe, and is not in the ignore list");
                return;            
            }

            /* element requires descendants */
            if($this->pxeRules->hasRequiredDescendant($tagName))
            {
                $this->pushParentTag($tagName, $hashTag, $element); 
                return;
            }

            /* element has pxe descendants */
            if($this->numPxeElementContainerDecendents($element)>0)
            {
                $this->pushParentTag($tagName, $hashTag, $element);
                return;
            }
            
            $childHash = "";
            /* element is a singleton and already exists in the parent */
            if($this->pxeRules->isSingleton($tagName) && is_object($pxeParent))
            {
                $childHash = $this->getSingletonHash($pxeParent, $tagName);                     
            }
            $this->assignPxeTag($element, $tagName, $childHash);
        }        
    }
    
    public function visitXmlElementEnd(IdmlXmlElement $element, $depth = 0) 
    {
       $this->restoreParentStack($element); 
    }
       
    public function visitHyperlink(IdmlHyperlink $element, $depth = 0) 
    {
        $this->verifyParentStructure();
        $this->stashParentCount($element);
        $hashTag = null;
        $parentTag = $this->getParentTag();
        if(strlen($parentTag)>0 && substr($parentTag,0,2)=='a.')
        {
            $element->idmlTag = $parentTag;            
            array_pop($this->parentStack);
        }
        elseif(!strlen($element->idmlTag))
        {
            $element->idmlTag = "a";
        }
        if(strlen($element->idmlTag)>0 && !IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($element->idmlTag))
        {
            $hashTag = IdmlPxeHelper::getNewTagHash();
        }      
        elseif(IdmlPxeHelper::hasHashValue($element))
        {
            $parts = explode('#', $element->idmlTag);
            $element->idmlTag = $parts[0];
            $hashTag = $parts[1];
        }
        if(strlen($hashTag)>0)
        {
            $pxeAttribs = array();
            if(strlen($element->href)>0)
            {
                $pxeAttribs['href'] = $element->href;            
            }
            if(strlen($element->name)>0)
            {
                $pxeAttribs['name'] = $element->name;
            }            
            $element->href="";
            $element->name="";
            $this->pushParentTag($element->idmlTag, $hashTag, $element);          
            $parents = implode(' ', array_slice($this->parentStack, 0, count($this->parentStack)-1));          
            $this->setPxeAttributes($element, $element->idmlTag, $parents, $hashTag);  
            IdmlPxeHelper::setPxeAttributes($element, $pxeAttribs);
            $this->clearParentElementWithHash($element, $hashTag);
        }
    }
    
    public function visitHyperlinkEnd(IdmlHyperlink $element, $depth = 0) 
    {
        $this->restoreParentStack($element);
    }    
    
    public function visitImage(IdmlImage $element, $depth = 0) 
    {
        $this->verifyParentStructure();
        if(!strlen($element->idmlTag))
        {
            $element->idmlTag = "img";
        }
        if(strlen($element->idmlTag)>0 && !IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($element->idmlTag))
        {
            $hashTag = IdmlPxeHelper::getNewTagHash();
        }      
        elseif(IdmlPxeHelper::hasHashValue($element))
        {
            $parts = explode('#', $element->idmlTag);
            $element->idmlTag = $parts[0];
            $hashTag = $parts[1];
        }
        $parents = implode(' ', $this->parentStack);          
        $this->setPxeAttributes($element, $element->idmlTag, $parents, $hashTag);          
    }      
    
    public function visitContent(IdmlContent $element, $depth = 0)
    {       
        $this->verifyParentStructure();
        if(!$element->hasContent())
        {
            //skip empty content tags
            return;
        }        
        $parentTag = $this->getParentTag();
        $pxeParent = $this->getPxeParent($element);
        if(!$this->pxeRules->canContainText($parentTag) || !is_object($pxeParent))
        {            
            if($pxeParent)
            {
                $this->pushTagToElement($pxeParent, "p", IdmlPxeHelper::getNewTagHash());
            }
            else
            {
                $parents = implode(' ', $this->parentStack); 
                $this->setPxeAttributes($element, "span", $parents, IdmlPxeHelper::getNewTagHash());
            }
        }        
    }

    public function visitContentEnd(IdmlContent $idmlContent, $depth = 0)
    {
    }
    
    public function visitText(IdmlContent $idmlContent, $depth = 0)
    {
    }

    public function visitProcessingInstruction(IdmlContent $idmlContent, $depth = 0)
    {
    }

    public function visitTable(IdmlTable $element, $depth = 0)
    {
        $this->verifyParentStructure();
        $this->stashParentCount($element);
        $hashTag = null;
        if(!strlen($element->idmlTag))
        {
            $element->idmlTag = "table";
        }
        if(!IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($element->idmlTag))
        {
            $hashTag = IdmlPxeHelper::getNewTagHash();
        }      
        elseif(IdmlPxeHelper::hasHashValue($element))
        {
            $parts = explode('#', $element->idmlTag);
            $element->idmlTag = $parts[0];
            $hashTag = $parts[1];
        }
        $this->pushParentTag($element->idmlTag, $hashTag, $element);          
        $parents = implode(' ', array_slice($this->parentStack, 0, count($this->parentStack)-1));          
        $this->setPxeAttributes($element, $element->idmlTag, $parents, $hashTag);          
    }
    
    public function visitTableEnd(IdmlTable $element, $depth = 0)
    {
        $this->restoreParentStack($element);
    }        
    
    public function visitTableRow(IdmlTableRow $element, $depth = 0)
    {
        $this->verifyParentStructure();
        $this->stashParentCount($element);
        if($this->findFirstParent(array($element->rowType,"table"))=="table")
        {
            $rowParent = $this->findFirstParent(array("thead","tbody","tfoot"));
            if(strlen($rowParent) && $rowParent != $element->rowType)
            {
                array_pop($this->parentStack);
            }
            if($rowParent != $element->rowType)
            {
                $this->pushParentTag($element->rowType,  IdmlPxeHelper::getNewTagHash(), $element);
                $this->stashParentCount($element);
            }
        }
        $hashTag = null;
        if(!strlen($element->idmlTag))
        {
            $element->idmlTag = "tr";
        }
        if(!IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($element->idmlTag))
        {
            $hashTag = IdmlPxeHelper::getNewTagHash();
        }      
        elseif(IdmlPxeHelper::hasHashValue($element))
        {
            $parts = explode('#', $element->idmlTag);
            $element->idmlTag = $parts[0];
            $hashTag = $parts[1];
        }
        $this->pushParentTag($element->idmlTag, $hashTag, $element);      
        $parents = implode(' ', array_slice($this->parentStack, 0, count($this->parentStack)-1));          
        $this->setPxeAttributes($element, $element->idmlTag, $parents, $hashTag);           
    }
    
    public function visitTableRowEnd(IdmlTableRow $element, $depth = 0)
    {
        $this->restoreParentStack($element);
    }    
        
    public function visitTableCell(IdmlTableCell $element, $depth = 0)
    {
        $this->verifyParentStructure();
        $this->stashParentCount($element);
        $hashTag = null;
        if(!strlen($element->idmlTag))
        {
            if($this->findFirstParent(array("thead","tbody","tfoot"))=="thead")
            {
                $element->idmlTag = "th";
            }
            else 
            {
                $element->idmlTag = "td";
            }
        }
        if(!IdmlPxeHelper::hasHashValue($element) && $this->pxeRules->isPxeTag($element->idmlTag))
        {
            $hashTag = IdmlPxeHelper::getNewTagHash();
        }      
        elseif(IdmlPxeHelper::hasHashValue($element))
        {
            $parts = explode('#', $element->idmlTag);
            $element->idmlTag = $parts[0];
            $hashTag = $parts[1];
        }
        $this->pushParentTag($element->idmlTag, $hashTag, $element);     
        $parents = implode(' ', array_slice($this->parentStack, 0, count($this->parentStack)-1));           
        $this->setPxeAttributes($element, $element->idmlTag, $parents, $hashTag);         
    }
    
    public function visitTableCellEnd(IdmlTableCell $element, $depth = 0)
    {
        $this->restoreParentStack($element);
    }    
    
    public function visitTextFrame(IdmlTextFrame $element, $depth = 0)
    {
        /* find the parent and if it is a character range, drop that from the parent list */
        $parentElement = $element->parentIdmlObject();
        if(is_a($parentElement,"IdmlCharacterRange") && count($this->parentStack)>3)
        {            
           $element->attributes["_pxe_stashed_parent"] = array_pop($this->parentStack); 
        }
    }
    
    public function visitTextFrameEnd(IdmlTextFrame $element, $depth = 0)
    {
        if(array_key_exists("_pxe_stashed_parent",$element->attributes))
        {
            array_push($this->parentStack,$element->attributes["_pxe_stashed_parent"]);
            unset($element->attributes["_pxe_stashed_parent"]);
        }
    }

    public function visitEmbeddedTextFrame(IdmlTextFrame $element, $depth = 0)
    {
    }

    public function visitStory(IdmlStory $item, $depth = 0) 
    {
        $this->currentStoryID = $item->UID;
    }
    
    public function visitStoryEnd(IdmlStory $item, $depth = 0) 
    {
        $this->currentStoryID = "";
    }      

    public function visitParagraphRange(IdmlParagraphRange $element, $depth = 0) {}        
    public function visitParagraphRangeEnd(IdmlParagraphRange $element, $depth = 0) {}            
    public function visitCharacterRange(IdmlCharacterRange $element, $depth = 0) {}
    public function visitCharacterRangeEnd(IdmlCharacterRange $element, $depth = 0) {}    
      
  
    public function visitGroup(IdmlGroup $element, $depth = 0) {}
    public function visitGroupEnd(IdmlGroup $element, $depth = 0) {}
    public function visitBrContent(IdmlBrContent $element, $depth = 0) {}
    public function visitTags(IdmlTags $item, $depth = 0){}
    public function visitPackage(IdmlPackage $item, $depth = 0) {}
    public function visitPackageEnd(IdmlPackage $item, $depth = 0) {}
    public function visitSpread(IdmlSpread $item, $depth = 0) {}
    public function visitSpreadEnd(IdmlSpread $item, $depth = 0) {}
    public function visitPage(IdmlPage $item, $depth = 0) {}
    public function visitPageEnd(IdmlPage $item, $depth = 0) {}
    public function visitRectangle(IdmlRectangle $element, $depth = 0) {}
    public function visitRectangleEnd(IdmlRectangle $element, $depth = 0) {}
    public function visitTableColumn(IdmlTableColumn $element, $depth = 0){}
    public function visitTableColumnEnd(IdmlTableColumn $element, $depth = 0){}
    public function visitChange(IdmlChange $element, $depth = 0){}
    public function visitChangeEnd(IdmlChange $element, $depth = 0){}
}
