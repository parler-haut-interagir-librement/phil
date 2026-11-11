<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * How intense a theme is in one piece of content, from the mildest to the most severe: a passing mention, a scene
 * shown, a scene dwelt on. The cases are declared in that order, which `isAtLeast()` reads.
 */
enum Severity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';

    public function isAtLeast(self $other): bool
    {
        return array_search($this, self::cases(), true) >= array_search($other, self::cases(), true);
    }
}
