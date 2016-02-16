<?php

/**
 * StyleClass is a class that holds an information of one CSS class-element,
 * including name, attributes, and a unique hash, in order to
 * make sure a CSS style with reusable classes.
 * @author Avetis Zakharyan
 */
class StyleClass
{

    /**
     * Name of CSS class
     */
    private $name;

    /**
     * Array of attributes of CSS class
     */
    private $attributes;

    /**
     * Hash unique identifier for attribute set
     */
    private $hash;

    /**
     * Hash unique identifier for attribute set
     */
    private $CSSstring;

    /**
     * Inner value used to udentify if hash needs to be just retreived or reclacualted
     */
    private $dirty = false;

    /**
     * Inner value used to udentify if css string needs to be just retreived or reclacualted
     */
    private $css_dirty = true;

    /**
     * Constructor initializes attributes array
     */
    function __construct()
    {
        $this->attribute = array();
        $this->CSSstring = '';
    }

    /**
     * Get class name
     * @return  string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set class name
     * @param   string  Class name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Set CSS attribute
     * @param   string  Key - name of attribute to add, if key already exists, then previous value will be overwritten
     * @param   string  Value - value of attribute
     * @return type
     */
    public function setAttribute($key, $value)
    {
        // Return if value is empty
        if (empty($value)) return;

        // Assign attribute and set it to dirty
        $this->dirty = true;
        $this->attributes[$key] = $value;
    }

    /**
     * Get CSS attributes
     * @return  array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return bool
     */
    public function hasAttributes()
    {
        $hasAttributes = (count($this->attributes) > 0) ? true : false;
        return $hasAttributes;
    }

    /**
     * @param $key
     * @return bool
     */
    public function attributeExists($key)
    {
        $existence = array_key_exists($key, $this->attributes);
        return $existence;
    }

    /**
     * Get CSS attribute value
     * @param   string  Key
     * @return  string
     */
    public function getAttributeValue($key)
    {
        return $this->attributes[$key];
    }

    /**
     * Remove CSS attribute
     * @param   string  Key
     */
    public function removeAttribute($key)
    {
        // Unset attribute by key and set it to dirty
        $this->dirty = true;
        unset($this->attributes[$key]);
    }

    /**
     * Get unique hash to this particular attribute hashset
     * @return  string
     */
    public function getHash()
    {
        // If object is dirty then we need to recalculate the hash value according to new attribute set
        if ($this->dirty) {

            // Create hash
            ksort($this->attributes);
            $serialized = serialize($this->attributes);
            $this->hash = md5($serialized);

            // Set dirty to false
            $this->dirty = false;
        }

        // Return hash
        return $this->hash;
    }

    /**
     * @param $styleToMerge
     * @return StyleClass
     */
    public function mergeStyleClass($styleToMerge)
    {
        if (is_a($styleToMerge, 'StyleClass')) {
            $mergedStyle = new StyleClass();
            $attributes = $this->getAttributes();
            if (count($attributes) > 0) {
                foreach ($attributes as $key => $attribute) {
                    $mergedStyle->setAttribute($key, $attribute);
                }
            }
            $mergeAttributes = $styleToMerge->getAttributes();
            if(!is_null($mergeAttributes)) {
                foreach ($mergeAttributes as $merge_key => $mergeAttribute) {
                    if (!$this->attributeExists($merge_key)) {
                        $mergedStyle->setAttribute($merge_key, $mergeAttribute);
                    }
                }
            }
        } else {
            $mergedStyle = new StyleClass();
        }
        return $mergedStyle;
    }

    /**
     * Get the final CSS string
     * @return  string
     */
    public function getCSS()
    {
        // Check if css is dirty
        if ($this->css_dirty) {

            // Open class definition
            $CSSClass = '.' . $this->name . " { \n";

            // Check and add attributes to the class
            if ($this->attributes) {
                foreach ($this->attributes as $key => $value) {
                    $CSSClass .= "	" . $key . ':' . $value . "; \n";
                }
            }

            // Finalize class
            $CSSClass .= "}";
            $this->CSSstring = $CSSClass;
            $this->css_dirty = false;
        }

        // Return CSS
        return $this->CSSstring;
    }
}
