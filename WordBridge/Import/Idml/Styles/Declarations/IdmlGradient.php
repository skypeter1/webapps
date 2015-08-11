<?php

/**
 * @package /app/Import/Idml/Styles/Declarations/IdmlGradient.php
 *
 * @class   IdmlGradient
 *
 * @description  A gradient contains two or more 'stops' each with a 'location' from 0 to 100. All stops after the
 *               first one also have a 'midpoint' which is a percentage between that stop and the prior stop.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlGradient
{
    public $linearOrRadial;

    public $stops;

    public function __construct($linearOrRadial)
    {
        $this->linearOrRadial = $linearOrRadial;
        $this->stops = array();
    }

    public function addStop($rgb, $location)
    {
        $this->stops[] = array(
            'rgb' => $rgb,                      /* this is a string containing an rgb hex value, like #000000 or #ffffff */
            'location' => round($location,1)    /* this is a percentage, a number from 0 to 100 */
        );
    }

    /*
     * Returns the gradient as a CSS string suitable for use in a CSS 'background-image' property.
     * @param $angle is the IDML element's 'GradientFillAngle' which is a number from 0 to 360.
     * In InDesign, the parameter to this function is not part of the gradient itself, rather it is
     * part of the style or element, so it must be supplied in context.
     */
    public function gradientAsCSS($angle)
    {
        if ($this->linearOrRadial == 'Linear')
            return $this->linearGradientAsCSS($angle);
        else
            return $this->radialGradientAsCSS();
    }

    private function linearGradientAsCSS($idmlAngle)
    {
        // IDML specifies angles from 0 to 360 with West => 0, South => 90, East => 180, North => 270, West => 360
        // CSS  specifies angles from 0 to 360 with North => 360, West => 270, South => 180, East => 90, North => 0
        $cssAngle = (90 - $idmlAngle);
        if ($cssAngle >= 360)
            $cssAngle -= 360;
        if ($cssAngle <= -360)
            $cssAngle += 360;

        $css = "linear-gradient({$cssAngle}deg";

        foreach ($this->stops as $stop)
            $css .= sprintf( ', %s %s%%', $stop['rgb'], $stop['location']);

        $css .= ')';
        return $css;
    }

    private function radialGradientAsCSS()
    {
        $css = 'radial-gradient(circle closest-side';  // IDML does not have the equivalent of CSS 'ellipse'

        foreach ($this->stops as $stop)
            $css .= sprintf( ', %s %s%%', $stop['rgb'], $stop['location']);

        $css .= ')';
        return $css;
    }

    /*
     * Returns the first color as a CSS string suitable for use in a CSS 'color' property.
     * This is an emergency fallback for when the user has attempted to apply a gradient to text or borders,
     * both of which are allowed in InDesign, but not in CSS.
     */
    public function firstColorAsCSS()
    {
        return $this->stops[0]['rgb'];
    }

}
?>