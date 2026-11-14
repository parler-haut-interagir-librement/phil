<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Application;

use Override;
use Phil\OnboardingBundle\Application\Event\FlowDismissed;
use Phil\OnboardingBundle\Application\Event\FlowFinished;
use Phil\OnboardingBundle\Application\Event\FlowReset;
use Phil\OnboardingBundle\Application\Event\StepCompleted;
use Phil\OnboardingBundle\Application\Event\StepSkipped;
use Phil\OnboardingBundle\Application\Exception\StepRefused;
use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Application\FlowProgress;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Application\StepProgress;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use Phil\OnboardingBundle\Tests\Unit\Fixture\RecordingRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(Onboarding::class)]
#[CoversClass(UnknownFlow::class)]
#[CoversClass(StepRefused::class)]
#[CoversClass(StepCompleted::class)]
#[CoversClass(StepSkipped::class)]
#[CoversClass(FlowFinished::class)]
#[CoversClass(FlowDismissed::class)]
#[CoversClass(FlowReset::class)]
final class OnboardingTest extends TestCase
{
    private Kit $kit;

    #[Override]
    protected function setUp(): void
    {
        $this->kit = new Kit();
    }

    public function testANewUserHasEveryStepPendingAndNothingIsStored(): void
    {
        $progress = $this->kit->onboarding->progress('ada', 'author');

        self::assertSame(['pending', 'pending', 'pending', 'pending'], $this->states($progress));
        self::assertSame(['profile', 'publish', 'page', 'rules'], array_map(static fn (StepProgress $step): string => $step->step->id, $progress->steps));
        self::assertFalse($progress->isDismissed());
        self::assertNull($this->kit->repository->find('ada', 'author'));
        self::assertSame(['ada author page'], $this->kit->checker->asked);
        self::assertSame([], $this->kit->events->events);
    }

    public function testACheckerCompletesItsStepWhenTheProgressIsRead(): void
    {
        $this->kit->checker->answer = true;

        $progress = $this->kit->onboarding->progress('ada', 'author');

        self::assertSame(['pending', 'pending', 'completed', 'pending'], $this->states($progress));
        self::assertSame('2026-11-08T10:00:00+00:00', $progress->step('page')?->at?->format(\DATE_ATOM));
        self::assertSame(StepState::Completed, $this->kit->repository->find('ada', 'author')?->stateOf('page'));
        self::assertEquals([new StepCompleted('ada', 'author', 'page')], $this->kit->events->events);

        $this->kit->onboarding->progress('ada', 'author');
        self::assertCount(1, $this->kit->checker->asked, 'a completed step is not checked again');
        self::assertCount(1, $this->kit->events->events);
    }

    public function testACheckerCompletesASkippedStepAndAStoredProgress(): void
    {
        $this->kit->onboarding->complete('ada', 'author', 'profile');
        $this->kit->onboarding->skip('ada', 'author', 'page');
        $this->kit->checker->answer = true;
        $this->kit->events->events = [];

        $progress = $this->kit->onboarding->progress('ada', 'author');

        self::assertSame(['completed', 'pending', 'completed', 'pending'], $this->states($progress));
        self::assertEquals([new StepCompleted('ada', 'author', 'page')], $this->kit->events->events);
    }

    public function testReadingAProgressWithNothingNewSavesNothing(): void
    {
        $this->kit->onboarding->complete('ada', 'author', 'profile');
        $saves = $this->kit->repository->saves;

        $this->kit->onboarding->progress('ada', 'author');

        self::assertSame($saves, $this->kit->repository->saves);
    }

    public function testEveryChangeIsSavedWithoutADispatcher(): void
    {
        $repository = new RecordingRepository();
        $onboarding = new Onboarding($this->kit->catalog, $repository, new ServiceLocator([]), $this->kit->clock);

        foreach (['profile', 'publish', 'page', 'rules'] as $step) {
            $onboarding->complete('ada', 'author', $step);
        }

        $onboarding->reset('ada', 'author');

        self::assertSame(5, $repository->saves);
        self::assertSame('pending', $onboarding->reset('ada', 'author')->steps[0]->state->value);
    }

    public function testADismissedFlowIsNotChecked(): void
    {
        $this->kit->onboarding->dismiss('ada', 'author');
        $this->kit->checker->answer = true;

        $progress = $this->kit->onboarding->progress('ada', 'author');

        self::assertSame([], $this->kit->checker->asked);
        self::assertTrue($progress->isDismissed());
        self::assertSame('pending', $progress->steps[2]->state->value);
    }

