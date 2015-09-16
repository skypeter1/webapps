<?php
/**
 * User: ecass
 * Date: 12/13/13
 * Time: 11:27 AM
 * Usage:
 * $csm = file_get_contents($argv[1]);
 * $pxe = new IdmlPxeExporter($csm);
 * echo $pxe->process();
 */

App::import('Vendor', 'Chaucer/Common/ChaucerDomDocument');
App::uses('IdmlPxeExportParentMap', 'Import/Idml/Pxe');
App::uses('DefaultTranslator',      'Import/Idml/Pxe/Translators');


class IdmlPxeExporter
{
    const TRANSLATOR_DIRECTORY = '/translators/';
    const DEFAULT_TRANSLATOR = 'DefaultTranslator';
    /**
     * @var ChaucerDomDocument $csmDoc
     */
    protected $csmDoc;
    /**
     * @var ChaucerDomDocument $pxeDoc
     */
    protected $pxeDoc;

    /**
     * Pxe Body element.
     * @var DOMNode
     */
    protected $pxeBody;

    /**
     * @var bool
     */
    protected $isDocument = true;
    /**
     * @var bool
     */
    protected $inSet = false;
    /**
     * @var array
     */
    protected $translatorMap = array();
    /**
     * @var ParentMap
     */
    protected $elementMap;

    /**
     * Constructor
     * @param string $csm HTML Document.
     * @param string $mapFile full path to map file. If empty default one will be used.
     */
    public function __construct($csm = null, $mapFile = null)
    {
        $this->csmDoc = new ChaucerDomDocument;

       if ($mapFile)
        {
            $this->populateMap($mapFile);
        }
        
        if ($csm)
        {
            $this->setCSM($csm);
        }
    }

    /**
     * Set CSM.
     * @param string $csm XML content of page.
     * @return IdmlPxeExporter
     */
    public function setCSM($csm)
    {
        $ok = $this->csmDoc->loadHTML($csm);
        $this->pxeDoc = clone $this->csmDoc;
        $oldBody = $this->pxeDoc->getElementsByTagName('body')->item(0);
        $this->pxeBody = $this->pxeDoc->createElement('body');
        $oldBody->parentNode->replaceChild($this->pxeBody, $oldBody);
        $this->elementMap = new IdmlPxeExportParentMap($this->pxeDoc);

        if($ok === FALSE)
        {
            error_log("WARNING: Unable to load HTML from IdmlPxeExporter::setCSM");
            return;
        }

        $this->csmBody = $this->csmDoc->getElementsByTagName('body')->item(0);
        return $this;
    }

    /**
     * Process the content.
     * 
     * @return IdmlPxeExporter
     */
    public function process()
    {
        if ($this->csmBody->hasChildNodes())
        {
            foreach ($this->csmBody->childNodes as $csmNode)
            {
                if ($csmNode instanceof DOMElement)
                {
                    if ($this->isChaucerNonsense($csmNode)) continue;

                    $this->handleTag($csmNode);
                }
            }
        }
        
        return $this;
    }

