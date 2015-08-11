<?php
/**
 * User: ecass
 * Date: 12/18/13
 * Time: 10:25 AM
 */
App::import('Vendor', 'Chaucer/Common/ChaucerDomDocument');

class IdmlPxeExportParentMap
{
    /**
     * [0]['section'] => 'bodymatter',
     * [1]['figure'] => 'image',
     * [2]['image'] => 'puppy'
     **/
    protected $map = array();
    protected $doc;
    protected $nodeDepth = 0;

    public function __construct(ChaucerDomDocument $doc)
    {
        $this->doc = $doc;
    }

    public function getNode($hash) {
        return isset($this->map[$hash])
            ? $this->map[$hash]
            : false;
    }

    public function getParent($tagDef)
    {
        $def = self::parsePixieDef($tagDef);
        $key = $this->getKeyFromDef($def);
        return $this->getNode($key);
    }

    public function getKeyFromDef(array $def) {
        if (isset($def['hash'])) return $def['hash'];
        $preKey = '';
        foreach ($def as $k => $v) {
            $preKey .= $v;
        }
        return md5($preKey);
    }

    public function setNode($hash, DOMNode $node) {
        $this->map[$hash] = $node;
        return $this;
    }

    public function setParent($tagDef, DOMNode $parent) {
        $def = self::parsePixieDef($tagDef);
        $key = $this->getKeyFromDef($def);
        $this->map[$key] = $parent;
        return $this;
    }

    public static function parsePixieDef($tagDef)
    {
        if (strstr($tagDef, '#')) {
            list($tagDef, $hash) = explode('#', $tagDef);
        } else {
            $hash = null;
        }
        if (strstr($tagDef, '.'))
        {
            list($tagName, $className) = explode('.', $tagDef);
        }
        else
        {
            $tagName = $tagDef;
            $className = null;
        }

        $attributes = array();
        if (preg_match('/{.*}/', $className, $matches))
        {
            $attributes = json_decode($matches[0], true);
            $className = preg_replace('/{.*}/', '', $className);
        }

        return array(
            'tagName' => $tagName,
            'class' => $className,
            'attributes' => $attributes,
            'hash' => $hash,
        );
    }
} 