<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlOval.php
 *
 * @class   IdmlOval
 *
 * @description Parser for InDesign Oval.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlTransformation', 'Import/Idml');
App::uses('IdmlBoundary',       'Import/Idml');
App::uses('IdmlSvgShape',       'Import/Idml/PageElements');
App::uses('IdmlFrameFitting',   'Import/Idml');
App::uses('IdmlAttributes',     'Import/Idml');


class IdmlOval extends IdmlSvgShape
{
    /** Constructor, parser, and several utility methods are defined in the parent, IdmlSvgShape */

    /**
     * Visit this content.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitOval($this, $depth);

        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }
    }
}
