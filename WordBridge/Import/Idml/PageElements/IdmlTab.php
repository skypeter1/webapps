<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlTab.php
 *
 * @class   IdmlTab
 *
 * @description Parser for InDesign Polygon.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDeclarationManager',         'Import/Idml/Styles/Declarations');
App::uses('IdmlElement',                    'Import/Idml/PageElements');
App::uses('IdmlContent',                    'Import/Idml/PageElements');
App::uses('IdmlParagraphRange',             'Import/Idml/PageElements');

class IdmlTab extends IdmlElement
{
    public $alignment;
    public $position;
    public $index;

    public $lastPosition;
    public $nextPosition;

    public $lastAlignment;
    public $nextAlignment;

    public $paragraph;

    public $usesSpans;

    /**
     * Constructor.
     */
    public function __construct(IdmlContent $contentNode, $tabIndex)
    {
        parent::__construct();

        $this->parentElement = $contentNode;
        $contentNode->childrenElements[] = $this;
        $this->paragraph = $this->getParagraph();
        $this->usesNestedClass = true;

        /* *** Hack alert!!! ***
         * In some cases, tabs need to be managed by a complex hierarchy of spans dictating position, alignment, and styling.
         * In other cases, that strategy generates HTML that doesn't position the content correctly.
         * The strategy implemented here is that if the containing paragraph has a line indent property,
         *   tabs are treated as simple content and are replaced with the html special character "&#09;".
         * If there is no line indent property, we use the complex span strategy.
         * This is based on an admittedly incomplete understanding of how InDesign works, and is subject to reevaluation.
         */
        $this->usesSpans = $this->paragraphUsesLeftIndent($this->paragraph);

        if (!$this->usesSpans)
        {
            return;
        }

        $this->paragraph->tabCount++;
        $tabData = $this->getTabLists();

        $this->tabData = $tabData[$tabIndex];

        $this->position = $tabData[$tabIndex]['Position'];
        $this->alignment = $tabData[$tabIndex]['Alignment'];
        $this->index = $tabIndex;

        // Save prior tab's position for determining the width of the current tab span
        if ($tabIndex > 0)
        {
            $this->lastPosition = $tabData[$tabIndex - 1]['Position'];
            $this->lastAlignment = $tabData[$tabIndex - 1]['Alignment'];
        }
        else
        {
            $this->lastPosition = 0;
            $this->lastAlignment = null;
            $this->paragraph->firstTabPosition = $tabData[$tabIndex]['Position'];
            $this->paragraph->firstTabAlignment = $tabData[$tabIndex]['Alignment'];
        }

        // Save next tab's position for determining width of the next tab span
        if (count($tabData) > $tabIndex + 1)
        {
            $this->nextPosition = $tabData[$tabIndex + 1]['Position'];
            $this->nextAlignment = $tabData[$tabIndex + 1]['Alignment'];
        }
        else
        {
            $this->nextPosition = null;
            $this->nextAlignment = null;
        }
    }

    /**
     * This function determines whether the tab's containing paragraph uses the LeftIndent property.
     * We need to parse tabs differently for such cases.
     * See also the *** Hack alert *** above for more details.
     * @param IdmlParagraphRange $paragraph
     * @return bool
     */
    protected function paragraphUsesLeftIndent($paragraph)
    {
        $propertyList = $paragraph->contextualStyle->idmlKeyValues;

        $leftIndent = isset($propertyList['LeftIndent']) ? $propertyList['LeftIndent'] : '0';

        if ($leftIndent == 0)
        {
            $declarationMgr = IdmlDeclarationManager::getInstance();

            $appliedStyle = $declarationMgr->declaredStyles[$paragraph->appliedStyleName];
            $propertyList = $appliedStyle->idmlKeyValues;

            $leftIndent = isset($propertyList['LeftIndent']) ? $propertyList['LeftIndent'] : '0';
        }

        return ($leftIndent == 0);
    }

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitTab($this, $depth);
    }

    /**
     * Get the TabList array property for the tab's containing paragraph.
     * If the tab list is not an array, or has no elements, it's not usable.
     * @return array|null
     */
    protected function getTabLists()
    {
        $tabList = null;

        $paragraphStyles = $this->paragraph->contextualStyle->idmlKeyValues;

        if (array_key_exists('Properties::TabList', $paragraphStyles) && is_array($paragraphStyles['Properties::TabList']) && count($paragraphStyles['Properties::TabList']) > 0)
        {
            $tabList = $paragraphStyles['Properties::TabList'];
        }
        else
        {
            $declarationMgr = IdmlDeclarationManager::getInstance();

            $paragraphStyles = $declarationMgr->declaredStyles[$this->paragraph->appliedStyleName];

            if (is_array($paragraphStyles->idmlKeyValues['Properties::TabList']))
            {
                $tabList = $paragraphStyles->idmlKeyValues['Properties::TabList'];
            }
        }

        return $tabList;
    }
}
