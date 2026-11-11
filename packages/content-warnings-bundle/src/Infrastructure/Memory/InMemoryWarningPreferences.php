<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Memory;

use Override;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Preferences kept in memory, for tests, commands and applications without sessions; reset to the defaults between
 * requests, so a worker never carries one member's preferences to the next.
 */
final class InMemoryWarningPreferences implements WarningPreferencesStore, ResetInterface
{
    private WarningPreferences $preferences;

    public function __construct(
        private readonly Display $default = Display::Fold,
    ) {
        $this->preferences = WarningPreferences::defaults($default);
    }

    #[Override]
    public function current(): WarningPreferences
    {
        return $this->preferences;
    }

    #[Override]
    public function save(WarningPreferences $preferences): void
    {
        $this->preferences = $preferences;
    }

    #[Override]
    public function reset(): void
    {
        $this->preferences = WarningPreferences::defaults($this->default);
    }
}
