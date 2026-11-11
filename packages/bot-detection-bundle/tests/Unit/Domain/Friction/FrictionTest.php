<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit\Domain\Friction;

use Phil\BotDetectionBundle\Domain\Friction\Friction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Friction::class)]
final class FrictionTest extends TestCase
{
    /**
     * The strongest outcome is one step. A refusal is not a case that exists.
     */
    public function testThereAreExactlyTwoOutcomesAndNoRefusal(): void
    {
        self::assertSame([Friction::None, Friction::Step], Friction::cases());
    }

    public function testOnlyAStepAddsFriction(): void
    {
        self::assertTrue(Friction::Step->adds());
        self::assertFalse(Friction::None->adds());
    }
}
