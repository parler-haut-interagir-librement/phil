<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * The contrast a text must have with its background: AA, success criterion 1.4.3, or AAA, success criterion 1.4.6.
 * Large text — 24 CSS pixels, or 18.66 bold — needs less.
 */
enum ContrastLevel: string
{
    /** 4.5:1, and 3:1 for large text. */
    case AA = 'AA';

    /** 7:1, and 4.5:1 for large text. */
    case AAA = 'AAA';

    /**
     * The lowest ratio a text of this size may have.
     */
    public function minimumRatio(bool $largeText): float
    {
        return match ($this) {
            self::AA => $largeText ? 3.0 : 4.5,
            self::AAA => $largeText ? 4.5 : 7.0,
        };
    }

    public function criterion(): SuccessCriterion
    {
        return match ($this) {
            self::AA => SuccessCriterion::ContrastMinimum,
            self::AAA => SuccessCriterion::ContrastEnhanced,
        };
    }
}
