<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Event\SiteAlertRaised;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\ValueObject\Alert;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads a site through its agent and keeps the snapshot — or the error that kept it from being read —, then raises
 * the alerts the previous snapshot did not have.
 */
final readonly class Puller
{
    public function __construct(
        private SiteRegistry $registry,
        private AgentClient $client,
        private SnapshotRepository $snapshots,
        private AlertRules $rules,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $dispatcher = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function pull(string $siteId): Snapshot
    {
        $site = $this->registry->get($siteId);
        $previous = $this->snapshots->latest($siteId);

        try {
            $status = $this->client->status($site);

            if ($status->site !== $siteId) {
                throw AgentFailure::malformed(\sprintf('the status is that of the site "%s".', mb_substr($status->site, 0, 64)));
            }

            $snapshot = Snapshot::reached($siteId, $status, $this->clock->now());
        } catch (AgentFailure $agentFailure) {
            $this->logger?->warning('The site {site} could not be read: {reason}', ['site' => $siteId, 'reason' => $agentFailure->getMessage()]);
            $snapshot = Snapshot::unreachable($siteId, $agentFailure->getMessage(), $this->clock->now());
        }

        $this->snapshots->save($snapshot);
        $this->raise($siteId, $previous, $snapshot);

        return $snapshot;
    }

    private function raise(string $siteId, ?Snapshot $previous, Snapshot $snapshot): void
    {
        if (!$this->dispatcher instanceof EventDispatcherInterface) {
            return;
        }

        $latest = [];

        foreach ($this->registry->all() as $site) {
            $other = $site->id() === $siteId ? $snapshot : $this->snapshots->latest($site->id());

            if ($other instanceof Snapshot) {
                $latest[] = $other;
            }
        }

        $newest = $this->rules->newest($latest);
        $known = array_map(static fn (Alert $alert): string => $alert->key(), $this->rules->evaluate($siteId, $previous, $newest));

        foreach ($this->rules->evaluate($siteId, $snapshot, $newest) as $alert) {
            if (!\in_array($alert->key(), $known, true)) {
                $this->dispatcher->dispatch(new SiteAlertRaised($alert));
            }
        }
    }
}
