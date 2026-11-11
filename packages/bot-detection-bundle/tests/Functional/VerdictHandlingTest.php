<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Functional;

use Phil\BotDetectionBundle\Application\VerdictHandler;
use Phil\BotDetectionBundle\Domain\Friction\Friction;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use Phil\BotDetectionBundle\Tests\Unit\Application\Fixture\InMemoryRobotVerdictCounter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Two surfaces wired by the application as `doc/configuration.md` shows, each handling the verdict submitted with
 * its form: the policy decides the friction, and only the surface's own counter moves.
 */
#[CoversClass(VerdictHandler::class)]
#[CoversClass(FrictionPolicy::class)]
#[CoversClass(Verdict::class)]
final class VerdictHandlingTest extends FunctionalTestCase
{
    public function testARobotVerdictAddsAStepAndIsCountedOnItsOwnSurfaceOnly(): void
    {
        $friction = $this->handler('sign_up')->handle(Verdict::fromSubmitted('robot'));

        self::assertSame(Friction::Step, $friction);
        self::assertSame(1, $this->counter('sign_up')->count);
        self::assertSame(0, $this->counter('payment')->count);
    }

    /**
     * A missing verdict adds friction where the surface's policy says so, and is never counted.
     */
    public function testAMissingVerdictFollowsEachSurfacePolicyAndIsNeverCounted(): void
    {
        self::assertSame(Friction::Step, $this->handler('sign_up')->handle(Verdict::fromSubmitted(null)));
        self::assertSame(Friction::None, $this->handler('payment')->handle(Verdict::fromSubmitted(null)));
        self::assertSame(0, $this->counter('sign_up')->count);
        self::assertSame(0, $this->counter('payment')->count);
    }

    public function testAHumanVerdictAddsNothingAndIsNotCounted(): void
    {
        self::assertSame(Friction::None, $this->handler('payment')->handle(Verdict::fromSubmitted('human')));
        self::assertSame(0, $this->counter('payment')->count);
    }

    private function handler(string $surface): VerdictHandler
    {
        return $this->service('app.bot.handler.'.$surface, VerdictHandler::class);
    }

    private function counter(string $surface): InMemoryRobotVerdictCounter
    {
        return $this->service('app.bot.counter.'.$surface, InMemoryRobotVerdictCounter::class);
    }
}
