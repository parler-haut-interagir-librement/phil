<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Application\Contract;

use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\Exception\PreferencesNotSaved;

/**
 * Where the current member's warning preferences are kept: the port an application implements over its own member
 * settings. The bundle ships `SessionWarningPreferences`, which keeps them in the HTTP session, and
 * `InMemoryWarningPreferences`, for tests and commands.
 *
 * An implementation keeps no member between requests: it reads the current member each time.
 */
interface WarningPreferencesStore
{
    /**
     * The current member's preferences, or the defaults when there are none.
     */
    public function current(): WarningPreferences;

    /**
     * @throws PreferencesNotSaved when there is no current member to save them for
     */
    public function save(WarningPreferences $preferences): void;
}
