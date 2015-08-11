<?php
/**
 * @package /app/Lib/Import/Idml/PageElements/IdmlNestedStyleHelper.php
 *
 * @class   IdmlNestedStyleHelper
 *
 * @description Manages nested styles assigned to a ParagraphStyleRange. The IdmlNestedStyleHelper is linked to the IdmlParagraphRange.
 *              The object is used by descendants of the paragraph to determine when the nested style should be applied
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlNestedStyleHelper
{
    /**
     * @var IdmlParagraphRange $parentParagraph - paragraph on which the nested style is assigned
     */
    private $parentParagraph;

    /**
     * @var string $charStyle - the name of the character style to be assigned to a part of the parent paragraph
     */
    private $charStyle;

    /**
     * @var int $numTimes - the number of times the delimiter entity must be encountered to end the nested style
     * @var int $numFound - the number of times the delimiter entity has been encountered so far
     */
    private $numTimes;
    private $numFound;

    /**
     * @var string $delimiter - the name of the delimiter; this name will determine the content which the delimiter indicates
     */
    private $delimiter;

    /**
     * @var string $delimType - the type of delimiter to be searched for: either a regex, a processing instruction, or a tab node.
     */
    private $delimType;

    /**
     * @var string $delimRegex - the string delimiter; may include hex characters
     */
    private $delimRegex;

    /**
     * @var int $instrVal - the number indicating what type of processing instruction is used as the delimiter.
     */
    private $instrVal;

    /**
     * @var boolean $inclusive - indicates whether to style the final delimiter
     */
    private $inclusive;

    /**
     * @var int $patternOffset - the number of characters to offset the end of the string if the final delimiter is not included
     * Based on the length in bytes of the delimiter
     */
    private $patternOffset;

    /**
     * @var string $nodeName - the class name of the delimiter node
     */
    private $nodeName;

    /**
     * @var array $delimValues - This array contains the values to be used as delimiters for each of IDML's delimiter property values
     */
    private $delimValues = array
    (
        'Sentence' => array('type' => 'regex', 'value' => '[^\.]*\.', 'offset' => 1),
        'AnyCharacter' => array('type' => 'regex', 'value' => '.', 'offset' => 1),
        'Digits' => array('type' => 'regex', 'value' => '[^\d]*\d', 'offset' => 1),
        'Letters' => array('type' => 'regex', 'value' => '[^a-zA-Z]*[a-zA-Z]', 'offset' => 1),
        'AnyWord' => array('type' => 'regex', 'value' => '[^\s]+\s', 'offset' => 1),
        'EnSpace' => array('type' => 'regex', 'value' => '[^\xe2\x80\x82]*\xe2\x80\x82', 'offset' => 3),
        'EmSpace' => array('type' => 'regex', 'value' => '[^\xe2\x80\x83]*\xe2\x80\x83', 'offset' => 3),
        'ForcedLineBreak' => array('type' => 'regex', 'value' => '[^\xe2\x80\xa8]*\xe2\x80\xa8', 'offset' => 3),
        'NonbreakingSpace' => array('type' => 'regex', 'value' => '[^\xe2\x80\xaf|\xc2\xa0]*\xe2\x80\xaf|\xc2\xa0', 'offset' => 3),
        'EndNestedStyle' => array('type' => 'pi', 'value' => 3),
        'IndentHereTab' => array('type' => 'pi', 'value' => 7),
        'AutoPageNumber' => array('type' => 'pi', 'value' => 18),
        'SectionMarker' => array('type' => 'pi', 'value' => 19),
        'Tabs' => array('type' => 'node', 'value' => 'IdmlTab'),
    );

    public function __construct($idmlKeyValues, IdmlParagraphRange $paragraph)
    {
        $this->parentParagraph = $paragraph;

        $idmlCharStyle = $idmlKeyValues['AppliedCharacterStyle'];
        $this->charStyle = IdmlDeclarationManager::getInstance()->declaredStyles[$idmlCharStyle]->getClassName();

        $this->numTimes = (int)$idmlKeyValues['Repetition'];
        $this->delimiter = $idmlKeyValues['Delimiter'];
        $this->inclusive = ($idmlKeyValues['Inclusive'] == 'true');

        $this->assignDelimValues($this->delimiter);

        $this->numFound = 0;
    }

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @return int
     */
    public function getNumTimes()
    {
        return $this->numTimes;
    }

    /**
     * Reset the number of times found to zero. Used when a Br element is encountered, which resets all paragraph data
     */
    public function resetTimesFound()
    {
        $this->numFound = 0;
    }

    /**
     * Used to determine whether a nested style is still active, i.e. the delimiters found has not reached the limit.
     * @return boolean $active
     */
    public function isActive()
    {
        return ($this->numFound < $this->numTimes);
    }

    /**
     * @param mixed $content - can be content string or processing instruction value
     * @param IdmlElement $element
     * @param IdmlVisitor $producer
     * @param int $depth
     * @throws Exception
     */
    public function processContent($content, $element, $producer, $depth)
    {
        $nodeType = get_class($element);

        switch ($nodeType) {
            case 'IdmlText':
            case 'IdmlTextVariableInstance':
                $this->nestTextTypeNode($content, $element, $producer, $depth);
                break;
            case 'IdmlProcessingInstruction':
                $this->nestPINode($content, $element, $producer, $depth);
                break;
            case 'IdmlTab':
                $this->nestTabNode($content, $element, $producer, $depth);
                break;
            default:
                throw new Exception('Invalid node type, ' . $nodeType . ', in ' . __FILE__ . ', line ' . __LINE__);
        }
    }

    /**
     * @param string $content
     * @param IdmlElement $element
     * @param IdmlVisitor $producer
     * @param int $depth
     */
    private function nestTextTypeNode($content, $element, $producer, $depth = 0)
    {
        if ($this->numFound >= $this->numTimes) // CASE 1: we're already beyond the nested style
        {
            $styledContent = '<span>' . $content;
        }
        else
        {
            if ($this->delimType == 'regex')
            {
                $regExp = '/' . $this->delimRegex . '/';
                preg_match_all($regExp, $content, $matches, PREG_OFFSET_CAPTURE);
            }
            else
            {
                $matches = array(array());
            }

            if (count($matches[0]) + $this->numFound >= $this->numTimes) // CASE 2: nested style ends inside this segment
            {
                $lastMatch = $matches[0][($this->numTimes - $this->numFound) - 1];
                $endPos = $lastMatch[1] + strlen($lastMatch[0]);

                if (!$this->inclusive)
                {
                    $endPos -= $this->patternOffset;  // If the style is not 'inclusive', don't include the delimiter in the styling.
                }
                $styledContent = '<span class="' . $this->charStyle . '">' . substr($content, 0, $endPos);
                $this->addSpanTag($styledContent, $producer, $depth);

                $this->numFound = $this->numTimes;

                // Now recurse to process the remaining content
                $producer->addTextContent($element, substr($content, $endPos), $depth);

                // Since this case writes the content directly, return to avoid rewrite
                return;
            }
            else  // CASE 3: This entire segment is within the nested style.
            {
                $this->numFound += count($matches[0]);
                $styledContent = '<span class="' . $this->charStyle . '">' . $content;
            }
        }

        $this->addSpanTag($styledContent, $producer, $depth);
    }

    /**
     * Adds a span to current page. First replaces line feeds, which can't be replaced until now because they might be delimiters.
     * Then add the span opening and closing tags in separate steps: it's essential to keep the span tags in separate page elements,
     * since the tags are tallied later to find out what spans need to be closed at the end of a paragraph.
     * @param string $content
     * @param IdmlVisitor $producer
     * @param int $depth
     */
    private function addSpanTag($content, $producer, $depth)
    {
        $spanTag = str_replace("\xe2\x80\xa8", '<br />', $content);
        $producer->addPageElement($spanTag, $depth);
        $producer->addPageElement('</span>', $depth);
    }

    /**
     * @param string $content
     * @param IdmlElement $element
     * @param IdmlVisitor $producer
     * @param int $depth
     */
    private function nestPINode($content, $element, $producer, $depth=0)
    {
        // Increment numFound if delimiter matches the nested style
        if ($this->delimType == 'pi' && $content == $this->instrVal)
        {
            $this->numFound++;
        }

        if (($this->numFound < $this->numTimes) || ($this->numFound == $this->numTimes && $this->inclusive))
        {
            $producer->addPageElement('<span class="' . $this->charStyle . '">', $depth);
            $producer->visitProcessingInstructionCases($element, $content, $depth);
            $producer->addPageElement('</span>', $depth);
        }
        else
        {
            $producer->visitProcessingInstructionCases($element, $content, $depth);
        }
    }

    /**
     * @param string $content
     * @param IdmlElement $element
     * @param IdmlVisitor $producer
     * @param int $depth
     */
    private function nestTabNode($content, $element, $producer, $depth=0)
    {
        // Increment numFound if delimiter matches the nested style
        if ($this->delimType == 'node')
        {
            $this->numFound++;
        }

        if (($this->numFound < $this->numTimes) || ($this->numFound == $this->numTimes && $this->inclusive))
        {
            $producer->addPageElement('<span class="' . $this->charStyle . '">', $depth);
            $producer->processTab($element, $content, $depth);
            $producer->addPageElement('</span>', $depth);
        }
        else
        {
            $producer->processTab($element, $content, $depth);
        }
    }

    /**
     * Initialize the array of values for IDML delimiter property strings.
     * Each possible string indicates a delimiter which is either a regex, a processing instruction, or a node.
     * The interpretation of the value is dependent on the type of the delimiter.
     * @param string $delimiter - the IDML property indicating the delimiter
     * @throws Exception
     */
    private function assignDelimValues($delimiter)
    {
        if (array_key_exists($delimiter, $this->delimValues))
        {
            $delimData = $this->delimValues[$delimiter];
            $this->delimType = $delimData['type'];

            switch ($delimData['type'])
            {
                case 'regex':
                    $this->delimRegex = $delimData['value'];
                    $this->patternOffset = $delimData['offset'];
                    break;
                case 'pi':
                    $this->instrVal = $delimData['value'];
                    break;
                case 'node':
                    $this->nodeName = $delimData['value'];
                    break;
                default:
                    throw new Exception('Invalid type of nested style delimiter ' . $delimData['type'] . ' in ' . __FILE__ . ', line ' . __LINE__);
            }
        }
        else
        // The delimiter is not one of the enumerated values. Treat it as a string.
        // Convert the string to a regex. All non-alphanumeric characters get backslash quoted.
        {
            $this->delimType = 'regex';
            $escapedDelimiter = preg_replace('/([^a-zA-Z0-9])/', '\\\\${1}', $delimiter);
            $this->delimRegex = '[^' . $escapedDelimiter .']*[' . $escapedDelimiter . ']';
            $this->patternOffset = 1;
        }
    }

}