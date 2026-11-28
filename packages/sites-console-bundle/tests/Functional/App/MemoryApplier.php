<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Functional\App;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\ConfigurationApplier;
use Phil\SitesConsoleBundle\Application\Contract\UpdateRunner;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;

/**
 * The site's applier and runner in the test application: the settings and the updates it was given.
 */
final class MemoryApplier implements ConfigurationApplier, UpdateRunner
{
    /** @var list<array<string, scalar|null>> */
    public array $applied = [];

    /** @var list<string> */
    public array $updates = [];

    #[Override]
    public function apply(Settings $settings): void
    {
        $this->applied[] = $settings->values;
    }

    #[Override]
    public function run(string $package, string $version): Outcome
    {
        $this->updates[] = $package.' '.$version;

        return '9.9.9' === $version ? Outcome::failed('The tests failed.') : Outcome::succeeded('Deployed.');
    }
}
