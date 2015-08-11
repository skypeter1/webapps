<?php
/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlDeclarationParser.php
 *
 * @class   IdmlDeclarationParser
 *
 * @description Read the /Resources/Styles.xml file to get all InDesign-defined style declarations.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */
 
App::uses('IdmlDeclarationManager',     'Import/Idml/Styles/Declarations');
App::uses('IdmlCharacterStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlParagraphStyleGroup',    'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlObjectStyleGroup',       'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlTableStyleGroup',        'Import/Idml/Styles/Declarations/DeclaredStyleGroup');
App::uses('IdmlCellStyleGroup',         'Import/Idml/Styles/Declarations/DeclaredStyleGroup');


class IdmlDeclarationParser
{
    public function __construct()
    {
    }

    /**
     *  The parse function is the starting point for parsing the Styles.xml file.
     */
    public function load($filename)
    {
        $manager = IdmlDeclarationManager::getInstance();

        // Read the /Resources/Styles.xml file into a DOMDocument
        if(!file_exists($filename))
        {
            CakeLog::debug("[IdmlDeclarationParser::load] Filename not found $filename");
            return false;
        }
        $doc = new DOMDocument();
        if ($doc->load($filename) === false)
        {
            CakeLog::debug("[IdmlDeclarationParser::load] Unable to read $filename");
            return false;
        }
        $xpath = new DOMXPath($doc);

        // Recursively read each of the style groups and their hierarchy of styles
        $nodes = $xpath->query('//idPkg:Styles/RootCharacterStyleGroup');
        if ($nodes->length > 0)
        {
            $obj = new IdmlCharacterStyleGroup();
            $name = $obj->parse($nodes->item(0));
            $manager->addDeclaredStyleGroup($name, $obj);
        }
        $nodes = $xpath->query('//idPkg:Styles/RootParagraphStyleGroup');
        if ($nodes->length > 0)
        {
            $obj = new IdmlParagraphStyleGroup();
            $name = $obj->parse($nodes->item(0));
            $manager->addDeclaredStyleGroup($name, $obj);
        }
        $nodes = $xpath->query('//idPkg:Styles/RootObjectStyleGroup');
        if ($nodes->length > 0)
        {
            $obj = new IdmlObjectStyleGroup();
            $name = $obj->parse($nodes->item(0));
            $manager->addDeclaredStyleGroup($name, $obj);
        }
        $nodes = $xpath->query('//idPkg:Styles/RootTableStyleGroup');
        if ($nodes->length > 0)
        {
            $obj = new IdmlTableStyleGroup();
            $name = $obj->parse($nodes->item(0));
            $manager->addDeclaredStyleGroup($name, $obj);
        }
        $nodes = $xpath->query('//idPkg:Styles/RootCellStyleGroup');
        if ($nodes->length > 0)
        {
            $obj = new IdmlCellStyleGroup();
            $name = $obj->parse($nodes->item(0));
            $manager->addDeclaredStyleGroup($name, $obj);
        }

        // Using each style's "BasedOn" property, flatten the hierarchy so that each style has all of it's parent's properties.
        $manager->resolveAll();

        // Turn all properties which had duplicate names into an arrays
        foreach ($manager->declaredStyles as $styleName => $declaredStyle)
        {
            self::arrayifyDupes($styleName, $declaredStyle);
        }
    }

    /**
     * Some styles are indicated by series of identically named nodes and subnodes inside a parent node
     * At this time we've found tabs and nested styles to be described in this fashion.
     * During parsing, we add a digit to the end of the node name to make it unique.
     * arrayifyDupes takes all those nodes and puts them into arrays.
     * @param string $styleName
     * @param IdmlDeclaredStyle $declaredStyle
     */
    public static function arrayifyDupes($styleName, $declaredStyle)
    {
        // TabList properties are only found in ParagraphStyles
        if (substr($styleName, 0, 14) != 'ParagraphStyle')
        {
            return;
        }

        // Skip unless the style contains a tab list
        if (array_key_exists('Properties::TabList::ListItem->type', $declaredStyle->idmlKeyValues))
        {
            self::arrayifyListItems($declaredStyle->idmlKeyValues, 'TabList');
        }

        // Skip unless the style contains a tab list
        if (array_key_exists('Properties::AllNestedStyles', $declaredStyle->idmlKeyValues))
        {
            self::arrayifyListItems($declaredStyle->idmlKeyValues, 'AllNestedStyles');
        }
    }

    /**
     * Turns a series of related style properties into an array for easier processing.
     * These style properties are coded in IDML in ListItem nodes; some Properties child nodes
     *  (e.g. tabs or nested styles) contain multiple ListItem nodes.
     * The ListItem descendants' names are changed to make the idmlKeyValues keys unique during parsing.
     * Here, all those descendants are added to an array, which is then added to idmlKeyValues.
     * All the existing idmlKeyValues elements for the group are unset, since they are safely stored in the new array.
     * @param $idmlKeyValues
     * @param $groupName
     */
    public static function arrayifyListItems(&$idmlKeyValues, $groupName)
    {
        $groupListArray = array();
        $fullGroupName = 'Properties::' . $groupName;
        $nameLen = strlen($fullGroupName);

        foreach ($idmlKeyValues as $name => $value)
        {
            if (substr($name, 0, $nameLen) != $fullGroupName)
            {
                // This is a group header of sorts, e.g. 'Properties::AllNestedStyles'; relevant data is in subsequent idml key values
                continue;
            }

            if ($name == $fullGroupName || substr($name, -6) == '->type')
            {
                // These are group headers or content descriptors. We need only the relevant data is in subsequent idml key values
                unset($idmlKeyValues[$name]);
                continue;
            }

            $truncatedName = substr($name, $nameLen + 10);
            $firstColon = strpos($truncatedName, ':');

            if ($firstColon === false) {
                // This is a ListItem node; we want it's children only
                unset($idmlKeyValues[$name]);
                continue;
            }
            elseif ($firstColon == 0)
            {
                // This is the first item of relevant data for this property; initialize the index to zero to begin the array
                $listIndex = 0;
            }
            else
            {
                // This is a repeated item of relevant data; obtain the index from the appended number
                $listIndex = ((int) substr($truncatedName, 0, $firstColon)) - 1;
            }

            // Strip the value of tabs and line feeds, add it to the array, and remove the original from $idmlKeyValues
            $value = str_replace(array("\t","\n"), array('',''), $value);

            $groupListArray[$listIndex][substr($truncatedName, $firstColon + 2)] = $value;
            unset($idmlKeyValues[$name]);
        }

        // Add the completed array to the list of idml key values
        $idmlKeyValues[$fullGroupName] = $groupListArray;
    }
}
?>