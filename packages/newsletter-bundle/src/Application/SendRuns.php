<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Event\SendRunCompleted;
use Phil\NewsletterBundle\Application\Event\SendRunPaused;
use Phil\NewsletterBundle\Application\Event\SendRunResumed;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\SendRunRepository;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Domain\ValueObject\BreakerPolicy;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The send runs of campaigns: one per attempt at sending, its counts as its messages go out, and its circuit breaker.
 *
 * Each count a worker adds is read back with the run: past a threshold of the breaker, the run is paused and
 * `SendRunPaused` dispatched; once every message it queued was sent or skipped, it is completed. A paused run sends
 * nothing until an operator resumes it, which queues again what it holds; there is no automatic resumption.
 */
final readonly class SendRuns
{
    /** The deliveries queued again at a time, when a run is resumed. */
    public const int PAGE = 500;

    /**
     * @param BreakerPolicy|null $breaker `null` for no circuit breaker
     */
    public function __construct(
        private SendRunRepository $runs,
        private DeliveryRepository $deliveries,
        private MessageBusInterface $bus,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private ?BreakerPolicy $breaker = new BreakerPolicy(),
    ) {
    }

    /**
     * @throws NotFound when there is no send run of that id
     */
    public function get(string $id): SendRun
    {
        return $this->runs->get($id) ?? throw NotFound::of('send run', $id);
    }

    public function find(string $id): ?SendRun
    {
        return $this->runs->get($id);
    }

    /**
     * @return list<SendRun> the send runs of a campaign, by number
     */
    public function ofCampaign(string $campaignId): array
    {
        return $this->runs->ofCampaign($campaignId);
    }

    /**
     * Starts the next send run of a campaign.
     */
    public function start(Campaign $campaign): SendRun
    {
        return $this->next($campaign->id, \count($this->runs->ofCampaign($campaign->id)) + 1);
    }

    /**
     * The send run that queues the messages of a campaign: its last one while it is still queuing them, else a new one.
     */
    public function current(Campaign $campaign): SendRun
    {
        $runs = $this->runs->ofCampaign($campaign->id);
        $last = array_last($runs);

        if ($last instanceof SendRun && !$last->queuedAt instanceof DateTimeImmutable && !$last->status->hasEnded()) {
            return $last;
        }

        return $this->next($campaign->id, \count($runs) + 1);
    }

    /**
     * Where the run of a delivery stands; `null` for a delivery of no run.
     */
    public function status(?string $runId): ?SendRunStatus
    {
        return null === $runId ? null : $this->runs->get($runId)?->status;
    }

    /**
     * Every message of the run is queued: keeps how many, then completes the run if each was already sent or skipped.
     */
    public function queuedAll(string $runId): void
    {
        $run = $this->get($runId);
        $run->queuedAll($this->deliveries->countOfRun($runId), $this->clock->now());

        $this->runs->queuedAll($run);
        $this->settle($run);
    }

    /**
     * Counts what became of a message of the run, then reads the run back: past a threshold of the breaker it is
     * paused, and once each of its messages was sent or skipped it is completed. A message of no run is not counted.
     */
    public function record(?string $runId, SendRunCounter $counter): void
    {
        if (null === $runId) {
            return;
        }

        $this->runs->add($runId, $counter);
        $run = $this->runs->get($runId);

        if ($run instanceof SendRun) {
            $this->settle($run);
        }
    }

    /**
     * An operator pauses a running send run.
     *
     * @throws NotFound               when there is no send run of that id
     * @throws InvalidNewsletterValue when the run is not running
     */
    public function pause(string $runId): void
    {
        $run = $this->get($runId);
        $this->pauseFor($run, PauseReason::Operator);
    }

    /**
     * An operator resumes a paused send run: what it holds is queued again, and the circuit breaker counts from here.
     *
     * @throws NotFound               when there is no send run of that id
     * @throws InvalidNewsletterValue when the run is not paused
     */
    public function resume(string $runId): void
    {
        $run = $this->get($runId);
        $run->resume();

        if (!$this->runs->transition($run, SendRunStatus::Paused)) {
            return;
        }

        $this->events->dispatch(new SendRunResumed($run->id, $run->campaignId, $this->clock->now()));
        $after = null;

        do {
            $ids = $this->deliveries->pendingOfRun($run->id, $after, self::PAGE);

            foreach ($ids as $id) {
                $this->bus->dispatch(new SendDelivery($id));
                $after = $id;
            }
        } while (self::PAGE === \count($ids));

        $this->settle($run);
    }

    /**
     * An operator cancels a running or paused send run: what it had not sent is skipped.
     *
     * @throws NotFound               when there is no send run of that id
     * @throws InvalidNewsletterValue when the run has ended
     */
    public function cancel(string $runId): void
    {
        $run = $this->get($runId);
        $from = $run->status;
        $run->cancel($this->clock->now());

        if ($this->runs->transition($run, $from)) {
            $this->runs->add($run->id, SendRunCounter::Skipped, $this->deliveries->skipPendingOfRun($run->id));
        }
    }

    private function next(string $campaignId, int $number): SendRun
    {
        $run = new SendRun(SendRun::idFor($campaignId, $number), $campaignId, $number, $this->clock->now());
        $this->runs->create($run);

        return $run;
    }

    private function settle(SendRun $run): void
    {
        if (SendRunStatus::Running !== $run->status) {
            return;
        }

        $reason = $this->breaker?->tripped($run);

        if ($reason instanceof PauseReason) {
            $this->pauseFor($run, $reason);

            return;
        }

        if ($run->isDone()) {
            $run->complete($this->clock->now());

            if ($this->runs->transition($run, SendRunStatus::Running)) {
                $this->events->dispatch(new SendRunCompleted($run->id, $run->campaignId, $this->clock->now()));
            }
        }
    }

    private function pauseFor(SendRun $run, PauseReason $reason): void
    {
        $run->pause($reason, $this->clock->now());

        if ($this->runs->transition($run, SendRunStatus::Running)) {
            $this->events->dispatch(new SendRunPaused($run->id, $run->campaignId, $reason, $this->clock->now()));
        }
    }
}
