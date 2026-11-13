<?php

declare(strict_types=1);

namespace Phil\Epub\Accessibility;

/**
 * A human sense through which the content can be perceived, from the schema.org `accessMode` vocabulary.
 */
enum AccessMode: string
{
    case Auditory = 'auditory';
    case ChartOnVisual = 'chartOnVisual';
    case ChemOnVisual = 'chemOnVisual';
    case ColorDependent = 'colorDependent';
    case DiagramOnTactile = 'diagramOnTactile';
    case DiagramOnVisual = 'diagramOnVisual';
    case MathOnVisual = 'mathOnVisual';
    case MusicOnVisual = 'musicOnVisual';
    case Tactile = 'tactile';
    case TextOnVisual = 'textOnVisual';
    case Textual = 'textual';
    case Visual = 'visual';
}
