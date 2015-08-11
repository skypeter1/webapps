<?php
/**
 * @package /app/Import/Idml/PageElements/IdmlMedia.php
 *
 * @class   IdmlMedia
 *
 * @description Parser for InDesign <Sound> and InDesign <Movie>.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlElement',            'Import/Idml/PageElements');
App::uses('IdmlFrameFitting',       'Import/Idml');
App::uses('IdmlTransformation',     'Import/Idml');
App::uses('IdmlBoundary',           'Import/Idml');
App::uses('IdmlAttributes',         'Import/Idml');


class IdmlMedia extends IdmlElement
{
    /**
     * @var string $tag - Name of the tag, 'audio' or 'video'
     */
    public $tag;

    /**
     * @var string $controls - attribute to indicate whether or not to display controls. Always true for sound files.
     */
    public $controls;

    /**
     * @var string $soundLoop - loop attribute to indicate whether or not to repeatedly play the media file
     */
    public $loop;

    /**
     * @var string $mediaFilename - file name of the media's source file
     */
    public $mediaFilename;

    /**
     * @var string $autoplay - autoplay attribute, set to 'autoplay ' if file plays on page turn
     */
    public $autoplay;

    /**
     * InDesign unique ID of the media element.
     * @var string
     */
    public $UID;

    /**
     * Page where is the media.
     * @var IdmlPage
     */
    public $page;

    /**
     * From what story this object is coming from.
     * @var IdmlStory
     */
    public $story = null;

    /** @var IdmlTransformation $transformation */
    public $transformation;

    /**
     * Boundary of the media player.
     * @var IdmlBoundary
     */
    public $boundary;
    
    /**
     * Is this object visible or has the InDesign user specifically hidden it
     * @var boolean
     */
    public $visible;


    /**
     * Constructor.
     * @param IdmlPage $page Could be null
     * @param IdmlStory $story Could be null if rectangle is not part of story.
     */
    public function __construct(IdmlPage $page = null, IdmlStory $story = null)
    {
        parent::__construct();

        $this->tag = '';
        $this->controls = true;
        $this->loop = false;
        $this->mediaFilename = '';
        $this->autoplay = false;
        $this->UID = '';
        $this->page = $page;
        $this->story = $story;
        $this->transformation = new IdmlTransformation();
        $this->boundary = IdmlBoundary::createDefault();
        $this->visible = true;
    }

    /**
     * Accept visitor. This element is not visited.
     * @param IdmlVisitor $visitor
     * @param int $depth
     */
    public function accept(IdmlVisitor $visitor, $depth = 0)
    {
        $visitor->visitMedia($this, $depth);

        // There are not likely to be any children; i've left this here to be safe -bt
        foreach($this->childrenElements as $child)
        {
            $child->accept($visitor, $depth+1);
        }
    }

    /**
     * Parse from DOM node.
      *
     * @param DOMElement $node
     */
    public function parse(DOMElement $node)
    {
        parent::parse($node);
        $this->setAttributes($node);
    }

    /**
     * @param DOMElement $node
     */
    protected function setAttributes(DOMElement $node)
    {
        $this->UID = $node->hasAttribute(IdmlAttributes::Self) ? $node->getAttribute(IdmlAttributes::Self) : null;
        $this->idAttribute = $this->UID;

        $this->setTransform($node);

        $visible = $node->hasAttribute(IdmlAttributes::Visible) ? $node->getAttribute(IdmlAttributes::Visible) : 'true';
        $this->visible = (strtolower($visible) == 'true') ? true : false;

        $this->mediaFilename = $this->setFilename($node);
    }

    /**
     * Obtains media file name for audio or video file from Link tag attributes
     * @param DOMElement $node
     * @return string
     */
    protected function setFilename(DOMElement $node)
    {
        // Load image Url.
        $linkNodes = $node->getElementsByTagName('Link');

        $mediaFilename = '';

        if ($linkNodes->length > 0)
        {
            $linkNode = $linkNodes->item(0);
            $linkURI = $linkNode->hasAttribute(IdmlAttributes::LinkResourceURI) ? $linkNode->getAttribute(IdmlAttributes::LinkResourceURI) : '';

            if (!empty($linkURI))
            {
                $mediaFilename = basename($linkURI);
                $mediaFilename = urldecode($mediaFilename);     // 'filename with spaces.jpg' <-- 'filename%20with%20spaces.jpg'
            }
        }
        return $mediaFilename;
    }
}
