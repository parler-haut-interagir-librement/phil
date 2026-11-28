<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;

/**
 * How a site brings a package to a version: it starts the site's own pipeline — a deployment job, a script — and
 * says how it went. The bundle never runs Composer on a server.
 */
interface UpdateRunner
{
    public function run(string $package, string $version): Outcome;
}
