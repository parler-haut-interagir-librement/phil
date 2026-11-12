<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * The conformance level of a success criterion of WCAG: A the lowest, AAA the highest.
 */
enum ConformanceLevel: string
{
    case A = 'A';

    case AA = 'AA';

    case AAA = 'AAA';
}
