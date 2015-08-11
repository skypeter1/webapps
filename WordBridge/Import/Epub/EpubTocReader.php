<?php

interface EpubTocReader
{
    public function setTocFile($file);
    public function parse();
    public function getNavData();
}
