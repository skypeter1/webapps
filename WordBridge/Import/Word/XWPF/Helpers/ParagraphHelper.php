<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 3/8/16
 * Time: 7:14 PM
 */
class ParagraphHelper
{
    public static function getParagraphObjectStyle(){}
    /**
     * @param $styleName
     * @return HTMLElement
     */
    public static function selectHeadlineContainer($styleName)
    {

        switch ($styleName) {
            case 'heading 1':
                $headlineContainer = new HTMLElement(HTMLElement::H1);
                break;
            case 'heading 2':
                $headlineContainer = new HTMLElement(HTMLElement::H2);
                break;
            case 'heading 3':
                $headlineContainer = new HTMLElement(HTMLElement::H3);
                break;
            case 'heading 4':
                $headlineContainer = new HTMLElement(HTMLElement::H4);
                break;
            case 'heading 5':
                $headlineContainer = new HTMLElement(HTMLElement::H5);
                break;
            case 'heading 6':
                $headlineContainer = new HTMLElement(HTMLElement::H6);
                break;
            default:
                $headlineContainer = new HTMLElement(HTMLElement::H1);
                break;
        }

        $headerContainer = new HTMLElement(HTMLElement::HEADER);
        $headerContainer->addInnerElement($headlineContainer);

        return $headlineContainer;
    }
}