<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit\Application;

use Phil\BotDetectionBundle\Application\VerdictHandler;
use Phil\BotDetectionBundle\Domain\Friction\Friction;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use Phil\BotDetectionBundle\Tests\Unit\Application\Fixture\InMemoryRobotVerdictCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerdictHandler::class)]
final class VerdictHandlerTest extends TestCase
{
    public function testARobotVerdictIsCountedAndAddsOneStep(): void
    {
        $counter = new InMemoryRobotVerdictCounter();

        $friction = new VerdictHandler(FrictionPolicy::robotOnly(), $counter)->handle(Verdict::Robot);

        self::assertSame(Friction::Step, $friction);
        self::assertSame(1, $counter->count);
    }

    public function testAHumanVerdictIsNotCountedAndAddsNothing(): void
    {
        $counter = new InMemoryRobotVerdictCounter();

        $friction = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $counter)->handle(Verdict::Human);

        self::assertSame(Friction::None, $friction);
        self::assertSame(0, $counter->count);
    }

    /**
     * The policy may slow down an unknown verdict, but the counter counts robot verdicts and nothing else:
     * a blocked script is not a robot.
     */
    public function testAnUnknownVerdictIsNeverCountedEvenWhenItAddsFriction(): void
    {
        $counter = new InMemoryRobotVerdictCounter();

        $friction = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $counter)->handle(Verdict::Unknown);

        self::assertSame(Friction::Step, $friction);
        self::assertSame(0, $counter->count);
    }

    public function testEachRobotVerdictCountsOnce(): void
    {
        $counter = new InMemoryRobotVerdictCounter();
        $handler = new VerdictHandler(FrictionPolicy::robotOnly(), $counter);

        $handler->handle(Verdict::Robot);
        $handler->handle(Verdict::Human);
        $handler->handle(Verdict::Robot);

        self::assertSame(2, $counter->count);
    }
}
