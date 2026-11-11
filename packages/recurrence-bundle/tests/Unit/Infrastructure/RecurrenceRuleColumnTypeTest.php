<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Recurrence\Exception\InvalidRule;
use Phil\Recurrence\RecurrenceRule;
use Phil\RecurrenceBundle\Infrastructure\Doctrine\RecurrenceRuleColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceRuleColumnType::class)]
final class RecurrenceRuleColumnTypeTest extends TestCase
{
    public function testARuleIsItsText(): void
    {
        $type = new RecurrenceRuleColumnType();
        $platform = new PostgreSQLPlatform();

        self::assertSame('phil_rrule', RecurrenceRuleColumnType::NAME);
        self::assertSame('VARCHAR(255)', $type->getSQLDeclaration([], $platform));
        self::assertSame('VARCHAR(255)', $type->getSQLDeclaration(['length' => 10], $platform));
        self::assertSame('FREQ=WEEKLY;UNTIL=20261231T235959Z;BYDAY=MO', $type->convertToDatabaseValue(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=MO;UNTIL=20261231T235959Z'), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame('FREQ=DAILY;COUNT=3', (string) $type->convertToPHPValue('FREQ=DAILY;COUNT=3', $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new RecurrenceRuleColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue('FREQ=DAILY', $platform);
            self::fail('A string was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\Recurrence\\RecurrenceRule', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('FREQ=HOURLY', $platform);
            self::fail('An hourly rule was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(InvalidRule::class, $valueNotConvertible->getPrevious());
        }
    }
}
