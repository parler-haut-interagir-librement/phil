<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use Phil\AccessibilityCheck\SuccessCriterion;

/**
 * The version of the Web Content Accessibility Guidelines a statement claims conformance to.
 */
enum Standard: string
{
    case Wcag20 = 'wcag-2.0';

    case Wcag21 = 'wcag-2.1';

    case Wcag22 = 'wcag-2.2';

    /**
     * The success criteria WCAG 2.1 added to WCAG 2.0.
     *
     * @var list<string>
     */
    private const array ADDED_IN_21 = [
        '1.3.4', '1.3.5', '1.3.6', '1.4.10', '1.4.11', '1.4.12', '1.4.13', '2.1.4', '2.2.6', '2.3.3',
        '2.5.1', '2.5.2', '2.5.3', '2.5.4', '2.5.5', '2.5.6', '4.1.3',
    ];

    /**
     * The success criteria WCAG 2.2 added to WCAG 2.1.
     *
     * @var list<string>
     */
    private const array ADDED_IN_22 = ['2.4.11', '2.4.12', '2.4.13', '2.5.7', '2.5.8', '3.2.6', '3.3.7', '3.3.8', '3.3.9'];

    /**
     * The name of the version, such as `WCAG 2.2`.
     */
    public function title(): string
    {
        return match ($this) {
            self::Wcag20 => 'WCAG 2.0',
            self::Wcag21 => 'WCAG 2.1',
            self::Wcag22 => 'WCAG 2.2',
        };
    }

    /**
     * The recommendation of the W3C.
     */
    public function url(): string
    {
        return match ($this) {
            self::Wcag20 => 'https://www.w3.org/TR/WCAG20/',
            self::Wcag21 => 'https://www.w3.org/TR/WCAG21/',
            self::Wcag22 => 'https://www.w3.org/TR/WCAG22/',
        };
    }

    /**
     * Whether the success criterion is part of this version; 4.1.1 Parsing, removed by WCAG 2.2, is not known.
     */
    public function includes(SuccessCriterion $criterion): bool
    {
        return match ($this) {
            self::Wcag22 => true,
            self::Wcag21 => !\in_array($criterion->value, self::ADDED_IN_22, true),
            self::Wcag20 => !\in_array($criterion->value, self::ADDED_IN_22, true) && !\in_array($criterion->value, self::ADDED_IN_21, true),
        };
    }
}
