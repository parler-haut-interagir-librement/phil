<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use LogicException;
use Phil\Enums\Attribute\TransitionsTo;
use Phil\Enums\Tests\Fixture\Lifecycle;
use Phil\Enums\Tests\Fixture\StrayTransition;
use Phil\Enums\Traits\AsStatefulEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsStatefulEnumTrait::class)]
final class AsStatefulEnumTraitTest extends TestCase
{
    #[Test]
    public function aCaseMovesOnlyWhereItsAttributeSays(): void
    {
        self::assertTrue(Lifecycle::Draft->canTransitionTo(Lifecycle::Review));
        self::assertFalse(Lifecycle::Draft->canTransitionTo(Lifecycle::Published));
        self::assertTrue(Lifecycle::Review->canTransitionTo(Lifecycle::Rework));
        self::assertFalse(Lifecycle::Review->canTransitionTo(Lifecycle::Draft));
        self::assertFalse(Lifecycle::Archived->canTransitionTo(Lifecycle::Draft));
    }

    #[Test]
    public function theNextStatesComeInDeclarationOrder(): void
    {
        self::assertSame([Lifecycle::Rework, Lifecycle::Published], Lifecycle::Review->nextStates()->all());
        self::assertSame([], Lifecycle::Archived->nextStates()->all());
    }

    #[Test]
    public function aCaseThatMovesNowhereIsFinal(): void
    {
        self::assertTrue(Lifecycle::Archived->isFinal());
        self::assertFalse(Lifecycle::Published->isFinal());
    }

    #[Test]
    public function theInitialStatesAreThoseNoCaseMovesTo(): void
    {
        self::assertSame([Lifecycle::Draft], Lifecycle::initialStates()->all());
    }

    #[Test]
    public function everyTransitionIsAPair(): void
    {
        self::assertSame([
            [Lifecycle::Draft, Lifecycle::Review],
            [Lifecycle::Review, Lifecycle::Rework],
            [Lifecycle::Review, Lifecycle::Published],
            [Lifecycle::Rework, Lifecycle::Review],
            [Lifecycle::Published, Lifecycle::Archived],
        ], Lifecycle::transitions());
    }

    #[Test]
    public function aTransitionToAnotherEnumIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains(StrayTransition::class.'::Lost moves to '.Lifecycle::class.'::Draft, a case of another enum.');

        StrayTransition::Lost->nextStates();
    }

    #[Test]
    public function theAttributeKeepsAListEvenFromNamedArguments(): void
    {
        self::assertSame([Lifecycle::Draft, Lifecycle::Review], new TransitionsTo(first: Lifecycle::Draft, second: Lifecycle::Review)->cases);
    }
}
