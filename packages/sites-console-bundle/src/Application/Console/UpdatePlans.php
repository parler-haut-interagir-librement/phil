<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Psr\Clock\ClockInterface;

/**
 * Updates grouped in batches: a plan names a package, a version and the sites in their order; running it asks each
 * site's agent in turn, keeps each outcome as it comes, and stops at the first failure, the sites not reached left
 * skipped. The plan is the report, one line per site.
 */
final readonly class UpdatePlans
{
    public function __construct(
        private SiteRegistry $registry,
        private UpdatePlanRepository $plans,
        private AgentClient $client,
        private Journal $journal,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $siteIds in the order they are updated
     *
     * @throws NotFound for a site the console does not know
     */
    public function plan(string $package, string $version, array $siteIds, int $batchSize, ?string $actor): UpdatePlan
    {
        $sites = array_map(static fn (Site $site): string => $site->id(), $this->registry->select($siteIds));
        $plan = UpdatePlan::plan($package, $version, $sites, $batchSize, $actor, $this->clock->now());
        $this->plans->save($plan);
        $this->journal->record(JournalAction::UpdatePlanned, $actor, null, $plan->id(), true, \sprintf('%s %s on %s, batches of %d', $package, $version, implode(', ', $sites), $batchSize));

        return $plan;
    }

    /**
     * Runs the plan from its next pending site: every batch left, or that many batches.
     *
     * @throws NotFound
     */
    public function run(string $planId, ?int $batches, ?string $actor): UpdatePlan
    {
        $plan = $this->get($planId);
        $position = $plan->next();
        $last = null === $position || null === $batches ? null : $plan->batchAt($position) + max(1, $batches) - 1;

        while (null !== $position && (null === $last || $plan->batchAt($position) <= $last)) {
            $siteId = $plan->siteAt($position);

            try {
                $outcome = $this->client->update($this->registry->get($siteId), new UpdateRequest($plan->stepKey($position), $plan->package(), $plan->version()));
            } catch (AgentFailure|NotFound $failure) {
                $outcome = Outcome::failed($failure->getMessage());
            }

            $result = $plan->record($position, $outcome, $this->clock->now());
            $this->plans->save($plan);
            $this->journal->record(JournalAction::UpdateStep, $actor, $siteId, $plan->id(), StepOutcome::Succeeded === $result, '' === $outcome->message ? $result->value : $result->value.': '.$outcome->message);
            $position = $plan->next();
        }

        return $plan;
    }

    /**
     * @throws NotFound
     */
    public function get(string $planId): UpdatePlan
    {
        return $this->plans->get($planId) ?? throw NotFound::plan($planId);
    }

    /**
     * @return list<UpdatePlan>
     */
    public function recent(int $limit): array
    {
        return $this->plans->recent(max(1, $limit));
    }
}
