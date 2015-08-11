<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeFactory.php
 *
 * @class   IdmlDecodeFactory
 *
 * @description Instantiate an IDMLDecoder class based on the IDML property name
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode',                       'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeFontFamily',             'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodePointSizeAndLeading',    'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeLeading',                'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTextDecoration',         'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodePosition',               'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCapitalization',         'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeLetterSpacing',          'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeKerning',                'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeWordSpacing',            'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeLigatures',              'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeSkew',                   'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeGlyphSpacing',           'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeHorizontalScale',        'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeVerticalScale',          'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTracking',               'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeVerticalAlign',          'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeFirstLineIndent',        'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeJustification',          'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeParagraphPadding',       'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeHyphenation',            'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeLists',                  'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTextWrap',               'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeInsetSpacing',           'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTextFrameJustification', 'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeFill',                   'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCorners',                'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeOpacity',                'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTextShadow',             'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeBoxShadow',              'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeStroke',                 'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTableTopStroke',         'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTableRightStroke',       'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTableBottomStroke',      'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeTableLeftStroke',        'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCellTopStroke',          'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCellRightStroke',        'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCellBottomStroke',       'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCellLeftStroke',         'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeCellPadding',            'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeClipContentToCell',      'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeVerticalJustification',  'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeUnderline',              'Import/Idml/Styles/Decoders');
App::uses('IdmlDecodeLayer',                  'Import/Idml/Styles/Decoders');


