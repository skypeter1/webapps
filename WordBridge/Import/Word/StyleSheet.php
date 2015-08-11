<?php

include_once "StyleClass.php";

/**
* StyleSheet is a class that holds an entire syle sheet information, which is mainly an array of StyleClass-es
* They main point of having this class is avoiding style class duplicates, in order to have a cleaner CSS structure.
* @author Avetis Zakharyan
*/
class StyleSheet{

    /**
     * Array List of StyleClass elements, where the key's are decided by styleClass getHash method
     */
    private $classes;

    private $hashes_array;

    /**
     * Constructor just initiates classes array as an empty array
     */
    function __construct()
    {
        $this->classes = array();
        $this->hashes_array = array();
    }
    
    /**
     * Get friendly class name from hash
     * @param   string  Hash
     * @return  string
     */
    public function getFriendlyClassNameFromHash($hash)
    {
        // Find index of that hash in indexes array
        $index = array_search($hash, $this->hashes_array);		

        // Can change to a better alternative
        $name = "cls_$index";

        // Return class name
        return $name;
    }
    
    /**
     * Get Class name (getClassName takes an antire StyleClass object with it's arguments, if it is unique
     * and was never used before then it will be stored to be reused, and then the style class name will
     * be returned)
     * @param   object  Instance of StyleClass
     * @return  string  Class name
     */
    public function getClassName($classData)
    {
        // Get the hash value
        $hash = $classData->getHash();

        // If this attribute set was not used before - create a class for it
        if (!array_key_exists($hash, $this->classes)) {
            $this->classes[$hash] = $classData;
            $this->hashes_array[] = $hash;
            $this->classes[$hash]->setName($this->getFriendlyClassNameFromHash($hash));
            
            // TODO: think of more user friendly class name generation, instead of using hash
        }
        
        // Return the class name
        return $this->classes[$hash]->getName();
    }
    
    /**
     * Get classes array
     * @return  array
     */
    public function getClassesArray()
    {
        return $this->classes;
    }
    
    /**
     * Get hashes array
     * @return  array
     */
    public function getHashesArray()
    {
        return $this->hashes_array;
    }
    
    /**
     * Generates the final CSS string
     * @return  string
     */
    public function getFinalCSS()
    {
        // Create default body and add the rest of classes
        $string = "body {margin: 0px; padding: 0px;} \n\n";
        foreach ($this->classes as $value) {
            $string .= $value->getCSS() . " \n\n";
        }
        
        // Return CSS
        return $string;
    }

    /**
     * Generates the final pages CSS string
     * @return  string
     */
    public function getPagesCSS()
    {
        // Collect all the classes
        $string = '';
        foreach ($this->classes as $value) {
            $string .= $value->getCSS() . " \n\n";
        }

        // Return CSS
        return $string;
    }
}
