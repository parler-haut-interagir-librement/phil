<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\CrackTimes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrackTimes::class)]
final class CrackTimesTest extends TestCase
{
    public function testTheSecondsOfEachSetting(): void
    {
        $times = CrackTimes::fromGuesses(1e10);

        self::assertEqualsWithDelta(36e10, $times->onlineThrottled, 1.0);
        self::assertSame(1e9, $times->onlineUnthrottled);
        self::assertSame(1e6, $times->offlineSlowHash);
        self::assertSame(1.0, $times->offlineFastHash);
    }
}
