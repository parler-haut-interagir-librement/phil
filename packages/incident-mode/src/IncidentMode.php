<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use DateTimeImmutable;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\Exception\IncidentNotActive;
use Phil\IncidentMode\Exception\InvalidIncident;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The incident mode: starts an incident from a preset for a duration it requires, lifts it by hand or at its end, and
 * tells the application what is suspended now.
 *
 * It keeps nothing between calls: every answer is read from the store at the moment the clock gives, so that a
 * long-running worker sees an incident end at its end, lifted or not.
 */
final readonly class IncidentMode
{
    public const int DEFAULT_JOURNAL_PAGE = 50;

    public const int LIFT_BATCH = 100;

    public function __construct(
        private Presets $presets,
        private ActivationStore $store,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private LiftScheduler $scheduler = new NoLiftScheduler(),
    ) {
    }

    public function presets(): Presets
    {
        return $this->presets;
    }

    /**
     * Starts an incident from the preset, for the duration given, which the preset bounds.
     *
     * @throws Exception\UnknownPreset when the catalogue has no preset of that name
     * @throws InvalidIncident         when the duration is longer than the preset allows, or the reason too long
     */
    public function start(string $preset, Duration $duration, ?Actor $by, ?string $reason = null): Activation
    {
        $definition = $this->presets->get($preset);

        if ($duration->isLongerThan($definition->maximumDuration)) {
            throw InvalidIncident::because(\sprintf('The preset "%s" lasts at most %d seconds; %d were asked.', $preset, $definition->maximumDuration->seconds, $duration->seconds));
        }

        $activation = Activation::start($definition, $duration, $this->clock->now(), $by, $reason);
        $this->store->add($activation);
        $this->scheduler->schedule($activation);
        $this->events?->dispatch(new IncidentStarted($activation));

        return $activation;
    }

    /**
     * Gives an incident in force a later end; the incident as a whole stays within the longest duration its preset
     * allows, counted from its start. A preset removed from the catalogue since allows no extension.
     *
     * @throws IncidentNotActive when the incident is not in force
     * @throws InvalidIncident   when the new end goes past what the preset allows
     */
    public function extend(string $id, Duration $additional, ?Actor $by): Activation
    {
        $now = $this->clock->now();
        $activation = $this->inForce($id, $now);
        $longest = $this->presets->has($activation->preset) ? $this->presets->get($activation->preset)->maximumDuration : null;
        $endsAt = $additional->after($activation->endsAt);

        if (!$longest instanceof Duration || $endsAt > $longest->after($activation->startedAt)) {
            throw InvalidIncident::because(\sprintf('The incident "%s" cannot last longer than its preset allows.', $id));
        }

        if (!$this->store->extend($id, $endsAt)) {
            throw IncidentNotActive::withId($id);
        }

        $extended = $activation->endingAt($endsAt);
        $this->scheduler->schedule($extended);
        $this->events?->dispatch(new IncidentExtended($extended, $activation->endsAt, $by));

        return $extended;
    }

    /**
     * Ends an incident in force before its end.
     *
     * @throws IncidentNotActive when the incident is not in force
     */
    public function lift(string $id, ?Actor $by): Activation
    {
        $now = $this->clock->now();
        $activation = $this->inForce($id, $now);

        if (!$this->store->lift($id, $now, $by, LiftReason::Manual)) {
            throw IncidentNotActive::withId($id);
        }

        $lifted = $activation->liftedOn($now, $by, LiftReason::Manual);
        $this->events?->dispatch(new IncidentLifted($lifted));

        return $lifted;
    }

    /**
     * Records the end of the incident if it is past it: the delayed message of its lifting calls this. An incident
     * extended since, or already lifted, is left as it is.
     *
     * @return bool whether this call lifted it
     */
    public function liftIfDue(string $id): bool
    {
        $now = $this->clock->now();
        $activation = $this->store->find($id);

        if (!$activation instanceof Activation || !$activation->isDueAt($now) || !$this->store->lift($id, $activation->endsAt, null, LiftReason::Expired)) {
            return false;
        }

        $this->events?->dispatch(new IncidentLifted($activation->liftedOn($activation->endsAt, null, LiftReason::Expired)));

        return true;
    }

    /**
     * Records the end of every incident past it, a batch at a time: a scheduled task calls this.
     *
     * @param positive-int $batch
     *
     * @return int how many incidents this call lifted
     */
    public function liftExpired(int $batch = self::LIFT_BATCH): int
    {
        $lifted = 0;

        foreach ($this->store->dueIdentifiers($this->clock->now(), $batch) as $id) {
            if ($this->liftIfDue($id)) {
                ++$lifted;
            }
        }

        return $lifted;
    }

    /**
     * The incidents in force now, the earliest start first.
     *
     * @return list<Activation>
     */
    public function inForceNow(): array
    {
        $now = $this->clock->now();

        return array_values(array_filter($this->store->unliftedEndingAfter($now), static fn (Activation $activation): bool => $activation->isInForceAt($now)));
    }

    /**
     * Whether the restriction is suspended now: named by an incident in force, or covered by a read-only one.
     */
    public function isSuspended(Restriction|string $restriction): bool
    {
        $restriction = \is_string($restriction) ? Restriction::named($restriction) : $restriction;

        return array_any($this->inForceNow(), static fn (Activation $activation): bool => $activation->suspends($restriction));
    }

    /**
     * The names of the restrictions suspended now, each once, in the order the incidents started.
     *
     * @return list<string>
     */
    public function suspended(): array
    {
        $names = [];

        foreach ($this->inForceNow() as $activation) {
            foreach ($activation->restrictionNames() as $name) {
                $names[$name] = $name;
            }
        }

        return array_values($names);
    }

    public function find(string $id): ?Activation
    {
        return $this->store->find($id);
    }

    /**
     * The journal of the incidents, the latest first.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<Activation>
     */
    public function journal(int $limit = self::DEFAULT_JOURNAL_PAGE, int $offset = 0): array
    {
        return $this->store->history($limit, $offset);
    }

    private function inForce(string $id, DateTimeImmutable $now): Activation
    {
        $activation = $this->store->find($id);

        if (!$activation instanceof Activation || !$activation->isInForceAt($now)) {
            throw IncidentNotActive::withId($id);
        }

        return $activation;
    }
}
