<?php

App::uses('AbstractTranslator','Import/Idml/Pxe/Translators');

class DefaultTranslator extends AbstractTranslator
{
    public function process()
    {
        return $this->element;
    }
} 