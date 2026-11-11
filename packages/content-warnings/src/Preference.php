<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * What a member wants for a theme or a category: a display, from a severity on when one is given. Below that severity
 * the preference does not apply; a warning of unknown severity is taken at its most severe, so the preference applies.
 */
final readonly class Preference
{
    public function __construct(
        public Display $display,
        public ?Severity $atLeast = null,
    ) {
    }

    public function appliesTo(ContentWarning $warning): bool
    {
        return !$this->atLeast instanceof Severity || !$warning->severity instanceof Severity || $warning->severity->isAtLeast($this->atLeast);
    }
}
