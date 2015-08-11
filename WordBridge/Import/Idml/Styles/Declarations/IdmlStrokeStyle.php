<?php

/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlStrokeStyle.php
 *
 * @class   IdmlStrokeStyle
 *
 * @description  InDesign supports custom strokes, but CSS doesn't so this class does it's best to provide a mapping
 *               from the robust InDesign possibilities to the simple CSS possibilities.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlStrokeStyle
{
    static public function strokeRefAsCSS($strokeRef)
    {
        list($customStrokeStyle, $customStrokeName) = explode('/', $strokeRef, 2);

        switch($customStrokeStyle)
        {
            case 'DashedStrokeStyle':
                return 'dashed';

            case 'DottedStrokeStyle':
                return 'dotted';

            case 'StripedStrokeStyle':
                return 'none';

            case 'StrokeStyle':
            {
                // These are entirely arbitrary mappings, at the whim of the author.
                switch($customStrokeName)
                {
                    case '$ID/Solid':
                        return 'solid';

                    case '$ID/Dashed':
                    case '$ID/Canned Dashed 3x2':
                    case '$ID/Canned Dashed 4x4':
                        return 'dashed';

                    case '$ID/Japanese Dots':
                    case '$ID/Canned Dotted':
                        return 'dotted';

                    case '$ID/ThickThick':
                    case '$ID/ThinThin':
                        return 'double';

                    case '$ID/ThickThinThick':
                        return 'groove';

                    case '$ID/ThinThickThin':
                        return 'ridge';

                    case '$ID/ThickThin':
                        return 'inset';

                    case '$ID/ThinThick':
                        return 'outset';

                    case '$ID/Triple_Stroke':
                    case '$ID/White Diamond':
                    case '$ID/Left Slant Hash':
                    case '$ID/Right Slant Hash':
                    case '$ID/Straight Hash':
                    case '$ID/Wavy':
                    default:
                        return 'solid';
                }
            }

            default:
                return 'none';
        }
    }
}
?>