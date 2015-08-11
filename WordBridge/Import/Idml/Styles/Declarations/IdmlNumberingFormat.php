<?php

/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlNumberingFormat.php
 *
 * @class   IdmlNumberingFormat
 *
 * @description  According to the document InDesign specifies NumberingFormat using either an enum or a string.
 *               This class contains a static function to convert NumberingFormat to CSS list-style-type
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlNumberingFormat
{
    static public function convertIdmlToCSS($numberingFormat)
    {
        switch($numberingFormat)
        {
            case 'UpperRoman':
            case 'I, II, II, IV...':
                return 'upper-roman';

            case 'LowerRoman':
            case 'i, ii, iii, iv...':
                return 'lower-roman';

            case 'UpperLetters':
            case 'A, B, C, D...':
                return 'upper-alpha';

            case 'LowerLetters':
            case 'a, b, c, d...':
                return 'lower-alpha';

            case 'Arabic':
                return 'arabic-indic';

            case 'KatakanaModern':
                return 'katakana';

            case 'KatakanaTraditional':
                return 'katakana-iroha';

            case 'KatakanaTraditional':
                return 'katakana-iroha';

            case '1, 2, 3, 4...':
                return 'decimal';

            case 'SingleLeadingZeros':
            case 'DoubleLeadingZeros':
            case 'TripleLeadingZeros':
                return 'decimal-leading-zero';

            case 'FormatNone':
            default:
                return 'none';
        }
    }
}
?>