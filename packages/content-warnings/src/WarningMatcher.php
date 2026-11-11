<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * Matches the warnings of a piece of content against a member's preferences: each warning gets the display its
 * theme, its category or the default gives, and the content takes the strictest of them. Linear in the number of
 * warnings, which `ContentWarnings` bounds.
 */
final readonly class WarningMatcher
{
    public function __construct(
        private ?Catalogue $catalogue = null,
    ) {
    }

    public function decide(ContentWarnings $warnings, WarningPreferences $preferences): Decision
    {
        $displays = [];

        foreach ($warnings as $warning) {
            $displays[] = [$warning, $preferences->displayFor($warning, $this->catalogue)];
        }

        $display = Display::strictest(...array_column($displays, 1));

        return new Decision(
            $display,
            array_column($displays, 0),
            array_column(array_filter($displays, static fn (array $pair): bool => $pair[1] === $display), 0),
        );
    }
}
