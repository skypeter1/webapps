<?php

App::uses('PdfProcessor', 'Lib/Import/Pdf');

class PdfProcessorRasterized extends PdfProcessor
{
    public $createTextOverlay = false;

    public function getPdfProcessorSwitches($width, $height)
    {
        $options = parent::getPdfProcessorSwitches($width, $height);
        if($this->createTextOverlay)
        {
            $options .= ' --fallback 1';
        }
        else
        {
            $options .= ' --rasterize-page 1';
        }
        return $options;
    }

    protected function getBookCss()
    {
        $bookCss = parent::getBookCSS();

        if($this->createTextOverlay)
        {
            $bookCss = str_replace("  color:transparent;\n", '', $bookCss);

            // add a single selector to allow book developers to toggle between red text and invisible text
            $invisible = ".textframe { /* make all text invisible: rgba(255,0,0,0) */\n/* make all text red:       rgb(255,0,0) */\n  color: rgba(255,0,0,0);\n}\n";

            $bookCss = $invisible . $bookCss;
        }
        return $bookCss;
    }

}