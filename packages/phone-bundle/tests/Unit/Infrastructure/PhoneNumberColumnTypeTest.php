<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Phone\Exception\InvalidPhoneNumber;
use Phil\Phone\PhoneNumber;
use Phil\PhoneBundle\Infrastructure\Doctrine\PhoneNumberColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneNumberColumnType::class)]
final class PhoneNumberColumnTypeTest extends TestCase
{
    public function testANumberIsAStringInE164(): void
    {
        $type = new PhoneNumberColumnType();
        $platform = new PostgreSQLPlatform();

        self::assertSame('phil_phone', PhoneNumberColumnType::NAME);
        self::assertSame('VARCHAR(33)', $type->getSQLDeclaration([], $platform));
        self::assertSame('VARCHAR(33)', $type->getSQLDeclaration(['length' => 10], $platform));
        self::assertSame('+15145550123;ext=12', $type->convertToDatabaseValue(PhoneNumber::parse('+1 514 555 0123 x12'), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertTrue(PhoneNumber::parse('+15145550123;ext=12')->equals($type->convertToPHPValue('+15145550123;ext=12', $platform) ?? PhoneNumber::parse('+15145550000')));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    /**
     * The longest number parse() accepts — a country code of three digits, seventeen national digits, an extension
     * of seven — fits the column and reads back the same.
     */
    public function testTheLongestNumberFits(): void
    {
        $type = new PhoneNumberColumnType();
        $platform = new PostgreSQLPlatform();
        $longest = PhoneNumber::parse('+979 12345678901234567 ext. 1234567');
        $written = $type->convertToDatabaseValue($longest, $platform);

        self::assertSame('+97912345678901234567;ext=1234567', $written);
        self::assertSame(PhoneNumberColumnType::LENGTH, \strlen($written));
        self::assertTrue($longest->equals($type->convertToPHPValue($written, $platform) ?? PhoneNumber::parse('+15145550000')));
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new PhoneNumberColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue('+15145550123', $platform);
            self::fail('A string was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\Phone\\PhoneNumber', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('5145550123', $platform);
            self::fail('A number without its country code was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('is written without its country code', $valueNotConvertible->getMessage());
            self::assertInstanceOf(InvalidPhoneNumber::class, $valueNotConvertible->getPrevious());
        }
    }
}
