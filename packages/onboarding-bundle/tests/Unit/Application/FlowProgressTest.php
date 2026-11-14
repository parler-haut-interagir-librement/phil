<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\OnboardingBundle\Application\AlwaysTours;
use Phil\OnboardingBundle\Application\FlowProgress;
use Phil\OnboardingBundle\Application\StepProgress;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlowProgress::class)]
#[CoversClass(StepProgress::class)]
#[CoversClass(AlwaysTours::class)]
final class FlowProgressTest extends TestCase
{
    public function testTheCountsAndTheCurrentStep(): void
    {
        $progress = $this->progress([StepState::Completed, StepState::Skipped, StepState::Pending, StepState::Pending, StepState::Completed, StepState::Completed]);

        self::assertSame([6, 3, 1, 4, 66], [$progress->total(), $progress->completed(), $progress->skipped(), $progress->done(), $progress->percent()]);
        self::assertSame('s2', $progress->current()?->step->id);
        self::assertSame(StepState::Skipped, $progress->step('s1')?->state);
        self::assertNull($progress->step('nope'));
        self::assertFalse($progress->isFinished());
        self::assertTrue($progress->isActive());
        self::assertFalse($progress->isDismissed());
    }

    public function testAFlowWithNoPendingStepIsFinished(): void
    {
        $progress = $this->progress([StepState::Completed, StepState::Skipped]);

        self::assertNull($progress->current());
        self::assertTrue($progress->isFinished());
        self::assertFalse($progress->isActive());
        self::assertSame(100, $progress->percent());
        self::assertSame(0, $this->progress([StepState::Pending, StepState::Pending, StepState::Pending])->percent());
    }

    public function testADismissedFlowIsNotActive(): void
    {
        $progress = $this->progress([StepState::Pending], true);

        self::assertTrue($progress->isDismissed());
        self::assertFalse($progress->isActive());
    }

    public function testWhatAPersonMayDoWithAStep(): void
    {
        $manual = new StepDefinition('m', 'M');
        $locked = new StepDefinition('l', 'L', skippable: false);
        $event = new StepDefinition('e', 'E', Completion::Event, event: 'x');
        $at = new DateTimeImmutable('2026-11-08 10:00:00 UTC');

        self::assertSame([true, true, true, false, false], $this->abilities(new StepProgress($manual, StepState::Pending)));
        self::assertSame([false, false, false, true, false], $this->abilities(new StepProgress($event, StepState::Completed, $at)));
        self::assertSame([false, false, false, false, true], $this->abilities(new StepProgress($locked, StepState::Skipped)));
        self::assertSame([true, false, true, false, false], $this->abilities(new StepProgress($locked, StepState::Pending)));
        self::assertSame([false, true, true, false, false], $this->abilities(new StepProgress($event, StepState::Pending)));
        self::assertSame($at, new StepProgress($event, StepState::Completed, $at)->at);
        self::assertTrue(new AlwaysTours()->wantsTours('ada'));
    }

    /**
     * @return list<bool> tickable, skippable, pending, completed, skipped
     */
    private function abilities(StepProgress $step): array
    {
        return [$step->isTickable(), $step->isSkippable(), $step->isPending(), $step->isCompleted(), $step->isSkipped()];
    }

    /**
     * @param list<StepState> $states
     */
    private function progress(array $states, bool $dismissed = false): FlowProgress
    {
        $steps = array_map(static fn (int $i): StepDefinition => new StepDefinition('s'.$i, 'S'), array_keys($states));
        $flow = new FlowDefinition('f', 'F', $steps);

        return new FlowProgress($flow, array_map(static fn (StepDefinition $step, StepState $state): StepProgress => new StepProgress($step, $state), $steps, $states), $dismissed);
    }
}
