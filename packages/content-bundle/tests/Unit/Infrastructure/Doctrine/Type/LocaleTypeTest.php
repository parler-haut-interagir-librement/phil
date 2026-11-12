<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(LocaleType::class)]
final class LocaleTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function platforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform()];
        yield 'PostgreSQL' => [new PostgreSQLPlatform()];
        yield 'SQLite' => [new SQLitePlatform()];
    }

    #[DataProvider('platforms')]
    public function testTheColumnIsAStringOfTwelveByDefault(AbstractPlatform $platform): void
    {
        self::assertSame('VARCHAR(12)', new LocaleType()->getSQLDeclaration([], $platform));
    }

    public function testTheMappedLengthWins(): void
    {
        self::assertSame('VARCHAR(20)', new LocaleType()->getSQLDeclaration(['length' => 20], new MySQLPlatform()));
    }

    #[DataProvider('platforms')]
    public function testALocaleRoundTripsThroughItsOneSpelling(AbstractPlatform $platform): void
    {
        $type = new LocaleType();

        self::assertSame('fr_CA', $type->convertToDatabaseValue(Locale::fromString('fr_CA'), $platform));
        self::assertTrue(Locale::fromString('fr_CA')->equals($type->convertToPHPValue('fr_CA', $platform) ?? self::fail('null')));
    }

    public function testNullStaysNullBothWays(): void
    {
        $type = new LocaleType();

        self::assertNull($type->convertToDatabaseValue(null, new MySQLPlatform()));
        self::assertNull($type->convertToPHPValue(null, new MySQLPlatform()));
    }

    /**
     * A string is not a locale: the column only takes what has passed `Locale::fromString()`.
     */
    public function testOnlyALocaleIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: null, '.Locale::class.'.');

        new LocaleType()->convertToDatabaseValue('fr_CA', new MySQLPlatform());
    }

    public function testAnObjectOfAnotherClassIsRefused(): void
    {
        $this->expectException(InvalidType::class);

        new LocaleType()->convertToDatabaseValue(new stdClass(), new MySQLPlatform());
    }

    public function testAStoredValueThatIsNotALocaleIsRefused(): void
    {
        try {
            new LocaleType()->convertToPHPValue('fr-CA', new MySQLPlatform());
            self::fail('A value that is not a locale must not be read back.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('fr-CA', $valueNotConvertible->getMessage());
            self::assertInstanceOf(InvalidLocaleDimension::class, $valueNotConvertible->getPrevious());
        }
    }

    public function testANonStringStoredValueIsRefused(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains('"int"');

        new LocaleType()->convertToPHPValue(42, new MySQLPlatform());
    }
}
