<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Domain;

use Phil\FollowBundle\Domain\Status\FollowLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FollowLevel::class)]
final class FollowLevelTest extends TestCase
{
    public function testEachLevelSaysWhatReachesTheMember(): void
    {
        self::assertTrue(FollowLevel::Watching->notifies(false));
        self::assertTrue(FollowLevel::Watching->notifies(true));
        self::assertFalse(FollowLevel::Mentions->notifies(false));
        self::assertTrue(FollowLevel::Mentions->notifies(true));
        self::assertFalse(FollowLevel::Ignoring->notifies(true));
        self::assertFalse(FollowLevel::Custom->notifies(true));
    }

    public function testIgnoringIsNotFollowing(): void
    {
        self::assertTrue(FollowLevel::Watching->follows());
        self::assertTrue(FollowLevel::Mentions->follows());
        self::assertTrue(FollowLevel::Custom->follows());
        self::assertFalse(FollowLevel::Ignoring->follows());
    }
}
