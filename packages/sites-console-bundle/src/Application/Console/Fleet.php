<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\View\SiteOverview;
use Phil\SitesConsoleBundle\Application\View\VersionMatrix;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;

/**
 * What the console knows of all its sites at once, from their latest snapshots: the overview of each, its alerts, and
 * the versions of the watched packages side by side.
 */
final readonly class Fleet
{
    public function __construct(
        private SiteRegistry $registry,
        private SnapshotRepository $snapshots,
        private AlertRules $rules,
    ) {
    }

    /**
     * @return list<SiteOverview> sorted by site identifier
     */
    public function overview(): array
    {
        $latest = $this->latest();
        $newest = $this->rules->newest($latest);
        $overviews = [];

        foreach ($this->registry->all() as $site) {
            $snapshot = $latest[$site->id()] ?? null;
            $overviews[] = new SiteOverview($site, $snapshot, $this->rules->evaluate($site->id(), $snapshot, $newest));
        }

        return $overviews;
    }

    public function site(string $id): SiteOverview
    {
        $site = $this->registry->get($id);
        $snapshot = $this->snapshots->latest($id);

        return new SiteOverview($site, $snapshot, $this->rules->evaluate($id, $snapshot, $this->rules->newest($this->latest())));
    }

    public function matrix(): VersionMatrix
    {
        $latest = $this->latest();
        $newest = $this->rules->newest($latest);
        $versions = [];

        foreach ($latest as $siteId => $snapshot) {
            $versions[$siteId] = array_filter($snapshot->packages(), $this->rules->isWatched(...), \ARRAY_FILTER_USE_KEY);
        }

        return new VersionMatrix(array_keys($newest), array_keys($latest), $versions, $newest);
    }

    /**
     * @return array<string, Snapshot> the latest snapshot of each site that has one, by site identifier
     */
    private function latest(): array
    {
        $latest = [];

        foreach ($this->registry->all() as $site) {
            $snapshot = $this->snapshots->latest($site->id());

            if ($snapshot instanceof Snapshot) {
                $latest[$site->id()] = $snapshot;
            }
        }

        return $latest;
    }
}
