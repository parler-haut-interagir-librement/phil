<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;

/**
 * Where a site writes the common settings the console pushes: a settings table, a file, a feature flag. Only the
 * keys the agent's configuration allows reach it, and each push reaches it once.
 */
interface ConfigurationApplier
{
    /**
     * Applies the settings, or throws: the push is then reported as failed and may be delivered again.
     */
    public function apply(Settings $settings): void;
}
