<?php

declare(strict_types=1);

namespace Phil\NumberWords\Tests\Unit;

use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Exception\MissingRule;
use Phil\NumberWords\Exception\NumberOutOfRange;
use Phil\NumberWords\Gender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurrencyName::class)]
final class CurrencyNameTest extends TestCase
{
    public function testItKeepsItsNames(): void
    {
        $name = new CurrencyName('livre', 'livres', Gender::Feminine, 2, 'penny', 'pence', Gender::Neuter);

        self::assertSame('livre', $name->one);
        self::assertSame('livres', $name->other);
        self::assertSame(Gender::Feminine, $name->gender);
        self::assertSame(2, $name->fractionDigits);
        self::assertSame('penny', $name->subunitOne);
        self::assertSame('pence', $name->subunitOther);
        self::assertSame(Gender::Neuter, $name->subunitGender);
    }

    public function testACurrencyWithoutDecimalsNeedsNoSubunit(): void
    {
        $name = new CurrencyName('yen', 'yen', fractionDigits: 0);

        self::assertNull($name->subunitOne);
        self::assertSame(Gender::Masculine, $name->gender);
    }

    public function testFourDecimalsAreTheMost(): void
    {
        self::assertSame(4, new CurrencyName('a', 'b', fractionDigits: 4, subunitOne: 'c')->fractionDigits);

        $this->expectExceptionObject(NumberOutOfRange::fractionDigits(5));

        new CurrencyName('a', 'b', fractionDigits: 5, subunitOne: 'c');
    }

    public function testNegativeDecimalsAreRefused(): void
    {
        $this->expectExceptionObject(NumberOutOfRange::fractionDigits(-1));

        new CurrencyName('a', 'b', fractionDigits: -1);
    }

    public function testDecimalsNeedASubunit(): void
    {
        $this->expectExceptionObject(MissingRule::subunit('dollar'));

        new CurrencyName('dollar', 'dollars');
    }
}
