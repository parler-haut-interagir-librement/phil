<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\Deprecation;

use DateTimeImmutable;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Exception\InvalidContractValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeprecationSignal::class)]
final class DeprecationSignalTest extends TestCase
{
    private const string LINK = 'https://example.com/guide/deprecations#tenants';

    public function testTheServerWritesTheThreeHeaders(): void
    {
        $signal = DeprecationSignal::of(new DateTimeImmutable('@1688169599'), new DateTimeImmutable('2023-12-31 23:59:59 UTC'), self::LINK);

        self::assertSame([
            'Deprecation' => '@1688169599',
            'Sunset' => 'Sun, 31 Dec 2023 23:59:59 GMT',
            'Link' => '<'.self::LINK.'>; rel="deprecation"',
        ], $signal->toHeaders());
    }

    public function testAnAbsentPartWritesNoHeader(): void
    {
        self::assertSame(['Deprecation' => '@0'], DeprecationSignal::of(new DateTimeImmutable('@0'))->toHeaders());
        self::assertSame(['Sunset' => 'Thu, 01 Jan 1970 00:00:00 GMT'], DeprecationSignal::of(null, new DateTimeImmutable('@0'))->toHeaders());
    }

    public function testASignalCarriesAtLeastOneDate(): void
    {
        $this->expectException(InvalidContractValue::class);

        DeprecationSignal::of(null, null, self::LINK);
    }

    public function testTheSunsetCannotPrecedeTheDeprecation(): void
    {
        $this->expectException(InvalidContractValue::class);
        $this->expectExceptionMessageIsOrContains('The sunset date precedes the deprecation date.');

        DeprecationSignal::of(new DateTimeImmutable('@200'), new DateTimeImmutable('@100'));
    }

    public function testTheSunsetMayEqualTheDeprecation(): void
    {
        self::assertSame(0, DeprecationSignal::of(new DateTimeImmutable('@100'), new DateTimeImmutable('@100'))->noticeInDays());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLinks(): iterable
    {
        yield 'empty' => [''];
        yield 'closing bracket' => ['https://example.com/>; rel="next"'];
        yield 'space' => ['https://example.com/a b'];
        yield 'line break' => ["https://example.com/\r\nSet-Cookie: x"];
    }

    #[DataProvider('badLinks')]
    public function testALinkThatWouldBreakTheHeaderIsRefused(string $link): void
    {
        $this->expectException(InvalidContractValue::class);

        DeprecationSignal::of(new DateTimeImmutable('@0'), link: $link);
    }

    public function testAClientReadsWhatTheServerWrote(): void
    {
        $written = DeprecationSignal::of(new DateTimeImmutable('2030-01-01 12:00:00.75 UTC'), new DateTimeImmutable('2030-04-01 UTC'), self::LINK);
        $headers = $written->toHeaders();

        self::assertEquals($written, DeprecationSignal::fromHeaders($headers['Deprecation'], $headers['Sunset'], [$headers['Link']]));
    }

    public function testNoHeaderIsNoSignal(): void
    {
        self::assertNull(DeprecationSignal::fromHeaders(null, null, ['<'.self::LINK.'>; rel="deprecation"']));
    }

    /**
     * `Deprecation: true` is the form of the drafts before RFC 9745; neither it nor an HTTP-date is an sf-date.
     *
     * @return iterable<string, array{string}>
     */
    public static function malformedDeprecations(): iterable
    {
        yield 'draft boolean' => ['true'];
        yield 'HTTP-date' => ['Sun, 31 Dec 2023 23:59:59 GMT'];
        yield 'no at sign' => ['1688169599'];
        yield 'decimal' => ['@1688169599.5'];
        yield 'sixteen digits' => ['@1234567890123456'];
        yield 'text before the at sign' => ['x@1688169599'];
    }

    #[DataProvider('malformedDeprecations')]
    public function testAMalformedDeprecationIsIgnored(string $value): void
    {
        self::assertNull(DeprecationSignal::fromHeaders($value, null));

        $withSunset = DeprecationSignal::fromHeaders($value, 'Sun, 31 Dec 2023 23:59:59 GMT');
        self::assertNotNull($withSunset);
        self::assertNull($withSunset->deprecatedAt);
        self::assertNotNull($withSunset->sunsetAt);
    }

    public function testStructuredFieldParametersAreDropped(): void
    {
        self::assertSame(1688169599, DeprecationSignal::fromHeaders(' @1688169599;foo=bar ', null)?->deprecatedAt?->getTimestamp());
    }

    public function testAMalformedSunsetIsIgnored(): void
    {
        $signal = DeprecationSignal::fromHeaders('@0', '2030-01-01');

        self::assertNotNull($signal);
        self::assertNull($signal->sunsetAt);
    }

    /**
     * A sender that wrote its dates backwards still announced a retirement; the sunset is the date that
     * breaks the caller, so it is the one kept.
     */
    public function testContradictoryDatesKeepTheSunset(): void
    {
        $signal = DeprecationSignal::fromHeaders('@2000000000', 'Thu, 01 Jan 1970 00:00:00 GMT');

        self::assertNotNull($signal);
        self::assertNull($signal->deprecatedAt);
        self::assertSame(0, $signal->sunsetAt?->getTimestamp());
    }

    public function testEqualDatesAreNotContradictory(): void
    {
        $signal = DeprecationSignal::fromHeaders('@0', 'Thu, 01 Jan 1970 00:00:00 GMT');

        self::assertNotNull($signal);
        self::assertSame(0, $signal->deprecatedAt?->getTimestamp());
        self::assertSame(0, $signal->sunsetAt?->getTimestamp());
    }

    public function testTheDatesAreComparedWithNow(): void
    {
        $signal = DeprecationSignal::of(new DateTimeImmutable('@100'), new DateTimeImmutable('@200'));

        self::assertFalse($signal->isDeprecatedAt(new DateTimeImmutable('@99')));
        self::assertTrue($signal->isDeprecatedAt(new DateTimeImmutable('@100')));
        self::assertFalse($signal->isSunsetAt(new DateTimeImmutable('@199')));
        self::assertTrue($signal->isSunsetAt(new DateTimeImmutable('@200')));
    }

    public function testAnAbsentDateIsNeverReached(): void
    {
        self::assertFalse(DeprecationSignal::of(null, new DateTimeImmutable('@0'))->isDeprecatedAt(new DateTimeImmutable('@1000')));
        self::assertFalse(DeprecationSignal::of(new DateTimeImmutable('@0'))->isSunsetAt(new DateTimeImmutable('@1000')));
    }

    public function testTheNoticeIsCountedInWholeDays(): void
    {
        $deprecated = new DateTimeImmutable('2030-01-01 UTC');

        self::assertSame(90, DeprecationSignal::of($deprecated, $deprecated->modify('+90 days'))->noticeInDays());
        self::assertSame(89, DeprecationSignal::of($deprecated, $deprecated->modify('+90 days -1 second'))->noticeInDays());
        self::assertNull(DeprecationSignal::of($deprecated)->noticeInDays());
    }
}
