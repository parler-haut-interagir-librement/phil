<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit\Domain\Friction;

use Phil\BotDetectionBundle\Domain\Friction\Friction;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrictionPolicy::class)]
final class FrictionPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{FrictionPolicy}>
     */
    public static function policies(): iterable
    {
        yield 'robot only' => [FrictionPolicy::robotOnly()];
        yield 'robot or unknown' => [FrictionPolicy::robotOrUnknown()];
    }

    #[DataProvider('policies')]
    public function testAHumanVerdictAddsNoFrictionUnderAnyPolicy(FrictionPolicy $policy): void
    {
        self::assertSame(Friction::None, $policy->frictionFor(Verdict::Human));
    }

    #[DataProvider('policies')]
    public function testARobotVerdictAddsOneStepUnderAnyPolicy(FrictionPolicy $policy): void
    {
        self::assertSame(Friction::Step, $policy->frictionFor(Verdict::Robot));
    }

    public function testUnderRobotOnlyAnUnknownVerdictPassesLikeAHumanOne(): void
    {
        $policy = FrictionPolicy::robotOnly();

        self::assertFalse($policy->unknownAddsFriction);
        self::assertSame(Friction::None, $policy->frictionFor(Verdict::Unknown));
    }

    public function testUnderRobotOrUnknownAnUnknownVerdictAddsOneStep(): void
    {
        $policy = FrictionPolicy::robotOrUnknown();

        self::assertTrue($policy->unknownAddsFriction);
        self::assertSame(Friction::Step, $policy->frictionFor(Verdict::Unknown));
    }
}