    protected function isChaucerNonsense(DOMElement $node) {

        if ($node->hasAttribute('class')) {

            $classes = preg_split('/\s+/', $node->getAttribute('class'));

            if (in_array('chaucer-item', $classes)) {
                if (!$this->itIsAKeeper($classes)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $classes
     * @return bool
     */
    protected function itIsAKeeper($classes) {

        $stuffToKeep = array(
            'chaucer-widget-item',
            'chaucer-shape-item',
            'chaucer-img-item',
            'chaucer-video-item',
            'chaucer-audio-item'
        );

        foreach ($classes as $class) {
            if (in_array($class, $stuffToKeep)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle parents.
     * @param DOMNode $csmNode
     * 
     * @return DOMNode Pxe Parent.
     */
    protected function handleParents(DOMNode $csmNode)
    {
        $pxeParentNode = $this->pxeBody;
        $pxeParent = $this->pxeBody;

        $parents = $this->getAttributeValues($csmNode, 'data-pxe-parents');
        foreach ($parents as $parent)
        {
            if (substr(strtolower($parent), 0, 4) == 'body')
            {
                $this->handleBody($parent);
                continue;
            }

            $pxeParent = $this->elementMap->getParent($parent);
            $pxeParentNode = ($pxeParent) ? $pxeParent : $pxeParentNode;
            if (!$pxeParent)
            {
                $pxeParent = $this->applyTranslator($csmNode, $pxeParentNode, $parent, false);
                $nodeName = $pxeParentNode->nodeName;
                $nodeVal = $pxeParentNode->nodeValue;

                $this->elementMap->setParent($parent, $pxeParent);
                $pxeParentNode = $pxeParent;
            }
        }
        
        return $pxeParent;
    }

    /**
     * Handle body.
     * 
     * @param type $def
     * @return none
     *
     * @throws Exception
     */
    protected function handleBody($def)
    {
        $def = IdmlPxeExportParentMap::parsePixieDef($def);
        if (strtolower($def['tagName']) != 'body')
        {
            throw Exception('handling body that is not body. How did you get here?');
        }
        $this->pxeBody->setAttribute('class', $def['class']);
    }

    /**
     * Handle tag.
     *
     * @param DOMNode $csmNode
     * @param DOMNode $pxeParentNode
     * @return none
     * @throws Exception
     */
    protected function handleTag(DOMNode $csmNode, DOMNode $pxeParentNode =  null)
    {
        $pxeParentNode = (self::hasPxeParents($csmNode) || $pxeParentNode == null)
            ?  $this->handleParents($csmNode)
            : $pxeParentNode;

        $tags = self::getAttributeValues($csmNode, 'data-pxe-tag');
        $hash = self::getAttributeValues($csmNode, 'data-pxe-hash');

        if (count($tags))
        {
            if (!count($hash)) {
                throw new Exception('Element without hash.', E_USER_ERROR);
            }

            $classes = self::getAttributeValues($csmNode, 'data-pxe-class');
            $def = (count($classes))
                ? $tags[0] . '.' . $classes[0]
                : $tags[0];
            if ($pxeNode = $this->elementMap->getNode($hash[0])) {
                $this->handleExistingTag($tags[0], $pxeNode, $csmNode);
            } else {
                $pxeNode = $this->applyTranslator($csmNode, $pxeParentNode, $def);

                $this->elementMap->setNode($hash[0], $pxeNode);
            }
        }
        else
        {
            $pxeNode = $this->passthroughNode($csmNode, $pxeParentNode);
        }
        if (!$csmNode->hasChildNodes()) return;
        foreach ($csmNode->childNodes as $child)
        {
            $this->handleTag($child, $pxeNode);
        }
    }

    protected function handleExistingTag($tagName, DOMNode $pxeNode, DOMNode $csmNode) {
        $addAttributes = $this->createAttributeMerge($csmNode);
        $toAttributes = $this->createAttributeMerge($pxeNode);
        foreach ($toAttributes as $k => $v) {
            if (isset($addAttributes[$k])) {
                $toAttributes[$k] = array_merge($v, $addAttributes[$k]);
                unset($addAttributes[$k]);
            }
        }
        foreach ($addAttributes as $k => $v) {
            $toAttributes[$k] = $v;
        }

        foreach ($toAttributes as $k => $v) {
            $pxeNode->setAttribute($k, implode(' ', $v));
        }
    }

    protected function createAttributeMerge(DOMNode $node) {
        $addAttributes = array();
        foreach ($node->attributes as $attribute) {
            if (strstr($attribute->name, 'pxe')) {
                switch ($attribute->name) {
                    case 'data-pxe-class':
                        $addAttributes['class'] = (isset($addAttributes['class']))
                            ? array_merge($addAttributes['class'], self::getAttributeValues($node, 'data-pxe-class'))
                            : self::getAttributes($node);
                            break;
                        case 'data-pxe-attributes':
                            $pxeAttrs = json_decode($attribute->value);

                            foreach ($pxeAttrs as $key => $value) {
                                $forMerge = preg_split('/\s+/', $value);
                                if (isset($addAttributes[$key])) {
                                    $addAttributes[$key] = array_merge($addAttributes[$key], $forMerge);
                                } else {
                                    $addAttributes[$key] = $forMerge;
                                }
                            }
                            break;
                        default:
                            continue;
                }
            } else {
                $addAttributes[$attribute->name] = (isset($addAttributes[$attribute->name]))
                    ? array_merge($addAttributes[$attribute->name], preg_split('/\s+/', $attribute->value))
                    : preg_split('/\s+/', $attribute->value);
            }
        }
        return $addAttributes;
    }

    /**
     * Passthru node.
     * @param DOMNode $csmNode
     * @param DOMNode $parentPxeNode
     *
     * @return none
     */
    protected function passthroughNode(DOMNode $csmNode, DOMNode $parentPxeNode)
    {
        $childPxeNode = $this->pxeDoc->importNode($csmNode, false);
        $parentPxeNode->appendChild($childPxeNode);
        return $childPxeNode;
    }

    protected static function hasPxeParents(DOMNode $csmNode) {
        $parents = self::getAttributeValues($csmNode, 'data-pxe-parents');
        return (count($parents) > 0);
    }

    /**
     * Apply translator.
     *
     * @param DOMNode $csmNode
     * @param DOMNode $pxeParentNode
     * @param type $pxeDef
     * @param boolean $keepAttributes
     * 
     * @return DOMElement
     */
    protected function  applyTranslator(
            DOMNode $csmNode,
            DOMNode $pxeParentNode,
            $pxeDef,
            $keepAttributes = true)
    {
        $def = IdmlPxeExportParentMap::parsePixieDef($pxeDef);

        $translator = $this->getTranslator($def['tagName']);

        $pxeNode = $translator
            ->keepAttributes($keepAttributes)
            ->setDoc($this->pxeDoc)
            ->setClass($def['class'])
            ->setParent($pxeParentNode)
            ->setAttributes($def['attributes'])
            ->setNode($csmNode)
            ->process();

        return $pxeNode;
    }

    /**
     * Get translator. Creates translator.
     *
     * @param type $pxeDef
     * 
     * @return AbstractTranslator
     * @throws Exception
     */
    protected function getTranslator($pxeDef)
    {
        foreach ($this->translatorMap as $translatorName => $translatorData)
        {

            if (!isset($translatorData['data-pxe-tag'])) throw new Exception('Invalid definition of ' . $translatorName . ' data-pxe-tag missing');
            if (in_array($pxeDef, $translatorData['data-pxe-tag']))
            {
                App::import('Import/Idml/Pxe/Translators',$translatorName);
                if (file_exists($path))
                {
                    require_once($path);
                    return new $translatorName;
                }

            }
        }
        $def = IdmlPxeExportParentMap::parsePixieDef($pxeDef);
        ///require_once(dirname(__FILE__) . self::TRANSLATOR_DIRECTORY . self::DEFAULT_TRANSLATOR . 'test.php');
        $translatorName = self::DEFAULT_TRANSLATOR;
        $translator = new $translatorName;
        $translator->setTagName($def['tagName']);
        $translator->setClass($def['class']);
        return $translator;
    }

    /**
     * Get attribute values.
     * @param DOMNode $node
     * @param string $attrName
     *
     * @return array
     */
    public static function getAttributeValues(DOMNode $node, $attrName)
    {
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attr)
            {
                if ($attr->name == $attrName)
                {
                    return preg_split('/\s+/', trim($attr->value));
                }
            }
        }
        return array();
    }

    /**
     * Populate map.
     *
     * @param string $mapFile Full path to the file.
     *
     * @return none
     * @throws Exception
     */
    protected function populateMap($mapFile = null)
    {
        if ($mapFile)
        {
            if (!file_exists($mapFile))
            {
                throw new Exception($mapFile . ' no present.', E_USER_ERROR);
            }
            $this->translatorMap = json_decode(file_get_contents($mapFile), true);
        }
        else
        {
            $this->translatorMap = array();
        }
    }

    /**
     * Return transformed content.
     * 
     * @return string
     */
    public function getContent()
    {
        if ($this->pxeDoc)
        {
            // Turning on output formatting is suitable for debugging purposes only
            // $this->pxeDoc->formatOutput = true;      
            // 
            // Production usage requires this to be off,
            // so that <span>abc</span><span>def</span> doesn't come out with a space like "abc def"
            $this->pxeDoc->formatOutput = false;
            
            if ($this->isDocument)
            {
                return $this->pxeDoc->saveXML();
            }
            else
            {
                return $this->pxeDoc->saveXML($this->currentElement);
            }
        }
        else
        {
            return '';
        }
    }

    /**
     * Convert pxe exporter to string.
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }
}




