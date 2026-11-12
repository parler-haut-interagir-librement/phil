<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CounterKey::class)]
final class CounterKeyTest extends TestCase
{
    public function testAKeyCarriesItsParts(): void
    {
        $key = CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-10-31'), 'pageviews', 'route', 'home');

        self::assertSame('pageviews', $key->metric);
        self::assertSame('route', $key->dimension);
        self::assertSame('home', $key->value);
        self::assertTrue($key->equals(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-10-31'), 'pageviews', 'route', 'home')));
        self::assertFalse($key->equals(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-11-01'), 'pageviews', 'route', 'home')));
    }

    public function testTheReservedValuesAreValidValues(): void
    {
        $site = SiteId::fromString('s');
        $day = UtcDay::fromString('2026-10-31');

        self::assertSame(CounterKey::OTHER, CounterKey::of($site, $day, 'pageviews', 'route', CounterKey::OTHER)->value);
        self::assertSame(CounterKey::UNKNOWN, CounterKey::of($site, $day, 'pageviews', 'country', CounterKey::UNKNOWN)->value);
    }

    public function testAValueOfTheMaximumLengthIsKept(): void
    {
        $value = str_repeat('a', 128);

        self::assertSame($value, CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-10-31'), 'pageviews', 'route', $value)->value);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidParts(): iterable
    {
        yield 'metric with a space' => ['page views', 'route', 'home'];
        yield 'empty dimension' => ['pageviews', '', 'home'];
        yield 'empty value' => ['pageviews', 'route', ''];
        yield 'value too long' => ['pageviews', 'route', str_repeat('a', 129)];
        yield 'value with a control character' => ['pageviews', 'route', "ho\tme"];
    }

    #[DataProvider('invalidParts')]
    public function testAnInvalidPartIsRefused(string $metric, string $dimension, string $value): void
    {
        $this->expectException(InvalidValue::class);

        CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-10-31'), $metric, $dimension, $value);
    }
}
