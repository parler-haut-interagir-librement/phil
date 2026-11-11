<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use JsonException;
use Phil\Address\Address;
use Phil\Address\Exception\UnreadableAddress;
use Phil\AddressBundle\Infrastructure\Doctrine\AddressColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressColumnType::class)]
final class AddressColumnTypeTest extends TestCase
{
    public function testAnAddressIsJson(): void
    {
        $type = new AddressColumnType();
        $platform = new PostgreSQLPlatform();
        $json = '{"country":"CA","addressLine1":"1234 rue Saint-Denis","locality":"Montréal/Ville-Marie"}';

        self::assertSame('phil_address', AddressColumnType::NAME);
        self::assertSame('JSON', $type->getSQLDeclaration([], $platform));
        self::assertSame($json, $type->convertToDatabaseValue(Address::of('CA', ['addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal/Ville-Marie']), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame($json, $type->convertToDatabaseValue($type->convertToPHPValue($json, $platform), $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new AddressColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue('CA', $platform);
            self::fail('A string was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\Address\\Address', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('{"country":"CA","x":[[1]]}', $platform);
            self::fail('JSON too deep was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(JsonException::class, $valueNotConvertible->getPrevious());
        }

        try {
            $type->convertToPHPValue('{"country":"CA","x":[1]}', $platform);
            self::fail('A list was read as a field.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(UnreadableAddress::class, $valueNotConvertible->getPrevious());
        }
    }
}
