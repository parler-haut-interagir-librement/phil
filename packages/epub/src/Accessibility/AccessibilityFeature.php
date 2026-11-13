<?php

declare(strict_types=1);

namespace Phil\Epub\Accessibility;

/**
 * A feature that makes the content accessible, from the schema.org `accessibilityFeature` vocabulary.
 */
enum AccessibilityFeature: string
{
    case AlternativeText = 'alternativeText';
    case Annotations = 'annotations';
    case Aria = 'ARIA';
    case AudioDescription = 'audioDescription';
    case Braille = 'braille';
    case Captions = 'captions';
    case ChemMl = 'ChemML';
    case DescribedMath = 'describedMath';
    case DisplayTransformability = 'displayTransformability';
    case HighContrastAudio = 'highContrastAudio';
    case HighContrastDisplay = 'highContrastDisplay';
    case Index = 'index';
    case LargePrint = 'largePrint';
    case Latex = 'latex';
    case LongDescription = 'longDescription';
    case MathMl = 'MathML';
    case None = 'none';
    case PageBreakMarkers = 'pageBreakMarkers';
    case PageNavigation = 'pageNavigation';
    case ReadingOrder = 'readingOrder';
    case RubyAnnotations = 'rubyAnnotations';
    case SignLanguage = 'signLanguage';
    case StructuralNavigation = 'structuralNavigation';
    case SynchronizedAudioText = 'synchronizedAudioText';
    case TableOfContents = 'tableOfContents';
    case TactileGraphic = 'tactileGraphic';
    case TactileObject = 'tactileObject';
    case TimingControl = 'timingControl';
    case Transcript = 'transcript';
    case TtsMarkup = 'ttsMarkup';
    case Unlocked = 'unlocked';
}
