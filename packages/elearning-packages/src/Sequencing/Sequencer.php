<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;

/**
 * The sequencer of SCORM 2004, and of the prerequisites of SCORM 1.2: a deterministic state machine over an activity
 * tree. Each request takes the state a platform kept and returns a new one, with what to launch. It holds nothing
 * between two requests: one instance serves every learner and every worker.
 *
 * A platform begins a session with `SequencingState::begin()`, sends `NavigationRequest::Start` — or `ResumeAll` for a
 * suspended session —, launches the activity delivered, hands the sequencer what its content reports with `report()`,
 * then sends the next navigation request: `Continue`, `Previous`, `Choice` with the identifier of an activity, `Exit`,
 * `ExitAll` or `SuspendAll`. The state of every result is the one to keep, whatever its outcome.
 */
final readonly class Sequencer
{
    /**
     * @param ?string $target the activity a `Choice` names; ignored by the other requests
     */
    public function navigate(ActivityTree $tree, SequencingState $state, NavigationRequest $request, ?string $target = null): SequencingResult
    {
        return new SequencingRun($tree, $state)->navigate($request, $target);
    }

    /**
     * Records what the content of the delivered activity reported and rolls it up the tree.
     *
     * @throws SequencingRefused when no activity is delivered, or its attempt is not under way
     */
    public function report(ActivityTree $tree, SequencingState $state, AttemptResult $result): SequencingState
    {
        $run = new SequencingRun($tree, $state);
        $run->report($result);

        return $run->state();
    }

    /**
     * The `lesson_status` of SCORM 1.2 an activity reads as: the one its content last reported, else `passed` or
     * `failed` from its satisfaction, else `completed` or `incomplete`, else `not attempted`.
     */
    public function lessonStatus(ActivityTree $tree, SequencingState $state, string $activity): string
    {
        $tree->get($activity);

        return new SequencingRun($tree, $state)->lessonStatus($activity);
    }
}
