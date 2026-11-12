<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\TolerantReading;

use Phil\ApiContract\Exception\UnknownAuthorityValue;
use Phil\ApiContract\Tests\Unit\TolerantReading\Fixture\AssuranceLevel;
use Phil\ApiContract\Tests\Unit\TolerantReading\Fixture\Priority;
use Phil\ApiContract\TolerantReading\TolerantEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TolerantEnum::class)]
#[CoversClass(UnknownAuthorityValue::class)]
final class TolerantEnumTest extends TestCase
{
    public function testAKnownValueIsRead(): void
    {
        self::assertSame(AssuranceLevel::Strong, TolerantEnum::authoritative(AssuranceLevel::class, 'strong'));
        self::assertSame(Priority::High, TolerantEnum::withDefault(Priority::class, 2, Priority::Normal));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unknownValues(): iterable
    {
        yield 'a value added after this client' => ['phishing-resistant'];
        yield 'another case' => ['STRONG'];
        yield 'null' => [null];
        yield 'an array' => [['strong']];
    }

    #[DataProvider('unknownValues')]
    public function testAnUnknownValueOfAnOrdinaryFieldTakesTheDefault(mixed $value): void
    {
        self::assertSame(AssuranceLevel::Basic, TolerantEnum::withDefault(AssuranceLevel::class, $value, AssuranceLevel::Basic));
    }

    #[DataProvider('unknownValues')]
    public function testAnUnknownValueOfAnAuthorityFieldIsRefused(mixed $value): void
    {
        $this->expectException(UnknownAuthorityValue::class);

        TolerantEnum::authoritative(AssuranceLevel::class, $value);
    }

    /**
     * No coercion: a JSON string is not the integer it spells.
     */
    public function testAValueOfTheWrongJsonTypeIsUnknown(): void
    {
        self::assertSame(Priority::Normal, TolerantEnum::withDefault(Priority::class, '2', Priority::Normal));

        $this->expectException(UnknownAuthorityValue::class);
        TolerantEnum::authoritative(Priority::class, '2');
    }

    public function testTheRefusalNamesTheValueAndTheEnumeration(): void
    {
        $this->expectExceptionMessageIsOrContains('"phishing-resistant" is not a known value of '.AssuranceLevel::class);

        TolerantEnum::authoritative(AssuranceLevel::class, 'phishing-resistant');
    }

    /**
     * The value comes from a response: the message shows only its first bytes, escaped.
     */
    public function testTheRefusalEchoesAnUntrustedValueBounded(): void
    {
        try {
            TolerantEnum::authoritative(AssuranceLevel::class, str_repeat('x', 100)."\n");
            self::fail('An unknown authority value was accepted.');
        } catch (UnknownAuthorityValue $unknownAuthorityValue) {
            self::assertStringContainsString('"'.str_repeat('x', 64).'..."', $unknownAuthorityValue->getMessage());
            self::assertStringNotContainsString("\n", $unknownAuthorityValue->getMessage());
        }
    }

    public function testAValueOfTheShownLengthIsShownWhole(): void
    {
        try {
            TolerantEnum::authoritative(AssuranceLevel::class, 'a'.str_repeat('x', 63));
            self::fail('An unknown authority value was accepted.');
        } catch (UnknownAuthorityValue $unknownAuthorityValue) {
            self::assertStringStartsWith('"a'.str_repeat('x', 63).'" is not', $unknownAuthorityValue->getMessage());
        }
    }

    public function testALongerValueIsShownFromItsFirstByte(): void
    {
        $this->expectExceptionMessageIsOrContains('"a'.str_repeat('x', 63).'..." is not');

        TolerantEnum::authoritative(AssuranceLevel::class, 'a'.str_repeat('x', 99));
    }

    public function testTheRefusalNamesTheTypeOfANonScalar(): void
    {
        $this->expectExceptionMessageIsOrContains('array is not a known value');

        TolerantEnum::authoritative(AssuranceLevel::class, ['strong']);
    }

    public function testAnIntegerIsShownAsIs(): void
    {
        $this->expectExceptionMessageIsOrContains('7 is not a known value');

        TolerantEnum::authoritative(Priority::class, 7);
    }
}
