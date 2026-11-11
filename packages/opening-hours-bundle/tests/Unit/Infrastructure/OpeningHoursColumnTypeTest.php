<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use JsonException;
use Phil\OpeningHours\Exception\UnreadableOpeningHours;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Doctrine\OpeningHoursColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpeningHoursColumnType::class)]
final class OpeningHoursColumnTypeTest extends TestCase
{
    public function testHoursAreJson(): void
    {
        $type = new OpeningHoursColumnType();
        $platform = new PostgreSQLPlatform();
        $json = '{"timezone":"America/Toronto","week":{"1":"09:00-17:00"},"specials":[{"from":"2026-12-25","through":"2026-12-25","hours":"","label":"Noël"}]}';

        self::assertSame('phil_opening_hours', OpeningHoursColumnType::NAME);
        self::assertSame('JSON', $type->getSQLDeclaration([], $platform));
        self::assertSame($json, $type->convertToDatabaseValue(OpeningHours::of([1 => HoursText::day('09:00-17:00')], HoursText::specials('2026-12-25 # Noël'), 'America/Toronto'), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame($json, $type->convertToDatabaseValue($type->convertToPHPValue($json, $platform), $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertSame('UTC', $type->convertToPHPValue('{"timezone":"UTC","x":[[[[1]]]]}', $platform)?->timezone);
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new OpeningHoursColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue([], $platform);
            self::fail('An array was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\OpeningHours\\OpeningHours', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('{"timezone":"UTC","x":[[[[[1]]]]]}', $platform);
            self::fail('JSON too deep was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(JsonException::class, $valueNotConvertible->getPrevious());
        }

        try {
            $type->convertToPHPValue('{}', $platform);
            self::fail('Hours without time zone were read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(UnreadableOpeningHours::class, $valueNotConvertible->getPrevious());
        }
    }
}
