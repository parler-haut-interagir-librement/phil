<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\ConfigurationApplier;
use Phil\SitesConsoleBundle\Application\Contract\InstalledPackages;
use Phil\SitesConsoleBundle\Application\Contract\NonceStore;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Application\Contract\UpdateRunner;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use RuntimeException;

/**
 * The ports of an agent in memory: packages, health, nonces, an applier and a runner that record what they get.
 */
final class Memory implements InstalledPackages, SiteHealth, NonceStore, ConfigurationApplier, UpdateRunner
{
    /** @var array<string, int> */
    public array $nonces = [];

    /** @var list<Settings> */
    public array $applied = [];

    /** @var list<string> */
    public array $updates = [];

    public bool $fail = false;

    /**
     * @param array<string, string> $packages
     */
    public function __construct(
        public array $packages = [],
        public HealthSummary $health = new HealthSummary(HealthState::Up),
        public ?Outcome $outcome = null,
    ) {
    }

    #[Override]
    public function versions(): array
    {
        return $this->packages;
    }

    #[Override]
    public function summary(): HealthSummary
    {
        return $this->health;
    }

    #[Override]
    public function remember(string $nonce, int $seconds): bool
    {
        if (isset($this->nonces[$nonce])) {
            return false;
        }

        $this->nonces[$nonce] = $seconds;

        return true;
    }

    #[Override]
    public function apply(Settings $settings): void
    {
        if ($this->fail) {
            throw new RuntimeException('The settings could not be written.');
        }

        $this->applied[] = $settings;
    }

    #[Override]
    public function run(string $package, string $version): Outcome
    {
        if ($this->fail) {
            throw new RuntimeException('The pipeline is down.');
        }

        $this->updates[] = $package.' '.$version;

        return $this->outcome ?? Outcome::succeeded('Deployed '.$version);
    }
}
