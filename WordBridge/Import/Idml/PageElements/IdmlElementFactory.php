<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlElementFactory.php
 *
 * @class   IdmlElementFactory
 *
 * @description Centralized factory for creating IDMLElement-derived objects.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlParserHelper',           'Import/Idml');
App::uses('IdmlRectangle',              'Import/Idml/PageElements');
App::uses('IdmlTextFrame',              'Import/Idml/PageElements');
App::uses('IdmlElement',                'Import/Idml/PageElements');
App::uses('IdmlCharacterRange',         'Import/Idml/PageElements');
App::uses('IdmlParagraphRange',         'Import/Idml/PageElements');
App::uses('IdmlImage',                  'Import/Idml/PageElements');
App::uses('IdmlChange',                 'Import/Idml/PageElements');
App::uses('IdmlContent',                'Import/Idml/PageElements');
App::uses('IdmlGroup',                  'Import/Idml/PageElements');
App::uses('IdmlBrContent',              'Import/Idml/PageElements');
App::uses('IdmlXmlElement',             'Import/Idml/PageElements');
App::uses('IdmlTable',                  'Import/Idml/PageElements');
App::uses('IdmlTableRow',               'Import/Idml/PageElements');
App::uses('IdmlTableColumn',            'Import/Idml/PageElements');
App::uses('IdmlTableCell',              'Import/Idml/PageElements');
App::uses('IdmlHyperlink',              'Import/Idml/PageElements');
App::uses('IdmlHyperlinkDestination',   'Import/Idml/PageElements');
App::uses('IdmlSound',                  'Import/Idml/PageElements');
App::uses('IdmlMovie',                  'Import/Idml/PageElements');
App::uses('IdmlGraphicLine',            'Import/Idml/PageElements');
App::uses('IdmlPolygon',                'Import/Idml/PageElements');
App::uses('IdmlOval',                   'Import/Idml/PageElements');


/**
 * Idml Element factory.
 */
class IdmlElementFactory
{
     /**
      * @param DOMNode $node
      * @return IdmlElement $object
      */
    public static function createFromNode(DOMNode $node)
    {
        if(IdmlParserHelper::isParsableChildIdmlObjectNode($node))
        {
            $object = null;
            switch($node->nodeName)
            {
                case 'Rectangle':
                    // @TODO - determine whether spans should be used for rectangles parallel to the axes, and code accordingly
//                    $object = new IdmlRectangle();
                    $object = new IdmlPolygon();
                    break;
                case 'TextFrame':
                    $object = new IdmlTextFrame();
                    break;
                case 'Element':
                    $object = new IdmlElement();
                    break;
                case 'CharacterStyleRange':
                    $object = new IdmlCharacterRange();
                    break;
                case 'ParagraphStyleRange':
                    $object = new IdmlParagraphRange();
                    break;
                case 'Image':
                case 'PDF':                          /* Adobe Illustrator PDF image */
                case 'EPS':                          /* Encapsulated Postscript image */
                    $object = new IdmlImage();
                    break;
                case 'Group':
                    $object = new IdmlGroup();
                    break;
                case 'Story':
                    $object = new IdmlStory();
                    break;
                case 'Change':
                    $object = new IdmlChange();
                    break;
                case 'Content':
                    $object = new IdmlContent();
                    break;
                case 'Br':
                    $object = new IdmlBrContent();
                    break;
                case 'XMLElement':
                    $object = new IdmlXmlElement();
                    break;
                case 'Table':
                    $object = new IdmlTable();
                    break;                
                case 'Cell':
                    $object = new IdmlTableCell();
                    break;                
                case 'Column':
                    $object = new IdmlTableColumn();
                    break;                
                case 'Row':
                    $object = new IdmlTableRow();
                    break;
                case 'HyperlinkTextSource':
                    $object = new IdmlHyperlink();
                    break;
                case 'HyperlinkTextDestination':
                    $object = new IdmlHyperlinkDestination();
                    break;
                case 'Sound':
                    $object = new IdmlSound();
                    break;
                case 'Movie':
                    $object = new IdmlMovie();
                    break;
                case 'TextVariableInstance':
                    $object = new IdmlTextVariableInstance();
                    break;
                case 'GraphicLine':
                    $object = new IdmlGraphicLine();
                    break;
                case 'Polygon':
                    $object = new IdmlPolygon();
                    break;
                case 'Oval':
                    $object = new IdmlOval();
                    break;
                default:
                    CakeLog::debug("[IdmlElementFactory::createFromNode] '{$node->nodeName}' not implemented");
                    $object = null;
            }
            return $object;

        }
    }
}
