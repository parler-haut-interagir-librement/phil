<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Psr\Clock\ClockInterface;

/**
 * Common settings pushed to several sites: the push is kept and journaled before anything is sent, each site gets a
 * signed request carrying the push's identifier, and each delivery is journaled. A site that failed is tried again
 * by the next delivery; one that already applied the push answers that it did.
 */
final readonly class ConfigurationPushes
{
    public function __construct(
        private SiteRegistry $registry,
        private ConfigurationPushRepository $pushes,
        private AgentClient $client,
        private Journal $journal,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<string>         $siteIds
     *
     * @throws NotFound for a site the console does not know
     */
    public function create(array $settings, array $siteIds, ?string $actor): ConfigurationPush
    {
        $sites = array_map(static fn (Site $site): string => $site->id(), $this->registry->select($siteIds));
        $push = ConfigurationPush::create(Settings::of($settings), $sites, $actor, $this->clock->now());
        $this->pushes->save($push);
        $this->journal->record(JournalAction::PushCreated, $actor, null, $push->id(), true, \sprintf('%s to %s', implode(', ', $push->settings()->keys()), implode(', ', $sites)));

        return $push;
    }

    /**
     * Delivers the push to every site it has not reached yet, one after the other; a failure does not stop the others.
     *
     * @throws NotFound
     */
    public function deliver(string $pushId, ?string $actor): ConfigurationPush
    {
        $push = $this->get($pushId);
        $request = new ConfigurationRequest($push->id(), $push->settings());

        foreach ($push->undeliveredSites() as $siteId) {
            try {
                $outcome = $this->client->configure($this->registry->get($siteId), $request);
            } catch (AgentFailure|NotFound $failure) {
                $outcome = Outcome::failed($failure->getMessage());
            }

            $result = $push->record($siteId, $outcome, $this->clock->now());
            $this->pushes->save($push);
            $this->journal->record(JournalAction::PushDelivered, $actor, $siteId, $push->id(), $result->isDelivered(), '' === $outcome->message ? $result->value : $result->value.': '.$outcome->message);
        }

        return $push;
    }

    /**
     * @throws NotFound
     */
    public function get(string $pushId): ConfigurationPush
    {
        return $this->pushes->get($pushId) ?? throw NotFound::push($pushId);
    }

    /**
     * @return list<ConfigurationPush>
     */
    public function recent(int $limit): array
    {
        return $this->pushes->recent(max(1, $limit));
    }
}
