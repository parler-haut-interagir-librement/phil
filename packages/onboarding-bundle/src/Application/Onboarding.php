<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Phil\OnboardingBundle\Application\Contract\AnswerGrants;
use Phil\OnboardingBundle\Application\Contract\StepChecker;
use Phil\OnboardingBundle\Application\Event\FlowDismissed;
use Phil\OnboardingBundle\Application\Event\FlowFinished;
use Phil\OnboardingBundle\Application\Event\FlowReset;
use Phil\OnboardingBundle\Application\Event\QuestionAnswered;
use Phil\OnboardingBundle\Application\Event\StepCompleted;
use Phil\OnboardingBundle\Application\Event\StepSkipped;
use Phil\OnboardingBundle\Application\Exception\StepRefused;
use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Domain\Exception\InvalidAnswer;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;
use Phil\OnboardingBundle\Domain\Flow\Question;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * The entry point: where a user stands in a flow, and the changes they or the application make to it — a step
 * completed, answered or skipped, the flow dismissed or started again, every progress of a user forgotten.
 *
 * Reading the progress asks the checker of each step done by a checker and not completed yet, unless the flow is
 * dismissed, and records the steps they say are done. Every change dispatches its event, made of identifiers, after it
 * is saved. The service keeps no state between requests.
 */
