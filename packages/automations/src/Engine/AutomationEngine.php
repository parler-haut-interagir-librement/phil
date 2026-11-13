<?php

declare(strict_types=1);

namespace Phil\Automations\Engine;

use DateTimeImmutable;
use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Contract\CompensableAction;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\BranchPath;
use Phil\Automations\Definition\Condition;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepKind;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Exception\UnknownComponent;
use Phil\Automations\Messenger\AdvanceRun;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Throwable;

/**
 * Starts runs on the site's events and takes them through their steps.
 *
 * `handle()` is called with each event a trigger reads: every enabled definition whose trigger matches gets one run,
 * created once per event key, and an `AdvanceRun` message. `advance()`, on a worker, claims the run for a lease — a
 * save over the revision it read, which only one worker wins —, then goes through its steps: an action runs and its
 * result is saved before the next step, a delay saves the run as waiting and sends a delayed `AdvanceRun`, a filter
 * ends the run unless its condition holds, a branch takes one of its paths.
 *
 * An action that fails in a way worth retrying waits and is tried again, as many times as its step allows, after 30
 * seconds, then 60, 120… up to an hour. Past that, or on a permanent failure, the action steps already done are
 * undone in reverse order by those that can be, and the run ends as failed.
 *
 * The engine keeps nothing between calls: one instance serves every message of a long-running worker.
 */
