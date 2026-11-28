<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use Psr\Clock\ClockInterface;

/**
 * The sites the console operates, registered with the address of their agent and the public key it signs with.
 */
final readonly class SiteRegistry
{
    public function __construct(
        private SiteRepository $sites,
        private SnapshotRepository $snapshots,
        private Journal $journal,
        private ClockInterface $clock,
        private bool $allowInsecureUrls = false,
    ) {
    }

    /**
     * Registers a site, or changes the one with that identifier.
     *
     * @param list<string> $groups
     */
    public function register(string $id, string $name, string $agentUrl, string $publicKey, array $groups, ?string $actor): Site
    {
        $url = AgentUrl::fromString($agentUrl, $this->allowInsecureUrls);
        $key = VerifyingKey::fromBase64($publicKey);
        $site = $this->sites->get($id);

        if ($site instanceof Site) {
            $site->change($name, $url, $key, $groups);
        } else {
            $site = Site::register($id, $name, $url, $key, $groups, $this->clock->now());
        }

        $this->sites->save($site);
        $this->journal->record(JournalAction::SiteRegistered, $actor, $id, null, true, \sprintf('%s at %s', $site->name(), $url->value));

        return $site;
    }

    /**
     * Removes a site and its snapshots; its lines of the journal stay.
     */
    public function remove(string $id, ?string $actor): void
    {
        $site = $this->get($id);
        $this->sites->remove($site);
        $this->snapshots->removeSite($id);
        $this->journal->record(JournalAction::SiteRemoved, $actor, $id, null, true, $site->name());
    }

    /**
     * @throws NotFound
     */
    public function get(string $id): Site
    {
        return $this->sites->get($id) ?? throw NotFound::site($id);
    }

    /**
     * @return list<Site> sorted by identifier
     */
    public function all(): array
    {
        return $this->sites->all();
    }

    /**
     * The sites named, or those of a group, or all of them, in the order given or by identifier.
     *
     * @param list<string> $ids
     *
     * @return list<Site>
     *
     * @throws NotFound
     */
    public function select(array $ids = [], ?string $group = null): array
    {
        if ([] !== $ids) {
            return array_map($this->get(...), array_values(array_unique($ids)));
        }

        return null === $group ? $this->all() : array_values(array_filter($this->all(), static fn (Site $site): bool => $site->inGroup($group)));
    }
}
