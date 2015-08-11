<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlDeclarationManager.php
 *
 * @class   IdmlDeclarationManager
 *
 * @description The manager for style declarations
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclaredColors',     'Import/Idml/Styles/Declarations');
App::uses('IdmlDeclarationParser',  'Import/Idml/Styles/Declarations');


class IdmlDeclarationManager
{
//    const IMAGE_WIDTH_PERCENT = 85;             // images that are too wide for a typical eBook device, will be shown at this percent
//    const IMAGE_HEIGHT_PERCENT = 65;            // images that are too tall for a typical eBook device, will be shown at this percent
//    const IMAGE_WIDTH_HEIGHT_PERCENT = 50;      // images that are too wide and too tall for a typical eBook device, will be shown at this percent

    /**
     * @var IdmlDeclaredColors $declaredColorHandler contains IDML color object references defined in /Resources/Graphic.xml
     */
    public $declaredColorHandler;

    /**
     * Declared Style Groups are a hierarchical organization of semantically similar styles
     * They are created by the designer, within InDesign, to help manage what would otherwise become
     * an unwieldy collection of styles.
     *
     * @var array[IdmlxxxStyleGroup] where the key is the <xxxStyleGroup> "Self" attribute
     */
    public $declaredStyleGroups;

    /**
     * Declared Styles contain many, many properties, which are defined by Adobe in the IDML Specification.
     * Relatively few of these can be adequately mapped to CSS; the others are for advanced page layout
     * and page presentation that InDesign excels at, but that CSS is unable to accommodate.
     *
     * @var array[IdmlxxStyle] where the key is the <xxxStyle> "Self" attribute
     */
    public $declaredStyles;


    private static $instance = null;


    public static function getInstance()
    {
        if (IdmlDeclarationManager::$instance == null)
            IdmlDeclarationManager::$instance = new IdmlDeclarationManager();

        return IdmlDeclarationManager::$instance;
    }

    /*
     * resetInstance is needed for unit tests
     */
    public static function resetInstance()
    {
        IdmlDeclarationManager::$instance = null;
    }

    private function __construct()
    {
        $this->declaredStyleGroups = array();
        $this->declaredStyles = array();
        $this->declaredColorHandler = new IdmlDeclaredColors();
    }

    public function loadDeclaredColors($filename)
    {
        $this->declaredColorHandler->load($filename);
    }

    public function loadDeclaredStyles($filename)
    {
        $parser = new IdmlDeclarationParser();
        $parser->load($filename);
    }

    public function addDeclaredStyleGroup($name, $obj)
    {
        $this->declaredStyleGroups[$name] = $obj;
    }

    public function addDeclaredStyle($name, $obj)
    {
        $this->declaredStyles[$name] = $obj;
    }

    /*
     * resolveAll copies "BasedOn" properties from a master style to a subordinate style
     */
    public function resolveAll()
    {
        $this->resolveStyleHierarchy('CharacterStyle/$ID/[No character style]');
        $this->resolveStyleHierarchy('ParagraphStyle/$ID/[No paragraph style]');
        $this->resolveStyleHierarchy('ObjectStyle/$ID/[None]');
        $this->resolveStyleHierarchy('TableStyle/$ID/[No table style]');
        $this->resolveStyleHierarchy('CellStyle/$ID/[None]');
    }

    /*
     * resolveStyleHierarchy copies all properties from a master style to a subordinate style, walking down the hierarchy
     * This is a recursive function.
     * @param string $masterStyleName
     */
    public function resolveStyleHierarchy($masterStyleName)
    {
        $masterStyle = $this->declaredStyles[$masterStyleName];

        foreach ($this->declaredStyles as $styleName => $style)
        {
            $basedOn = $style->basedOn();
            if ($basedOn == $masterStyleName)
            {
                $style->idmlKeyValues = array_merge($masterStyle->idmlKeyValues, $style->idmlKeyValues);
                $style->idmlKeyValues['Properties::BasedOn'] = $basedOn;

                // walk down the hierarchy
                $this->resolveStyleHierarchy($styleName);
            }
        }
    }

    /*
     * @returns a string containing the CSS equivalents for every IDML declared style
     */
    public function convertIdmlToCSS()
    {
        $css = array();
        $css[] = $this->additionalPxeStyles();

        foreach ($this->declaredStyles as $key => $obj)
        {
            $css[] = $obj->convertIdmlToCss();
        }

        return implode("\n", $css);
    }

    /*
     * These additional Pxe styles are for PXE reflowable books
    */
    public function additionalPxeStyles()
    {
        $w  = 85; //IdmlDeclarationManager::IMAGE_WIDTH_PERCENT;
        $h  = 65; //IdmlDeclarationManager::IMAGE_HEIGHT_PERCENT;
        $wh = 50; //IdmlDeclarationManager::IMAGE_WIDTH_HEIGHT_PERCENT;

return <<<CSS3
.img-w-threshold {
	width                      : $w%;
}

.img-h-threshold {
	height                     : $h%;
}

.img-wh-threshold {
	width                      : $wh%;
}
CSS3;
    }


    /*
     * Diagnostic tool
     */
    public function dumpGroups()
    {
        $s = array();
        $s[] = "=== Declared Style Groups ===";
        foreach ($this->declaredStyleGroups as $key => $obj)
        {
            $s[] = "\nStyle Group: $key";
            foreach ($obj->children as $child)
                $s[] = "   $child";
        }
        return implode("\n", $s);
    }

    /*
     * Diagnostic tool
     */
    public function dumpStyles()
    {
        $s = array();
        $s[] = "=== Declared Styles ===";
        $i = 0;
        foreach ($this->declaredStyles as $key => $obj)
        {
            $i++;
            $s[] = sprintf("\n%3d Style: %s", $i, $key);
            foreach ($obj->idmlKeyValues as $key2 => $value2)
            {
                $k = sprintf("%-40.40s", '['.$key2.']');
                $s[] = sprintf("%3d %s => %s", $i, $k, $value2);
            }
        }
        return implode("\n", $s);
    }
}
?>