class IdmlDecodeFactory
{
    /*
     * This map contains several keys that point to identical values. This is necessary because most of these IDML keys
     * are optional, thus only one of the keys *may* be in the InDesign file. Each IdmlDecode conversion function
     * is written to handle any of the possible present/not present situations.
     */
    static public $map = array(

        // Character typography
        'Properties::AppliedFont'                                 => 'IdmlDecodeFontFamily',
        'FontStyle'                                               => 'IdmlDecodeFontFamily',
        'PointSize'                                               => 'IdmlDecodePointSizeAndLeading',
        'Properties::Leading'                                     => 'IdmlDecodePointSizeAndLeading',
        'Underline'                                               => 'IdmlDecodeTextDecoration',
        'StrikeThru'                                              => 'IdmlDecodeTextDecoration',
        'Position'                                                => 'IdmlDecodePosition',
        'Capitalization'                                          => 'IdmlDecodeCapitalization',
        'DesiredLetterSpacing'                                    => 'IdmlDecodeLetterSpacing',
        'DesiredWordSpacing'                                      => 'IdmlDecodeWordSpacing',
        'DesiredGlyphSpacing'                                     => 'IdmlDecodeGlyphSpacing',
        'HorizontalScale'                                         => 'IdmlDecodeHorizontalScale',
        'VerticalScale'                                           => 'IdmlDecodeVerticalScale',
        'Tracking'                                                => 'IdmlDecodeTracking',
        'BaselineShift'                                           => 'IdmlDecodeVerticalAlign',
        'KerningValue'                                            => 'IdmlDecodeKerning',
        'Ligatures'                                               => 'IdmlDecodeLigatures',
        'Skew'                                                    => 'IdmlDecodeSkew',

        // Paragraph typography
        'FirstLineIndent'                                         => 'IdmlDecodeFirstLineIndent',
        'Justification'                                           => 'IdmlDecodeJustification',
        'SpaceBefore'                                             => 'IdmlDecodeParagraphPadding',
        'SpaceAfter'                                              => 'IdmlDecodeParagraphPadding',
        'LeftIndent'                                              => 'IdmlDecodeParagraphPadding',
        'RightIndent'                                             => 'IdmlDecodeParagraphPadding',
        'Hyphenation'                                             => 'IdmlDecodeHyphenation',
        'BulletsAndNumberingListType'                             => 'IdmlDecodeLists',
        'Properties::BulletChar->BulletCharacterValue'            => 'IdmlDecodeLists',
        'Properties::NumberingFormat'                             => 'IdmlDecodeLists',

        // TextFrames
        'TextWrapPreference->TextWrapMode'                        => 'IdmlDecodeTextWrap',
        'TextFramePreference::Properties::InsetSpacing::ListItem' => 'IdmlDecodeInsetSpacing',
        'TextFramePreference->VerticalJustification'              => 'IdmlDecodeTextFrameJustification',

        // Decorations
        'EnableFill'                                              => 'IdmlDecodeFill',
        'FillColor'                                               => 'IdmlDecodeFill',
        'FillTint'                                                => 'IdmlDecodeFill',
        'EnableStroke'                                            => 'IdmlDecodeStroke',
        'StrokeColor'                                             => 'IdmlDecodeStroke',
        'StrokeTint'                                              => 'IdmlDecodeStroke',
        'StrokeWeight'                                            => 'IdmlDecodeStroke',
        'StrokeType'                                              => 'IdmlDecodeStroke',
        'Properties::UnderlineColor'                              => 'IdmlDecodeUnderline',
        'EnableStrokeAndCornerOptions'                            => 'IdmlDecodeCorners',
        'CornerRadius'                                            => 'IdmlDecodeCorners',
        'TopLeftCornerRadius'                                     => 'IdmlDecodeCorners',
        'TopRightCornerRadius'                                    => 'IdmlDecodeCorners',
        'BottomLeftCornerRadius'                                  => 'IdmlDecodeCorners',
        'BottomRightCornerRadius'                                 => 'IdmlDecodeCorners',
        'TransparencySetting::BlendingSetting->Opacity'           => 'IdmlDecodeOpacity',
        'TransparencySetting::DropShadowSetting'                  => 'IdmlDecodeBoxShadow',
        'ContentTransparencySetting::DropShadowSetting->Mode'     => 'IdmlDecodeTextShadow',

        // Tables
        'TopBorderStrokeWeight'                                   => 'IdmlDecodeTableTopStroke',
        'TopBorderStrokeType'                                     => 'IdmlDecodeTableTopStroke',
        'TopBorderStrokeColor'                                    => 'IdmlDecodeTableTopStroke',
        'TopBorderStrokeTint'                                     => 'IdmlDecodeTableTopStroke',
        'RightBorderStrokeWeight'                                 => 'IdmlDecodeTableRightStroke',
        'RightBorderStrokeType'                                   => 'IdmlDecodeTableRightStroke',
        'RightBorderStrokeColor'                                  => 'IdmlDecodeTableRightStroke',
        'RightBorderStrokeTint'                                   => 'IdmlDecodeTableRightStroke',
        'BottomBorderStrokeWeight'                                => 'IdmlDecodeTableBottomStroke',
        'BottomBorderStrokeType'                                  => 'IdmlDecodeTableBottomStroke',
        'BottomBorderStrokeColor'                                 => 'IdmlDecodeTableBottomStroke',
        'BottomBorderStrokeTint'                                  => 'IdmlDecodeTableBottomStroke',
        'LeftBorderStrokeWeight'                                  => 'IdmlDecodeTableLeftStroke',
        'LeftBorderStrokeType'                                    => 'IdmlDecodeTableLeftStroke',
        'LeftBorderStrokeColor'                                   => 'IdmlDecodeTableLeftStroke',
        'LeftBorderStrokeTint'                                    => 'IdmlDecodeTableLeftStroke',

        // Cells
        'TopEdgeStrokeWeight'                                     => 'IdmlDecodeCellTopStroke',
        'TopEdgeStrokeType'                                       => 'IdmlDecodeCellTopStroke',
        'TopEdgeStrokeColor'                                      => 'IdmlDecodeCellTopStroke',
        'TopEdgeStrokeTint'                                       => 'IdmlDecodeCellTopStroke',
        'RightEdgeStrokeWeight'                                   => 'IdmlDecodeCellRightStroke',
        'RightEdgeStrokeType'                                     => 'IdmlDecodeCellRightStroke',
        'RightEdgeStrokeColor'                                    => 'IdmlDecodeCellRightStroke',
        'RightEdgeStrokeTint'                                     => 'IdmlDecodeCellRightStroke',
        'BottomEdgeStrokeWeight'                                  => 'IdmlDecodeCellBottomStroke',
        'BottomEdgeStrokeType'                                    => 'IdmlDecodeCellBottomStroke',
        'BottomEdgeStrokeColor'                                   => 'IdmlDecodeCellBottomStroke',
        'BottomEdgeStrokeTint'                                    => 'IdmlDecodeCellBottomStroke',
        'LeftEdgeStrokeWeight'                                    => 'IdmlDecodeCellLeftStroke',
        'LeftEdgeStrokeType'                                      => 'IdmlDecodeCellLeftStroke',
        'LeftEdgeStrokeColor'                                     => 'IdmlDecodeCellLeftStroke',
        'LeftEdgeStrokeTint'                                      => 'IdmlDecodeCellLeftStroke',
        'TopInset'                                                => 'IdmlDecodeCellPadding',
        'RightInset'                                              => 'IdmlDecodeCellPadding',
        'BottomInset'                                             => 'IdmlDecodeCellPadding',
        'LeftInset'                                               => 'IdmlDecodeCellPadding',
        'VerticalJustification'                                   => 'IdmlDecodeVerticalJustification',
        'ClipContentToCell'                                       => 'IdmlDecodeClipContentToCell',

        // Miscellaneous
        'ItemLayer'                                               => 'IdmlDecodeLayer',
);

    /*
     * This factory method may be called by IdmlDeclaredStyle::convert() for generating CSS class declarations or
     * by IdmlProduceHtml for an individual HTML element's 'styles' attribute.
     *
     * @param Idml[XXX]Style $contextualStyle is the style whose properties are to be converted.
     * This may either be a defined style or a contextual style.
     *
     * @param string $idmlPropertyName is an IDML property name taken from an InDesign style declaration in /Resources/Styles.xml or
     * from an inline set of properties applied to a ParagraphRange, CharacterRange, etc.
     *
     * @param string $idmlPropertyValue is the corresponding value.
     *
     */
    static public function instantiate($contextualStyle, $idmlPropertyName, $idmlPropertyValue)
    {
        if (array_key_exists($idmlPropertyName, IdmlDecodeFactory::$map))
        {
            $decoderClassName = IdmlDecodeFactory::$map[$idmlPropertyName];
            return new $decoderClassName($contextualStyle, $idmlPropertyName, $idmlPropertyValue);
        }

        else
        {
            return new IdmlDecode($contextualStyle, $idmlPropertyName, $idmlPropertyValue);
        }
    }
}
?>
