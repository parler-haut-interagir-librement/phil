<?php

declare(strict_types=1);

namespace Phil\Epub\Accessibility;

/**
 * A characteristic of the content that may harm some readers, or its declared absence, from the schema.org
 * `accessibilityHazard` vocabulary.
 */
enum AccessibilityHazard: string
{
    case Flashing = 'flashing';
    case NoFlashingHazard = 'noFlashingHazard';
    case UnknownFlashingHazard = 'unknownFlashingHazard';
    case MotionSimulation = 'motionSimulation';
    case NoMotionSimulationHazard = 'noMotionSimulationHazard';
    case UnknownMotionSimulationHazard = 'unknownMotionSimulationHazard';
    case Sound = 'sound';
    case NoSoundHazard = 'noSoundHazard';
    case UnknownSoundHazard = 'unknownSoundHazard';
    case None = 'none';
    case Unknown = 'unknown';
}
