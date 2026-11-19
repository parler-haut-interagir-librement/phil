<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use Phil\VirtualClassroom\Timestamps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Timestamps::class)]
final class TimestampsTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function moments(): iterable
    {
        yield 'seconds' => [1_700_000_000, '2023-11-14T22:13:20+00:00'];
        yield 'milliseconds' => [1_700_000_000_123, '2023-11-14T22:13:20+00:00'];
        yield 'seconds as digits' => ['1700000000', '2023-11-14T22:13:20+00:00'];
        yield 'milliseconds as digits' => ['1700000000999', '2023-11-14T22:13:20+00:00'];
        yield 'seconds as a float' => [1_700_000_000.75, '2023-11-14T22:13:20+00:00'];
        yield 'the last second before milliseconds' => [99_999_999_999, null];
        yield 'the first millisecond read so' => [100_000_000_000, null];
        yield 'RFC 3339 in UTC' => ['2026-11-19T14:30:00Z', '2026-11-19T14:30:00+00:00'];
        yield 'RFC 3339 with an offset' => ['2026-11-19T09:30:00-05:00', '2026-11-19T14:30:00+00:00'];
        yield 'RFC 3339 with a fraction and a space' => ['2026-11-19 14:30:00.123456z', '2026-11-19T14:30:00+00:00'];
        yield 'the first second of 2000' => [946_684_800, '2000-01-01T00:00:00+00:00'];
        yield 'the last second before 2000' => [946_684_799, null];
        yield 'the first second of 2200' => [7_258_118_400, '2200-01-01T00:00:00+00:00'];
        yield 'after 2200' => [7_258_118_401, null];
        yield 'a date without a time' => ['2026-11-19', null];
        yield 'a date without a zone' => ['2026-11-19T14:30:00', null];
        yield 'a line break after' => ["2026-11-19T14:30:00Z\n", null];
        yield 'an impossible date' => ['2026-13-45T14:30:00Z', null];
        yield 'words' => ['yesterday', null];
        yield 'digits and a line break' => ["1700000000\n", null];
        yield 'a number in scientific notation' => ['1.7e9', null];
        yield 'a number with a space before' => [' 1700000000', null];
        yield 'a prefix before the date' => ['Thursday 2026-11-19T14:30:00Z', null];
        yield 'seventeen digits' => ['17000000000000000', null];
        yield 'a negative float' => [-1.5, null];
        yield 'an infinite float' => [\INF, null];
        yield 'a huge float' => [1e17, null];
        yield 'null' => [null, null];
        yield 'an array' => [[1_700_000_000], null];
        yield 'a boolean' => [true, null];
    }

    #[DataProvider('moments')]
    public function testAMoment(mixed $value, ?string $expected): void
    {
        $moment = Timestamps::read($value);

        self::assertSame($expected, $moment?->format(\DATE_ATOM));
    }
}
