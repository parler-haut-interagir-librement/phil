<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Money\Currency;
use Phil\Money\Exception\UnknownCurrency;
use Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurrencyType::class)]
final class CurrencyTypeTest extends TestCase
{
    public function testTheColumnIsThreeFixedCharacters(): void
    {
        self::assertSame('CHAR(3)', new CurrencyType()->getSQLDeclaration([], new PostgreSQLPlatform()));
        self::assertSame('CHAR(3)', new CurrencyType()->getSQLDeclaration(['length' => 10, 'fixed' => false], new PostgreSQLPlatform()));
    }

    public function testACurrencyIsStoredAsItsCode(): void
    {
        self::assertSame('CAD', new CurrencyType()->convertToDatabaseValue(Currency::of('CAD'), new PostgreSQLPlatform()));
        self::assertNull(new CurrencyType()->convertToDatabaseValue(null, new PostgreSQLPlatform()));
    }

    public function testAnythingElseIsNotStored(): void
    {
        $this->expectExceptionObject(InvalidType::new('CAD', CurrencyType::NAME, ['null', Currency::class]));

        new CurrencyType()->convertToDatabaseValue('CAD', new PostgreSQLPlatform());
    }

    public function testACodeIsReadBack(): void
    {
        self::assertEquals(Currency::of('CAD'), new CurrencyType()->convertToPHPValue('CAD', new PostgreSQLPlatform()));
        self::assertNull(new CurrencyType()->convertToPHPValue(null, new PostgreSQLPlatform()));
    }

    public function testAnUnknownCodeFailsTheHydration(): void
    {
        try {
            new CurrencyType()->convertToPHPValue('XYZ', new PostgreSQLPlatform());
            self::fail('An unknown code must fail.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('phil_currency', $valueNotConvertible->getMessage());
            self::assertStringContainsString('"XYZ" is not a known ISO 4217 currency code.', $valueNotConvertible->getMessage());
            self::assertInstanceOf(UnknownCurrency::class, $valueNotConvertible->getPrevious());
        }
    }

    public function testANonStringFailsTheHydration(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains('phil_currency');

        new CurrencyType()->convertToPHPValue(840, new PostgreSQLPlatform());
    }
}
