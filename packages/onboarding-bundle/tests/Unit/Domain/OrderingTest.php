<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Domain;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Flow\Ordering;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ordering::class)]
final class OrderingTest extends TestCase
{
    public function testWithoutWishesTheOrderIsTheDeclaration(): void
    {
        self::assertSame(['c', 'a', 'b'], $this->sort([$this->step('c'), $this->step('a'), $this->step('b')]));
        self::assertSame([], Ordering::sort([]));
    }

    public function testAStepComesBeforeOrAfterOthers(): void
    {
        self::assertSame(['b', 'a', 'c'], $this->sort([$this->step('a', after: ['b']), $this->step('b'), $this->step('c')]));
        self::assertSame(['a', 'c', 'b'], $this->sort([$this->step('a'), $this->step('b'), $this->step('c', before: ['b'])]));
        self::assertSame(['d', 'a', 'b', 'c'], $this->sort([
            $this->step('a'),
            $this->step('b', after: ['a', 'a']),
            $this->step('c'),
            $this->step('d', before: ['a', 'b']),
        ]));
    }

    public function testTheStepsAStepWaitsForComeRightBeforeIt(): void
    {
        self::assertSame(['c', 'a', 'b', 'd'], $this->sort([$this->step('a', after: ['c']), $this->step('b'), $this->step('c'), $this->step('d')]));
    }

    public function testAStepNotDeclaredIsPassedOver(): void
    {
        self::assertSame(['a', 'b'], $this->sort([$this->step('a', before: ['missing'], after: ['gone']), $this->step('b')]));
    }

    public function testAStepIsPlacedOnceHoweverManyWaitForIt(): void
    {
        $steps = [$this->step('a0'), $this->step('b0')];

        for ($level = 1; $level <= 40; ++$level) {
            $earlier = ['a'.($level - 1), 'b'.($level - 1)];
            $steps[] = $this->step('a'.$level, after: $earlier);
            $steps[] = $this->step('b'.$level, after: $earlier);
        }

        $sorted = $this->sort(array_reverse($steps));

        self::assertCount(82, $sorted);
        self::assertSame(['b0', 'a0'], \array_slice($sorted, 0, 2));
    }

    public function testACircleIsRefused(): void
    {
        $this->expectException(InvalidFlow::class);
        $this->expectExceptionMessageIsOrContains('The steps "a", "b" ask to come before one another in a circle.');

        Ordering::sort([$this->step('a', after: ['b']), $this->step('b', after: ['a']), $this->step('c', before: ['z'])]);
    }

    public function testAStepAfterItselfIsACircle(): void
    {
        $this->expectExceptionMessageIsOrContains('The steps "b" ask to come');

        Ordering::sort([$this->step('a'), $this->step('b', before: ['b'])]);
    }

    /**
     * @param list<StepDefinition> $steps
     *
     * @return list<string>
     */
    private function sort(array $steps): array
    {
        return array_map(static fn (StepDefinition $step): string => $step->id, Ordering::sort($steps));
    }

    /**
     * @param list<string> $before
     * @param list<string> $after
     */
    private function step(string $id, array $before = [], array $after = []): StepDefinition
    {
        return new StepDefinition($id, $id, before: $before, after: $after);
    }
}
