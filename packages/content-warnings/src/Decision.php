<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * What to do with one piece of content for one member: its display — the strictest display of its warnings, `Show`
 * without warnings —, its warnings, and those of its warnings that gave that display.
 */
final readonly class Decision
{
    /**
     * @param list<ContentWarning> $warnings
     * @param list<ContentWarning> $because
     */
    public function __construct(
        public Display $display,
        public array $warnings,
        public array $because,
    ) {
    }

    public function isShown(): bool
    {
        return Display::Show === $this->display;
    }

    public function isFolded(): bool
    {
        return Display::Fold === $this->display;
    }

    public function isHidden(): bool
    {
        return Display::Hide === $this->display;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}
