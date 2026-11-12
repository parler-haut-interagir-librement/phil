<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Retention;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CounterRollup;
use Phil\AnalyticsBundle\Application\Retention\CounterRetentionRun;
use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CounterRetentionRun::class)]
final class CounterRetentionRunTest extends TestCase
{
    public function testTheDaysBeforeTheFirstDayKeptAreRolledUpUnderTheThreshold(): void
    {
        $rollup = new class implements CounterRollup {
            /**
             * @var list<array{string, int}>
             */
            public array $calls = [];

            #[Override]
            public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void
            {
                $this->calls[] = [$firstDailyDay->value, $threshold->value];
            }

            #[Override]
            public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array
            {
                return [];
            }
        };

        $first = new CounterRetentionRun($rollup, new CounterRetention(13), SmallNumberThreshold::of(7))->runFor(UtcDay::fromString('2026-11-11'));

        self::assertSame('2025-10-01', $first->value);
        self::assertSame([['2025-10-01', 7]], $rollup->calls);
    }
}
