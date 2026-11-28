<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Alert;

use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Status\AlertKind;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\Alert;
use Phil\SitesConsoleBundle\Domain\Version\Packages;

/**
 * When a site needs attention: it could not be read, its health is degraded or down, or a package it has is older
 * than the version the configuration expects or than the newest version another site runs. Only the watched
 * packages and the expected ones are compared.
 */
final readonly class AlertRules
{
    /**
     * @param list<string>          $watched  the patterns of the packages compared across sites, such as `vendor/*`
     * @param array<string, string> $expected the lowest version each package should run
     */
    public function __construct(
        private array $watched = [],
        private array $expected = [],
    ) {
    }

    /**
     * @param ?Snapshot             $snapshot the latest snapshot of the site, null when it was never read
     * @param array<string, string> $newest   the newest version of each watched package across the sites
     *
     * @return list<Alert>
     */
    public function evaluate(string $siteId, ?Snapshot $snapshot, array $newest): array
    {
        if (!$snapshot instanceof Snapshot) {
            return [];
        }

        if (!$snapshot->isReachable()) {
            return [new Alert(AlertKind::Unreachable, $siteId, '', (string) $snapshot->error())];
        }

        $alerts = match ($snapshot->health()->state) {
            HealthState::Down => [new Alert(AlertKind::HealthDown, $siteId)],
            HealthState::Degraded => [new Alert(AlertKind::HealthDegraded, $siteId)],
            default => [],
        };

        foreach ($snapshot->packages() as $package => $installed) {
            $target = $this->target($package, $newest);

            if (null !== $target && (Packages::compare($installed, $target) ?? 0) < 0) {
                $alerts[] = new Alert(AlertKind::VersionBehind, $siteId, $package, $installed.' < '.$target);
            }
        }

        return $alerts;
    }

    /**
     * The newest version of each watched package among the snapshots that could be read.
     *
     * @param iterable<Snapshot> $snapshots
     *
     * @return array<string, string>
     */
    public function newest(iterable $snapshots): array
    {
        $newest = [];

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot->packages() as $package => $version) {
                if (!$this->isWatched($package)) {
                    continue;
                }

                if (!isset($newest[$package]) || $this->isNewer($version, $newest[$package])) {
                    $newest[$package] = $version;
                }
            }
        }

        ksort($newest, \SORT_STRING);

        return $newest;
    }

    /**
     * Whether a version is newer than the one kept: a release beats a branch, which has no order.
     */
    private function isNewer(string $version, string $kept): bool
    {
        return Packages::isOrdered($kept) ? (Packages::compare($version, $kept) ?? 0) > 0 : Packages::isOrdered($version);
    }

    public function isWatched(string $package): bool
    {
        return Packages::matches($package, $this->watched);
    }

    /**
     * @param array<string, string> $newest
     */
    private function target(string $package, array $newest): ?string
    {
        $expected = $this->expected[$package] ?? null;
        $fleet = $this->isWatched($package) ? ($newest[$package] ?? null) : null;

        if (null === $expected || null === $fleet) {
            return $expected ?? $fleet;
        }

        return (Packages::compare($fleet, $expected) ?? 0) > 0 ? $fleet : $expected;
    }
}
