<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ApiContract\Http\HttpDate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpDate::class)]
final class HttpDateTest extends TestCase
{
    public function testTheWriterSendsAnImfFixdateInUtc(): void
    {
        $moment = new DateTimeImmutable('1994-11-06 03:49:37', new DateTimeZone('America/Toronto'));

        self::assertSame('Sun, 06 Nov 1994 08:49:37 GMT', HttpDate::format($moment));
    }

    /**
     * RFC 9110 §5.6.7 gives the same instant in its three forms.
     *
     * @return iterable<string, array{string}>
     */
    public static function theThreeForms(): iterable
    {
        yield 'IMF-fixdate' => ['Sun, 06 Nov 1994 08:49:37 GMT'];
        yield 'RFC 850' => ['Sunday, 06-Nov-94 08:49:37 GMT'];
        yield 'asctime' => ['Sun Nov  6 08:49:37 1994'];
    }

    #[DataProvider('theThreeForms')]
    public function testTheReaderAcceptsEachForm(string $value): void
    {
        self::assertSame(784111777, HttpDate::parse($value)?->getTimestamp());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'ISO 8601' => ['1994-11-06T08:49:37Z'];
        yield 'not GMT' => ['Sun, 06 Nov 1994 08:49:37 EST'];
        yield 'impossible day' => ['Thu, 31 Feb 2030 00:00:00 GMT'];
        yield 'wrong weekday' => ['Mon, 06 Nov 1994 08:49:37 GMT'];
        yield 'trailing text' => ['Sun, 06 Nov 1994 08:49:37 GMT x'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedValueIsNoDate(string $value): void
    {
        self::assertNull(HttpDate::parse($value));
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        self::assertSame(784111777, HttpDate::parse("  Sun, 06 Nov 1994 08:49:37 GMT \t")?->getTimestamp());
    }

    public function testTheParsedInstantHasNoFraction(): void
    {
        self::assertSame('000000', HttpDate::parse('Sun, 06 Nov 1994 08:49:37 GMT')?->format('u'));
    }

    public function testARoundTripKeepsTheInstant(): void
    {
        $moment = new DateTimeImmutable('@1893456000');

        self::assertEquals($moment, HttpDate::parse(HttpDate::format($moment)));
    }
}
