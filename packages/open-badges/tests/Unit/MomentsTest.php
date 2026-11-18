<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit;

use Phil\OpenBadges\Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The one reader of the moments of a period of validity, which the issuers and the verifier share: each part within
 * its range, nothing carried over to another date.
 */
#[CoversClass(Credentials::class)]
final class MomentsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function momentsRead(): iterable
    {
        yield 'in UTC' => ['2026-11-07T10:00:00Z', '2026-11-07T10:00:00.000000+00:00'];
        yield 'with an offset' => ['2026-11-07T10:00:00-05:30', '2026-11-07T10:00:00.000000-05:30'];
        yield 'the widest offsets' => ['2026-11-07T10:00:00+23:59', '2026-11-07T10:00:00.000000+23:59'];
        yield 'a fraction' => ['2026-11-07T10:00:00.25Z', '2026-11-07T10:00:00.250000+00:00'];
        yield 'a fraction to the microsecond' => ['2026-11-07T10:00:00.123456Z', '2026-11-07T10:00:00.123456+00:00'];
        yield 'a finer fraction, cut to the microsecond' => ['2026-11-07T10:00:00.1234569Z', '2026-11-07T10:00:00.123456+00:00'];
        yield 'the last moment of a day' => ['2026-12-31T23:59:59Z', '2026-12-31T23:59:59.000000+00:00'];
        yield 'the first moment' => ['0001-01-01T00:00:00Z', '0001-01-01T00:00:00.000000+00:00'];
        yield '29 February of a leap year' => ['2028-02-29T00:00:00Z', '2028-02-29T00:00:00.000000+00:00'];
    }

    #[DataProvider('momentsRead')]
    public function testAMomentIsRead(string $moment, string $read): void
    {
        self::assertSame($read, Credentials::parseMoment($moment)?->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function momentsRefused(): iterable
    {
        yield 'a date' => ['2026-11-07'];
        yield 'no offset' => ['2026-11-07T10:00:00'];
        yield 'no seconds' => ['2026-11-07T10:00Z'];
        yield 'a space' => ['2026-11-07 10:00:00Z'];
        yield 'a lower-case separator' => ['2026-11-07t10:00:00Z'];
        yield 'a lower-case zone' => ['2026-11-07T10:00:00z'];
        yield 'an empty fraction' => ['2026-11-07T10:00:00.Z'];
        yield 'text before' => ['x2026-11-07T10:00:00Z'];
        yield 'text after' => ['2026-11-07T10:00:00Zx'];
        yield 'a line after' => ["2026-11-07T10:00:00Z\n"];
        yield 'an offset without colon' => ['2026-11-07T10:00:00+0500'];
        yield '30 February' => ['2026-02-30T00:00:00Z'];
        yield '29 February of a common year' => ['2026-02-29T00:00:00Z'];
        yield 'month 13' => ['2026-13-01T00:00:00Z'];
        yield 'month 0' => ['2026-00-01T00:00:00Z'];
        yield 'day 0' => ['2026-01-00T00:00:00Z'];
        yield 'year 0' => ['0000-01-01T00:00:00Z'];
        yield 'hour 24' => ['2026-01-01T24:00:00Z'];
        yield 'minute 60' => ['2026-01-01T00:60:00Z'];
        yield 'a leap second' => ['2026-12-31T23:59:60Z'];
        yield 'an offset of 24 hours' => ['2026-01-01T00:00:00+24:00'];
        yield 'an offset of 60 minutes' => ['2026-01-01T00:00:00-00:60'];
    }

    #[DataProvider('momentsRefused')]
    public function testAMomentOutOfItsRangesIsRefused(string $moment): void
    {
        self::assertNull(Credentials::parseMoment($moment));
    }
}
