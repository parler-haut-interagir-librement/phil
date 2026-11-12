<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\CountryCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CountryCode::class)]
final class CountryCodeTest extends TestCase
{
    public function testACodeIsUpperCased(): void
    {
        $country = CountryCode::fromString('ca');

        self::assertSame('CA', $country->toString());
        self::assertTrue($country->isKnown());
        self::assertTrue($country->equals(CountryCode::fromString('CA')));
    }

    public function testAnUnknownCountryIsCountedAsUnknown(): void
    {
        self::assertFalse(CountryCode::unknown()->isKnown());
        self::assertSame(CounterKey::UNKNOWN, (string) CountryCode::unknown());
    }

    public function testWhatIsNotAnAlpha2CodeIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        CountryCode::fromString('CAN');
    }

    /**
     * `$` alone would let a final line feed through.
     */
    public function testACodeWithATrailingNewlineIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        CountryCode::fromString("CA\n");
    }
}
