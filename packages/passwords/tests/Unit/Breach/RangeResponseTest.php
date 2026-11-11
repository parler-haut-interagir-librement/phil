<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Breach;

use Phil\Passwords\Breach\RangeResponse;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RangeResponse::class)]
final class RangeResponseTest extends TestCase
{
    private const string SUFFIX = '1E4C9B93F3F0682250B6CF8331B7EE68FD8';

    public function testTheOccurrencesOfTheSuffix(): void
    {
        $body = "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n".self::SUFFIX.":10434004\r\n00D4F6E8FA6EECAD2A3AA415EEC418D38EC:0";

        self::assertSame(10434004, RangeResponse::occurrences($body, self::SUFFIX));
        self::assertSame(10434004, RangeResponse::occurrences($body, strtolower(self::SUFFIX)));
        self::assertSame(10434004, RangeResponse::occurrences(strtolower($body), self::SUFFIX));
        self::assertSame(0, RangeResponse::occurrences($body, '00D4F6E8FA6EECAD2A3AA415EEC418D38EC'));
        self::assertSame(1, RangeResponse::occurrences($body, '0018A45C4D1DEF81644B54AB7F969B88D65'));
        self::assertSame(0, RangeResponse::occurrences($body, 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'));
    }

    public function testEmptyLinesAndLineEndings(): void
    {
        self::assertSame(0, RangeResponse::occurrences('', self::SUFFIX));
        self::assertSame(3, RangeResponse::occurrences("\n".self::SUFFIX.":3\n\n", self::SUFFIX));
    }

    public function testTheLastLineOfTheSuffixWins(): void
    {
        self::assertSame(5, RangeResponse::occurrences(self::SUFFIX.":2\n".self::SUFFIX.':5', self::SUFFIX));
    }

    public function testHugeCountsAreCapped(): void
    {
        self::assertSame(\PHP_INT_MAX, RangeResponse::occurrences(self::SUFFIX.':9999999999999999999', self::SUFFIX));
        self::assertSame(999999999999999999, RangeResponse::occurrences(self::SUFFIX.':999999999999999999', self::SUFFIX));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'no colon' => [self::SUFFIX.'3'];
        yield 'no count' => [self::SUFFIX.':'];
        yield 'a negative count' => [self::SUFFIX.':-3'];
        yield 'a short suffix' => ['1E4C9B93F3F0682250B6CF8331B7EE68FD:3'];
        yield 'a long suffix' => [self::SUFFIX.'0:3'];
        yield 'not hexadecimal' => ['1E4C9B93F3F0682250B6CF8331B7EE68FDZ:3'];
        yield 'html' => ['<html>error</html>'];
        yield 'a valid line then a broken one' => [self::SUFFIX.":3\nbroken"];
    }

    #[DataProvider('malformed')]
    public function testAMalformedBody(string $body): void
    {
        $this->expectExceptionObject(new BreachCheckUnavailable('The breach source answered with a malformed range.'));

        RangeResponse::occurrences($body, self::SUFFIX);
    }
}
