<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

/**
 * One request of the sequencer on a working copy of the state: the navigation, termination, sequencing and delivery
 * behaviours of SCORM 2004, deterministic for a given state.
 *
 * @internal built by `Sequencer` for each request
 */
final readonly class SequencingRun
{
    private SequencingData $data;

    private SequencingRules $rules;

    private SequencingFlow $flow;

    private SequencingRollup $rollup;

    private SequencingAttempts $attempts;

    public function __construct(
        private ActivityTree $tree,
        SequencingState $state,
    ) {
        $this->data = new SequencingData($tree, $state);
        $this->rules = new SequencingRules($tree, $this->data);
        $this->flow = new SequencingFlow($tree, $this->data, $this->rules);
        $this->rollup = new SequencingRollup($tree, $this->data, $this->rules, $this->flow);
        $this->attempts = new SequencingAttempts($tree, $this->data, $this->rollup);
    }

    public function state(): SequencingState
    {
        return $this->data->state();
    }

    public function lessonStatus(string $identifier): string
    {
        return $this->data->lessonStatus($identifier);
    }

    public function navigate(NavigationRequest $request, ?string $target): SequencingResult
    {
        return match ($request) {
            NavigationRequest::Start => $this->start(),
            NavigationRequest::ResumeAll => $this->resumeAll(),
            NavigationRequest::Continue, NavigationRequest::Previous => $this->flowRequest(NavigationRequest::Continue === $request),
            NavigationRequest::Choice => $this->choice($target),
            NavigationRequest::Exit => $this->exit(),
            NavigationRequest::ExitAll => $this->exitAllRequest(),
            NavigationRequest::SuspendAll => $this->suspendAll(),
        };
    }

    /**
     * @throws SequencingRefused when no activity is delivered, or the current one is not a leaf under way
     */
    public function report(AttemptResult $result): void
    {
        $identifier = $this->data->current;

        if (null === $identifier || !$this->tree->get($identifier)->isLeaf() || !$this->data->record($identifier)['active']) {
            throw SequencingRefused::because('Results are reported for the activity delivered, while its attempt is under way.');
        }

        if (null !== $result->lessonStatus && !\in_array($result->lessonStatus, Prerequisites::STATUSES, true)) {
            throw SequencingRefused::because('A lesson status is one SCORM 1.2 defines.');
        }

        $activity = $this->tree->get($identifier);
        $primary = $activity->sequencing->primaryObjective();

        if (null !== $result->completed) {
            $this->data->records[$identifier]['completed'] = $result->completed;
        }

        if (null !== $result->passed) {
            $this->data->setSatisfied($activity, $primary, $result->passed);
        }

        if (null !== $result->scaledScore) {
            $this->data->setMeasure($activity, $primary, max(-1.0, min(1.0, $result->scaledScore)));
        }

        foreach ($result->objectives as $id => $status) {
            $objective = array_find($activity->sequencing->objectives, static fn (Objective $candidate): bool => $candidate->id === $id);

            if (!$objective instanceof Objective) {
                continue;
            }

            if (null !== $status->satisfied) {
                $this->data->setSatisfied($activity, $objective, $status->satisfied);
            }

            if (null !== $status->measure) {
                $this->data->setMeasure($activity, $objective, max(-1.0, min(1.0, $status->measure)));
            }
        }

        if ($result->duration instanceof Duration) {
            $this->data->records[$identifier]['duration'] = $result->duration->centiseconds;
        }

        if ($result->suspend) {
            $this->data->records[$identifier]['suspended'] = true;
        }

        if (null !== $result->lessonStatus) {
            $this->data->records[$identifier]['lesson'] = $result->lessonStatus;
        }

        $this->rollup->rollup($identifier);
    }

    private function start(): SequencingResult
    {
        if (null !== $this->data->current) {
            return $this->refused('A session is under way: it cannot start again.');
        }

        if (null !== $this->data->suspended) {
            return $this->refused('A suspended session is resumed by resume all, not started again.');
        }

        return $this->deliverFlow($this->tree->root, true, false);
    }

    private function resumeAll(): SequencingResult
    {
        if (null !== $this->data->current || null === $this->data->suspended) {
            return $this->refused('Only a suspended session, with no activity under way, is resumed.');
        }

        return $this->deliver($this->data->suspended);
    }

    private function flowRequest(bool $forward): SequencingResult
    {
        $current = $this->data->current;
        $parent = null === $current ? null : $this->tree->parent($current);

        if (null === $current || !$parent instanceof Activity || !$parent->sequencing->controlMode->flow) {
            return $this->refused('Flow is not allowed from the current activity.');
        }

        if (!$forward && $parent->sequencing->controlMode->forwardOnly) {
            return $this->refused('The parent of the current activity moves forward only.');
        }

        $override = $this->terminateIfActive();

        return $this->sequence($override ?? ($forward ? RuleAction::Continue : RuleAction::Previous), null);
    }

    private function choice(?string $target): SequencingResult
    {
        if (null === $target || !$this->tree->has($target)) {
            return $this->refused('A choice names an activity of the tree.');
        }

        $reason = $this->flow->choiceRefusal($target);

        if (null !== $reason) {
            return $this->refused($reason);
        }

        $override = $this->terminateIfActive();

        return $this->sequence($override, $target);
    }

    private function exit(): SequencingResult
    {
        if (null === $this->data->current || !$this->data->record($this->data->current)['active']) {
            return $this->refused('Only an activity under way is exited.');
        }

        return $this->sequence($this->terminate() ?? RuleAction::Exit, null);
    }

    private function exitAllRequest(): SequencingResult
    {
        if (null === $this->data->current) {
            return $this->refused('No session is under way.');
        }

        return $this->endSession();
    }

    private function suspendAll(): SequencingResult
    {
        $current = $this->data->current;

        if (null === $current) {
            return $this->refused('No session is under way.');
        }

        $this->rollup->rollup($current);

        foreach ($this->tree->path($current) as $activity) {
            $this->data->records[$activity->identifier] = ['active' => false, 'suspended' => true] + $this->data->record($activity->identifier);
        }

        $this->data->suspended = $current;
        $this->data->current = null;

        return new SequencingResult(Outcome::SessionEnded, $this->data->state());
    }

    private function terminateIfActive(): ?RuleAction
    {
        return null !== $this->data->current && $this->data->record($this->data->current)['active'] ? $this->terminate() : null;
    }

    /**
     * Ends the attempt on the current activity, applies the exit rules of its ancestors and its post-condition rules:
     * the sequencing request they make, if any — `exit` once the termination reaches the root.
     */
    private function terminate(): ?RuleAction
    {
        $current = (string) $this->data->current;
        $this->attempts->endAttempt($current);

        foreach (\array_slice($this->tree->path($current), 0, -1) as $ancestor) {
            if ($this->rules->ruleAction($ancestor, RuleTiming::Exit, [RuleAction::Exit]) instanceof RuleAction) {
                $this->attempts->endActiveBetween($current, $ancestor->identifier);
                $this->attempts->endAttempt($ancestor->identifier);
                $current = $ancestor->identifier;

                break;
            }
        }

        $this->data->current = $current;

        while (true) {
            $activity = $this->tree->get($current);
            $action = $this->rules->ruleAction($activity, RuleTiming::Post);
            $parent = $this->tree->parent($current);

            if (RuleAction::ExitParent === $action && $parent instanceof Activity) {
                $current = $parent->identifier;
                $this->data->current = $current;
                $this->attempts->endAttempt($current);

                continue;
            }

            if (!$parent instanceof Activity && RuleAction::Retry !== $action && RuleAction::RetryAll !== $action) {
                return RuleAction::ExitAll === $action ? RuleAction::ExitAll : RuleAction::Exit;
            }

            return $action;
        }
    }

    private function sequence(?RuleAction $action, ?string $target): SequencingResult
    {
        $current = $this->tree->get((string) ($this->data->current ?? $target));

        return match ($action) {
            RuleAction::Exit => $this->tree->parent($current->identifier) instanceof Activity ? new SequencingResult(Outcome::Waiting, $this->data->state()) : $this->endSession(),
            RuleAction::ExitAll => $this->endSession(),
            RuleAction::RetryAll => $this->retryAll(),
            RuleAction::Retry => $current->isLeaf() ? $this->deliver($current->identifier) : $this->deliverFlow($current, true, false),
            RuleAction::Continue, RuleAction::Previous => $this->flowFrom($current, RuleAction::Continue === $action),
            default => $this->chosen($this->tree->get((string) $target)),
        };
    }

    private function chosen(Activity $target): SequencingResult
    {
        return $target->isLeaf() ? $this->deliver($target->identifier) : $this->deliverFlow($target, true, false);
    }

    private function retryAll(): SequencingResult
    {
        $this->endSession();

        return $this->deliverFlow($this->tree->root, true, false);
    }

    private function endSession(): SequencingResult
    {
        if (null !== $this->data->current) {
            foreach (array_reverse($this->tree->path($this->data->current)) as $activity) {
                if ($this->data->record($activity->identifier)['active']) {
                    $this->attempts->endAttempt($activity->identifier);
                }
            }
        }

        $this->data->current = null;

        return new SequencingResult(Outcome::SessionEnded, $this->data->state());
    }

    private function flowFrom(Activity $from, bool $forward): SequencingResult
    {
        $next = $this->flow->next($from, $forward);

        if (false === $next) {
            return $this->endSession();
        }

        return $next instanceof Activity ? $this->deliverFlow($next, $forward, true) : $this->refused($forward ? 'No activity follows by flow.' : 'No activity precedes by flow.');
    }

    private function deliverFlow(Activity $candidate, bool $forward, bool $checkFirst): SequencingResult
    {
        $leaf = $this->flow->flow($candidate, $forward, $checkFirst);

        if (false === $leaf && $forward) {
            return $this->endSession();
        }

        return $leaf instanceof Activity ? $this->deliver($leaf->identifier) : $this->refused('Flow finds no activity to deliver.');
    }

    private function deliver(string $identifier): SequencingResult
    {
        $target = $this->tree->get($identifier);

        if (!$target->isLeaf()) {
            return $this->refused(\sprintf('The activity "%s" is not a leaf.', $identifier));
        }

        $path = $this->tree->path($identifier);

        foreach ($path as $activity) {
            $reason = $this->rules->checkActivity($activity);

            if (null !== $reason) {
                return $this->refused($reason);
            }
        }

        $current = $this->data->current;

        if (null !== $current && $current !== $identifier) {
            // The request ended the attempt on the current activity before it came here; those above it end now.
            $this->attempts->endActiveBetween($current, $this->flow->commonAncestor($current, $identifier)->identifier);
        }

        if (null !== $this->data->suspended) {
            foreach ($this->tree->path($this->data->suspended) as $activity) {
                if (!\in_array($activity, $path, true)) {
                    $this->data->records[$activity->identifier] = ['suspended' => false] + $this->data->record($activity->identifier);
                }
            }

            $this->data->suspended = null;
        }

        foreach ($path as $activity) {
            $record = $this->data->record($activity->identifier);

            if ($record['active']) {
                continue;
            }

            if ($record['suspended']) {
                $this->data->records[$activity->identifier] = ['suspended' => false, 'active' => true] + $record;

                continue;
            }

            $this->attempts->newAttempt($activity);
        }

        $this->data->current = $identifier;

        return new SequencingResult(Outcome::Delivered, $this->data->state(), $identifier);
    }

    private function refused(string $reason): SequencingResult
    {
        return new SequencingResult(Outcome::Refused, $this->data->state(), null, $reason);
    }
}
