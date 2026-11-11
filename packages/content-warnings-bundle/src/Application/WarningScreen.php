<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Application;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Decision;
use Phil\ContentWarnings\WarningMatcher;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;

/**
 * Screens a piece of content for the current member: its warnings against the preferences the store gives, the
 * catalogue telling each theme's category.
 */
final readonly class WarningScreen
{
    private WarningMatcher $matcher;

    public function __construct(
        Catalogue $catalogue,
        private WarningPreferencesStore $preferences,
    ) {
        $this->matcher = new WarningMatcher($catalogue);
    }

    public function decide(?ContentWarnings $warnings): Decision
    {
        return $this->matcher->decide($warnings ?? ContentWarnings::none(), $this->preferences->current());
    }
}
