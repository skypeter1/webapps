<?php
/**
 * User: ecass
 * Date: 12/13/13
 * Time: 3:09 PM
 */

App::import('Vendor', 'Chaucer/Common/ChaucerDomDocument');

abstract class AbstractTranslator
{
    /**
     * @var DOMDocument
     */
    protected $doc;

    /**
     * @var DOMNode
     */
    protected $externalNode;

    protected $isNodeCollection = false;

    protected $nodeSet = array();

    /**
     * @var DOMNode
     */
    protected $parentNode;

    /**
     * @var DOMNode
     */
    protected $element;
    /**
     * @var boolean
     */
    protected $keepAttributes;
    protected $className;

    protected $attributes = array();
    protected $tagName = 'div';

    /**
     * @param ChaucerDOMDocument $doc
     * @return AbstractTranslator
     */

    public function setDoc(ChaucerDomDocument $doc)
    {
        $this->doc = $doc;
        return $this;
    }

    public function setParentMap(array $map)
    {
        $this->openMap = $map;
        return $this;
    }

    public function setNode(DOMNode $node)
    {
        if (!$this->parentNode) throw new Exception('Parent Node must be set before actual Node');
        $this->externalNode = $node;

        $this->element = $this->doc->createElement($this->getTagName());

        if ($this->keepAttributes)
        {
            foreach ($this->externalNode->attributes as $attr)
            {
                if ($attr->name == 'class')
                {
                    $this->className = (strlen($this->className))
                        ? $this->className . ' ' . $attr->value
                        : $attr->value;
                }
                elseif ($attr->name == "data-pxe-attributes")
                {
                    $attributes = json_decode($attr->value);
                    foreach ($attributes as $name => $value)
                    {
                        $this->element->setAttribute($name, $value);
                    }
                }
                elseif (substr($attr->name, 0, 8) != 'data-pxe' && substr($attr->name, 0, 7) !== 'data-ca')
                {
                    if (!array_key_exists($attr->name, $this->element->attributes))
                    {
                        $this->element->setAttribute($attr->name, $attr->value);
                    }
                }
            }
        }
        // Keep attribute explicitly asked for by editor regardless of how smart you think you are.
        foreach ($this->attributes as $key => $value)
        {
            if ($this->element->hasAttribute($key))
            {
                $value = $this->element->getAttribute($key)->value . ' ' . $value;
            }
            $this->element->setAttribute($key, $value);
        }

        if ($this->className) $this->element->setAttribute('class', $this->className);
        $this->parentNode->appendChild($this->element);
        return $this;
    }

    public function setParent(DOMNode $parent)
    {
        $this->parentNode = $parent;
        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @var bool $isSet
     * @return AbstractTranslator
     */
    public function setNodeCollection($isSet = true)
    {
        $this->isNodeCollection = $isSet;
        return $this;
    }

    public function isNodeCollection()
    {
        return $this->isNodeCollection;
    }

    public function keepAttributes($boolean)
    {
        $this->keepAttributes = $boolean;
        return $this;
    }

    public function setClass($className)
    {
        // Remove Hash from the name.
        $classParts = explode('#', $className);
        $this->className = $classParts[0];
        return $this;
    }

    public function setTagName($tagName)
    {
        if (strstr($tagName, '{')) {
            $parts = explode('{', $tagName);
            $this->tagName = $parts[0];
        } else {
            $this->tagName = $tagName;
        }
        return $this;
    }

    public function getTagName()
    {
        return $this->tagName;
    }

    abstract public function process();

}