final readonly class AutomationEngine
{
    public const int DEFAULT_LEASE_SECONDS = 300;

    private const int FIRST_RETRY_SECONDS = 30;

    private const int LAST_RETRY_SECONDS = 3600;

    private LoggerInterface $logger;

    public function __construct(
        private Catalogue $catalogue,
        private Definitions $definitions,
        private RunStore $runs,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
        ?LoggerInterface $logger = null,
        private int $leaseSeconds = self::DEFAULT_LEASE_SECONDS,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Starts a run of every enabled definition whose trigger matches the event.
     *
     * @return list<RunId> the runs started; a run already started for this event is not started again
     */
    public function handle(object $event): array
    {
        $started = [];

        foreach ($this->catalogue->triggersFor($event) as $trigger) {
            foreach ($this->definitions->enabledFor($trigger->describe()->type) as $definition) {
                $match = $trigger->match($event, $definition->trigger->options);

                if (!$match instanceof TriggerMatch) {
                    continue;
                }

                $run = AutomationRun::start($definition, $match, $this->clock->now());

                if ($this->runs->create($run)) {
                    $this->bus->dispatch(new AdvanceRun($run->id->value));
                    $started[] = $run->id;
                }
            }
        }

        return $started;
    }

    /**
     * Takes a run through its steps until it waits or ends. A run that is not due, finished, or held by another
     * worker is left alone.
     */
    public function advance(RunId $id): void
    {
        $now = $this->clock->now();
        $run = $this->runs->get($id);

        if (!$run instanceof AutomationRun || !$run->isDue($now)) {
            return;
        }

        $definition = $this->definitions->get($run->automationId, $run->version);

        if (!$definition instanceof AutomationDefinition) {
            $this->persist($run->finished(RunState::Failed, \sprintf('Version %d of the automation is no longer kept.', $run->version), $now));

            return;
        }

        $run = $this->persist($run->claimed($now, $this->leaseEnd($now)));

        while ($run instanceof AutomationRun) {
            $run = $this->step($run, $definition);
        }
    }

    /**
     * Sends an `AdvanceRun` for each run whose time has come.
     *
     * @return int how many were sent
     */
    public function resumeDue(int $limit = 500): int
    {
        $due = $this->runs->due($this->clock->now(), max(1, $limit));

        foreach ($due as $id) {
            $this->bus->dispatch(new AdvanceRun($id->value));
        }

        return \count($due);
    }

    /**
     * Runs the step at the cursor; returns the run to go on with, or `null` when it waits, ends or was taken.
     */
    private function step(AutomationRun $run, AutomationDefinition $definition): ?AutomationRun
    {
        $now = $this->clock->now();
        $cursor = $run->cursor;

        if (!$cursor instanceof StepPath) {
            $this->persist($run->finished(RunState::Completed, null, $now));

            return null;
        }

        $step = $definition->stepAt($cursor);

        if (!$step instanceof Step) {
            $this->fail($run, $definition, \sprintf('The step path %s leads nowhere.', $cursor));

            return null;
        }

        try {
            return match ($step->kind) {
                StepKind::Action => $this->action($run, $definition, $cursor, $step),
                StepKind::Delay => $this->delay($run, $definition, $cursor, $step),
                StepKind::Filter => $this->holds($run, $step) ? $run->movedTo($definition->after($cursor), $now) : $this->end($run, RunState::Filtered),
                StepKind::Branch => $run->movedTo($definition->enter($cursor, $this->holds($run, $step) ? BranchPath::Then : BranchPath::Else), $now),
            };
        } catch (ActionFailed|UnknownComponent $failure) {
            $this->fail($run, $definition, \sprintf('Step "%s": %s', $step->id, $failure->getMessage()));

            return null;
        }
    }

    private function action(AutomationRun $run, AutomationDefinition $definition, StepPath $cursor, Step $step): ?AutomationRun
    {
        $type = (string) $step->action;
        $action = $this->catalogue->action($type);

        try {
            $result = $action->execute($this->context($run, $step, $run->attempt));
        } catch (Throwable $throwable) {
            $retryable = !$throwable instanceof ActionFailed || $throwable->retryable;

            if (!$retryable || $run->attempt >= $step->retries) {
                $this->fail($run, $definition, \sprintf('Step "%s": %s', $step->id, $throwable->getMessage()));

                return null;
            }

            $now = $this->clock->now();
            $wait = min(self::LAST_RETRY_SECONDS, self::FIRST_RETRY_SECONDS << min($run->attempt, 10));
            $this->wait($run->waitingUntil($now->modify(\sprintf('+%d seconds', $wait)), $cursor, $run->attempt + 1, $now), $wait);

            return null;
        }

        $now = $this->clock->now();

        return $this->persist($run->stepDone(new CompletedStep($step->id, $type, $result), $definition->after($cursor), $now)->claimed($now, $this->leaseEnd($now)));
    }

    private function delay(AutomationRun $run, AutomationDefinition $definition, StepPath $cursor, Step $step): null
    {
        $now = $this->clock->now();
        $next = $definition->after($cursor);

        if (!$next instanceof StepPath) {
            return $this->end($run, RunState::Completed);
        }

        $this->wait($run->waitingUntil($now->modify(\sprintf('+%d seconds', $step->seconds)), $next, 0, $now), $step->seconds);

        return null;
    }

    private function holds(AutomationRun $run, Step $step): bool
    {
        $condition = $step->condition;

        if (!$condition instanceof Condition) {
            throw ActionFailed::permanent('The step has no condition.');
        }

        return $this->catalogue->evaluator($condition->language)->evaluate($condition->source, [
            'payload' => $run->payload->values,
            'outputs' => array_map(static fn (Payload $output): array => $output->values, $run->outputs()),
            'subject' => $run->subject,
        ]);
    }

    private function end(AutomationRun $run, RunState $state): null
    {
        $this->persist($run->finished($state, null, $this->clock->now()));

        return null;
    }

    private function wait(AutomationRun $run, int $seconds): void
    {
        if ($this->persist($run) instanceof AutomationRun) {
            $this->bus->dispatch(new AdvanceRun($run->id->value), [new DelayStamp($seconds * 1000)]);
        }
    }

    /**
     * Undoes, in reverse order, the action steps done that can be undone, then ends the run as failed.
     */
    private function fail(AutomationRun $run, AutomationDefinition $definition, string $reason): void
    {
        $state = RunState::Failed;
        $steps = [];

        foreach ($definition->allSteps() as $step) {
            $steps[$step->id] = $step;
        }

        foreach (array_reverse($run->completed) as $done) {
            try {
                $action = $this->catalogue->action($done->action);

                if ($action instanceof CompensableAction && isset($steps[$done->stepId])) {
                    $action->compensate($this->context($run, $steps[$done->stepId], 0), $done->result);
                }
            } catch (Throwable $failure) {
                $state = RunState::CompensationFailed;
                $reason .= \sprintf(' Undoing step "%s" failed: %s', $done->stepId, $failure->getMessage());
            }
        }

        $this->logger->warning('The automation run {run} of {automation} failed.', ['run' => $run->id->value, 'automation' => $run->automationId->value, 'state' => $state->value]);
        $this->persist($run->finished($state, $reason, $this->clock->now()));
    }

    private function context(AutomationRun $run, Step $step, int $attempt): ActionContext
    {
        return new ActionContext(
            $run->id,
            $run->automationId,
            $step->id,
            $step->options,
            $run->payload,
            $run->subject,
            $run->outputs(),
            $run->id->value.'/'.$step->id,
            $attempt,
        );
    }

    private function persist(AutomationRun $run): ?AutomationRun
    {
        return $this->runs->save($run) ? $run->saved() : null;
    }

    private function leaseEnd(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(\sprintf('+%d seconds', max(1, $this->leaseSeconds)));
    }
}