    public function testStepsAreCompletedAndTheFlowFinishedOnce(): void
    {
        $onboarding = $this->kit->onboarding;
        $onboarding->complete('ada', 'author', 'profile');
        $onboarding->complete('ada', 'author', 'profile');
        $onboarding->complete('ada', 'author', 'publish');

        $this->kit->clock->modify('+1 minute');
        $onboarding->skip('ada', 'author', 'page');
        $progress = $onboarding->complete('ada', 'author', 'rules');

        self::assertSame(['completed', 'completed', 'skipped', 'completed'], $this->states($progress));
        self::assertTrue($progress->isFinished());
        self::assertEquals([
            new StepCompleted('ada', 'author', 'profile'),
            new StepCompleted('ada', 'author', 'publish'),
            new StepSkipped('ada', 'author', 'page'),
            new StepCompleted('ada', 'author', 'rules'),
            new FlowFinished('ada', 'author'),
        ], $this->kit->events->events);
        self::assertSame('2026-11-08T10:01:00+00:00', $this->kit->repository->find('ada', 'author')?->finishedAt()?->format(\DATE_ATOM));

        $onboarding->complete('ada', 'author', 'page');
        self::assertEquals(new StepCompleted('ada', 'author', 'page'), $this->kit->events->events[5]);
        self::assertCount(6, $this->kit->events->events, 'finished once');
    }

    public function testAPersonTicksOnlyTheStepsDoneByHand(): void
    {
        self::assertSame('completed', $this->kit->onboarding->markDone('ada', 'author', 'profile')->steps[0]->state->value);

        foreach (['publish', 'page'] as $step) {
            try {
                $this->kit->onboarding->markDone('ada', 'author', $step);
                self::fail($step);
            } catch (StepRefused $stepRefused) {
                self::assertSame(\sprintf('The step "%s" of the flow "author" is not ticked by hand.', $step), $stepRefused->getMessage());
            }
        }
    }

    public function testAStepThatCannotBeSkippedIsRefused(): void
    {
        $this->expectException(StepRefused::class);
        $this->expectExceptionMessageIsOrContains('The step "rules" of the flow "author" cannot be skipped.');

        $this->kit->onboarding->skip('ada', 'author', 'rules');
    }

    public function testACompletedStepIsNotSkipped(): void
    {
        $this->kit->onboarding->complete('ada', 'author', 'profile');
        $this->kit->events->events = [];

        self::assertSame('completed', $this->kit->onboarding->skip('ada', 'author', 'profile')->steps[0]->state->value);
        self::assertSame([], $this->kit->events->events);
    }

    public function testUnknownFlowsAndStepsAreRefused(): void
    {
        $calls = [
            'The onboarding flow "author" has no step "missing".' => fn (): FlowProgress => $this->kit->onboarding->complete('ada', 'author', 'missing'),
            'The onboarding flow "author" has no step "lost".' => fn (): FlowProgress => $this->kit->onboarding->skip('ada', 'author', 'lost'),
            'The onboarding flow "author" has no step "gone".' => fn (): FlowProgress => $this->kit->onboarding->markDone('ada', 'author', 'gone'),
            'No onboarding flow is declared as "nope".' => fn (): FlowProgress => $this->kit->onboarding->progress('ada', 'nope'),
        ];

        foreach ($calls as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (UnknownFlow $unknownFlow) {
                self::assertSame($message, $unknownFlow->getMessage());
            }
        }

        self::assertSame([], $this->kit->repository->ofUser('ada'));
    }

    public function testDismissedOnceAndResetToTheStart(): void
    {
        $onboarding = $this->kit->onboarding;
        $onboarding->complete('ada', 'author', 'profile');
        self::assertTrue($onboarding->dismiss('ada', 'author')->isDismissed());
        $onboarding->dismiss('ada', 'author');

        $this->kit->clock->modify('+1 hour');
        $progress = $onboarding->reset('ada', 'author');

        self::assertSame(['pending', 'pending', 'pending', 'pending'], $this->states($progress));
        self::assertFalse($progress->isDismissed());
        self::assertSame('2026-11-08T11:00:00+00:00', $this->kit->repository->find('ada', 'author')?->startedAt()->format(\DATE_ATOM));
        self::assertEquals([
            new StepCompleted('ada', 'author', 'profile'),
            new FlowDismissed('ada', 'author'),
            new FlowReset('ada', 'author'),
        ], $this->kit->events->events);
    }

    public function testResettingAFlowNeverStartedChangesNothing(): void
    {
        $progress = $this->kit->onboarding->reset('ada', 'author');

        self::assertSame(4, $progress->total());
        self::assertNull($this->kit->repository->find('ada', 'author'));
        self::assertSame([], $this->kit->events->events);
    }

    public function testAUserIsForgotten(): void
    {
        $this->kit->onboarding->complete('ada', 'author', 'profile');
        $this->kit->onboarding->complete('ada', 'other', 'first');
        $this->kit->onboarding->complete('bob', 'author', 'profile');

        $this->kit->onboarding->forget('ada');

        self::assertSame([], $this->kit->repository->ofUser('ada'));
        self::assertInstanceOf(Progress::class, $this->kit->repository->find('bob', 'author'));
    }

    /**
     * @return list<string>
     */
    private function states(FlowProgress $progress): array
    {
        return array_map(static fn (StepProgress $step): string => $step->state->value, $progress->steps);
    }
}