final readonly class Onboarding
{
    /**
     * @param ServiceProviderInterface<StepChecker> $checkers by the service identifier the steps name
     */
    public function __construct(
        private FlowCatalog $catalog,
        private ProgressRepository $repository,
        private ServiceProviderInterface $checkers,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private AnswerGrants $grants = new NoAnswerGrants(),
    ) {
    }

    /**
     * @throws UnknownFlow      when no flow has the identifier
     * @throws InvalidProgress  when a checker completes a step of a user identifier that cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function progress(string $user, string $flow): FlowProgress
    {
        $definition = $this->catalog->get($flow);
        $progress = $this->repository->find($user, $flow);

        if ($progress?->isDismissed() ?? false) {
            return $this->view($definition, $progress);
        }

        $events = [];

        foreach ($definition->steps as $step) {
            if (null === $step->checker || StepState::Completed === $progress?->stateOf($step->id)) {
                continue;
            }

            if ($this->checkers->get($step->checker)->isComplete($user, $flow, $step->id)) {
                $progress ??= Progress::start($user, $flow, $this->clock->now());
                $progress->complete($step->id, $this->clock->now());
                $events[] = new StepCompleted($user, $flow, $step->id);
            }
        }

        if ($progress instanceof Progress && [] !== $events) {
            $this->save($definition, $progress, $events);
        }

        return $this->view($definition, $progress);
    }

    /**
     * Records a step completed, whatever completes it: for the application's own code.
     *
     * @throws UnknownFlow      when the flow or the step is not declared
     * @throws InvalidProgress  when the user identifier cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function complete(string $user, string $flow, string $step): FlowProgress
    {
        [$definition] = $this->find($flow, $step);
        $progress = $this->repository->find($user, $flow) ?? Progress::start($user, $flow, $this->clock->now());

        if ($progress->complete($step, $this->clock->now())) {
            $this->save($definition, $progress, [new StepCompleted($user, $flow, $step)]);
        }

        return $this->view($definition, $progress);
    }

    /**
     * Records a step ticked by the person: one that neither an event nor a checker completes.
     *
     * @throws UnknownFlow      when the flow or the step is not declared
     * @throws StepRefused      when the step is completed by an event or a checker
     * @throws InvalidProgress  when the user identifier cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function markDone(string $user, string $flow, string $step): FlowProgress
    {
        [, $definition] = $this->find($flow, $step);

        if (Completion::Manual !== $definition->completion) {
            throw StepRefused::notManual($flow, $step);
        }

        return $this->complete($user, $flow, $step);
    }

    /**
     * Records the answer to the question of a pending step: the application grants what the choices ask for, through
     * `AnswerGrants`, then the step is completed.
     *
     * @param list<string> $choices the identifiers of the choices picked
     *
     * @throws UnknownFlow      when the flow or the step is not declared
     * @throws StepRefused      when the step asks no question, or is no longer pending
     * @throws InvalidAnswer    when the question cannot take these choices
     * @throws InvalidProgress  when the user identifier cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function answer(string $user, string $flow, string $step, array $choices): FlowProgress
    {
        [$definition, $stepDefinition] = $this->find($flow, $step);

        if (!$stepDefinition->question instanceof Question) {
            throw StepRefused::notAQuestion($flow, $step);
        }

        $progress = $this->repository->find($user, $flow) ?? Progress::start($user, $flow, $this->clock->now());

        if (StepState::Pending !== $progress->stateOf($step)) {
            throw StepRefused::alreadyDone($flow, $step);
        }

        $answer = new Answer($user, $flow, $step, $stepDefinition->question->pick($choices));
        $this->grants->grant($answer);
        $progress->complete($step, $this->clock->now());
        $this->save($definition, $progress, [new QuestionAnswered($user, $flow, $step, $answer->choices), new StepCompleted($user, $flow, $step)]);

        return $this->view($definition, $progress);
    }

    /**
     * Records a pending step skipped; a completed step stays completed.
     *
     * @throws UnknownFlow      when the flow or the step is not declared
     * @throws StepRefused      when the step cannot be skipped
     * @throws InvalidProgress  when the user identifier cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function skip(string $user, string $flow, string $step): FlowProgress
    {
        [$definition, $stepDefinition] = $this->find($flow, $step);

        if (!$stepDefinition->skippable) {
            throw StepRefused::notSkippable($flow, $step);
        }

        $progress = $this->repository->find($user, $flow) ?? Progress::start($user, $flow, $this->clock->now());

        if ($progress->skip($step, $this->clock->now())) {
            $this->save($definition, $progress, [new StepSkipped($user, $flow, $step)]);
        }

        return $this->view($definition, $progress);
    }

    /**
     * Puts the flow away for the user: its checklist and its tour no longer show, its steps are still recorded.
     *
     * @throws UnknownFlow      when no flow has the identifier
     * @throws InvalidProgress  when the user identifier cannot be kept
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function dismiss(string $user, string $flow): FlowProgress
    {
        $definition = $this->catalog->get($flow);
        $progress = $this->repository->find($user, $flow) ?? Progress::start($user, $flow, $this->clock->now());

        if ($progress->dismiss($this->clock->now())) {
            $this->save($definition, $progress, [new FlowDismissed($user, $flow)]);
        }

        return $this->view($definition, $progress);
    }

    /**
     * Starts the flow again for the user, every step pending; the checkers are asked again on the next reading.
     *
     * @throws UnknownFlow      when no flow has the identifier
     * @throws ProgressNotSaved when another request saved the same progress at the same time
     */
    public function reset(string $user, string $flow): FlowProgress
    {
        $definition = $this->catalog->get($flow);
        $progress = $this->repository->find($user, $flow);

        if ($progress instanceof Progress) {
            $progress->reset($this->clock->now());
            $this->repository->save($progress);
            $this->events?->dispatch(new FlowReset($user, $flow));
        }

        return $this->view($definition, $progress);
    }

    /**
     * Removes every progress of a user, to erase their account.
     */
    public function forget(string $user): void
    {
        $this->repository->removeAllOf($user);
    }

    /**
     * @return array{FlowDefinition, StepDefinition}
     *
     * @throws UnknownFlow when the flow or the step is not declared
     */
    private function find(string $flow, string $step): array
    {
        $definition = $this->catalog->get($flow);

        return [$definition, $definition->step($step) ?? throw UnknownFlow::step($flow, $step)];
    }

    /**
     * Saves the progress, recording it finished when no step is pending any more, then dispatches the events.
     *
     * @param list<object> $events
     */
    private function save(FlowDefinition $definition, Progress $progress, array $events): void
    {
        $pending = array_any($definition->steps, static fn (StepDefinition $step): bool => StepState::Pending === $progress->stateOf($step->id));

        if (!$pending && $progress->finish($this->clock->now())) {
            $events[] = new FlowFinished($progress->user, $definition->id);
        }

        $this->repository->save($progress);

        foreach ($events as $event) {
            $this->events?->dispatch($event);
        }
    }

    private function view(FlowDefinition $definition, ?Progress $progress): FlowProgress
    {
        return new FlowProgress(
            $definition,
            array_map(
                static fn (StepDefinition $step): StepProgress => new StepProgress($step, $progress?->stateOf($step->id) ?? StepState::Pending, $progress?->timeOf($step->id)),
                $definition->steps,
            ),
            $progress?->isDismissed() ?? false,
        );
    }
}
