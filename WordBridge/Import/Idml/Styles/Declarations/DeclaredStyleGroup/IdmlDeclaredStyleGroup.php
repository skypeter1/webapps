<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlDeclaredStyleGroup.php
 *
 * @class   IdmlDeclaredStyleGroup
 *
 * @description Declared Style Groups are a hierarchical organization of semantically similar styles
 * They are created by the designer, within InDesign, to help manage what would otherwise become
 * an unwieldy collection of styles.
 *
 * By default there is always one special group, called <RootCharacterStyleGroup>, <RootParagraphStyleGroup>,
 *  <RootObjectStyleGroup>, <RootTableStyleGroup> and <RootCellStyleGroup>
 *
 * User-defined groups are subordinate to each root group in elements called <CharacterStyleGroup>, <ParagraphStyleGroup>,
 *  <ObjectStyleGroup>, <TableStyleGroup> and <CellStyleGroup>
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
 
App::uses('IdmlCharacterStyle',         'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlParagraphStyle',         'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlObjectStyle',            'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlTableStyle',             'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlCellStyle',              'Import/Idml/Styles/Declarations/DeclaredStyle');
App::uses('IdmlCharacterStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlParagraphStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlObjectStyleGroup',       'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlTableStyleGroup',        'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlCellStyleGroup',         'Import/Idml/Styles/Declarations/DeclaredStyleGroup');


class IdmlDeclaredStyleGroup
{
    /*
     * @var string $styleTemplateName is either 'ParagraphStyle', 'CharacterStyle', 'ObjectStyle', 'TableStyle' or 'CellStyle'
     */
    public $styleTemplateName;

    /*
     * @var string $styleTemplateName is either 'ParagraphStyleGroup', 'CharacterStyleGroup', 'ObjectStyleGroup',
     *  'TableStyleGroup' or 'CellStyleGroup'
    */
    public $groupTemplateName;

    /*
     * @var array $children contains the names of the <xxxStyle> and <xxxStyleGroup> that are one level below this group
    */
    public $children;


    public function __construct($styleTemplateName)
    {
        $this->styleTemplateName = $styleTemplateName;
        $this->groupTemplateName = $styleTemplateName . 'Group';
        $this->children = array();
    }

    /*
     * Parse elements named <RootxxxStyleGroup> or <xxxStyleGroup>
     * This is recursive.
     * @param DOMElement $node
     * @returns the "Self" attribute of this group
     */
    public function parse(DOMElement $node)
    {
        $manager = IdmlDeclarationManager::getInstance();

        foreach($node->childNodes as $child)
        {
            if ($child->nodeType != XML_ELEMENT_NODE)
                continue;

            if ($child->nodeName == $this->groupTemplateName)
            {
                $classname = 'Idml' . $this->groupTemplateName;     // something like 'IdmlParagraphStyleGroup'
                $obj = new $classname();
                $name = $obj->parse($child);
                $manager->addDeclaredStyleGroup($name, $obj);
                $this->children[] = $name;
            }
            else if ($child->nodeName == $this->styleTemplateName)
            {
                $classname = 'Idml' . $this->styleTemplateName;     // something like 'IdmlParagraphStyle'
                $obj = new $classname();
                $obj->parse($child);
                $name = $obj->idmlKeyValues['Self'];
                $manager->addDeclaredStyle($name, $obj);
                $this->children[] = $name;
            }
            else
            {
                CakeLog::debug("[IdmlDeclaredStyleGroup::parse] Unhandled tag <$child->nodeName>");
            }
        }

        return ($node->hasAttribute('Self')) ? $node->getAttribute('Self') : '';
    }
}
?>