<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 3/8/16
 * Time: 12:15 PM
 */
class TableStyleHelper
{

    /**
     * @param $tcBorders
     * @return array|null
     */
    public static function getBorderProperties($tcBorders)
    {
        $borders = array();
        if (empty($tcBorders)) {
            $borders = null;
        } else {
            $borderKeys = array(
                "bottom" => "wbottom",
                "top" => "wtop",
                "right" => "wright",
                "left" => "wleft",
                "insideH" => "winsideH",
                "insideV" => "winsideV"
            );
            foreach ($borderKeys as $key => $borderKey) {
                if (array_key_exists($borderKey, $tcBorders)) {
                    $val = $tcBorders->xpath($borderKey)[0]["wval"];
                    if($val != "nil") {
                        $size = $tcBorders->xpath($borderKey)[0]["wsz"];
                        $space = $tcBorders->xpath($borderKey)[0]["wspace"];
                        $color = $tcBorders->xpath($borderKey)[0]["wcolor"];

                        $borders[$key]['val'] = (is_object($val)) ? (string)$val : "";
                        $borders[$key]['size'] = (is_object($size)) ? (string)round($size / 4) : "";
                        $borders[$key]['space'] = (is_object($space)) ? (string)$space : "";
                        $borders[$key]['color'] = (is_object($color)) ? (string)$color : "";
                        if ($borders[$key]['color'] == "auto") $borders[$key]['color'] = "000000";
                    }
                }
            }
        }

        return $borders;
    }

    /**
     * @param $borders
     * @param $tableStyleClass
     * @return mixed
     */
    public static function assignTableBorderStyles($borders,$tableStyleClass)
    {
        //Assign border styles
        if (!is_null($borders)) {
            $tableBorders = array('left', 'right', 'top', 'bottom');
            foreach ($borders as $key => $border) {
                if (in_array($key, $tableBorders)) {
                    $tableStyleClass->setAttribute("border-" . $key,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }
        return $tableStyleClass;
    }

    /**
     * @param $cellBorders
     * @param $tableCellStyleClass
     * @return mixed
     */
    public static function assignCellBorderStyles($cellBorders,$tableCellStyleClass ){
        if (!is_null($cellBorders)) {
            $tableBorders = array('right'=>'insideV', 'bottom'=>'insideH');
            foreach ($cellBorders as $key => $border) {
                if (in_array($key, $tableBorders)) {
                    $direction = ($key == "insideH") ? "bottom" : "right";
                    $tableCellStyleClass->setAttribute("border-" . $direction,
                        $border['size'] . "px " . HWPFWrapper::getBorder($border['val']) . " #" . $border['color']);
                }
            }
        }
        return $tableCellStyleClass;
    }

}