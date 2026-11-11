<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleIdType;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\OtherId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Uid\Exception\InvalidArgumentException as NotAUid;
use Symfony\Component\Uid\Uuid;

/**
 * The conversions, against platform objects and no connection. The round trip through real columns is
 * `Tests\Integration\Infrastructure\Doctrine`.
 *
 * PostgreSQL stands for the platforms with a native UUID type, MySQL 8 and SQLite for those without one.
 */
#[CoversClass(IdentifierType::class)]
final class IdentifierTypeTest extends TestCase
{
    private ExampleIdType $type;

    #[Override]
    protected function setUp(): void
    {
        $this->type = new ExampleIdType();
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function declarations(): iterable
    {
        yield 'PostgreSQL, native' => [new PostgreSQLPlatform(), 'UUID'];
        yield 'SQL Server, native' => [new SQLServerPlatform(), 'UNIQUEIDENTIFIER'];
        yield 'MySQL 8, binary' => [new MySQLPlatform(), 'BINARY(16)'];
        yield 'MariaDB, binary' => [new MariaDBPlatform(), 'BINARY(16)'];
        yield 'SQLite, binary' => [new SQLitePlatform(), 'BLOB'];
    }

    #[DataProvider('declarations')]
    public function testTheColumnIsTheNativeUuidOrSixteenBytes(AbstractPlatform $platform, string $declaration): void
    {
        self::assertSame($declaration, $this->type->getSQLDeclaration([], $platform));
    }

    /**
     * A length asked by the mapping does not reach a binary column: 16 bytes is what a UUID is.
     */
    public function testTheBinaryColumnIgnoresTheMappedLength(): void
    {
        self::assertSame('BINARY(16)', $this->type->getSQLDeclaration(['length' => 255, 'fixed' => false], new MySQLPlatform()));
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function nativePlatforms(): iterable
    {
        yield 'PostgreSQL' => [new PostgreSQLPlatform()];
        yield 'SQL Server' => [new SQLServerPlatform()];
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function binaryPlatforms(): iterable
    {
        yield 'MySQL 8' => [new MySQLPlatform()];
        yield 'MariaDB' => [new MariaDBPlatform()];
        yield 'SQLite' => [new SQLitePlatform()];
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function platforms(): iterable
    {
        yield from self::nativePlatforms();
        yield from self::binaryPlatforms();
    }

    #[DataProvider('nativePlatforms')]
    public function testANativeColumnStoresTheCanonicalString(AbstractPlatform $platform): void
    {
        $id = ExampleId::generate();

        self::assertSame($id->toString(), $this->type->convertToDatabaseValue($id, $platform));
    }

    #[DataProvider('binaryPlatforms')]
    public function testABinaryColumnStoresTheSixteenRawBytes(AbstractPlatform $platform): void
    {
        $id = ExampleId::generate();

        $stored = $this->type->convertToDatabaseValue($id, $platform);

        self::assertSame(str_replace('-', '', $id->toString()), bin2hex((string) $stored));
    }

    #[DataProvider('platforms')]
    public function testAStoredValueIsReadBackAsTheIdentifierClassOfTheColumn(AbstractPlatform $platform): void
    {
        $id = ExampleId::generate();

        $read = $this->type->convertToPHPValue($this->type->convertToDatabaseValue($id, $platform), $platform);

        self::assertInstanceOf(ExampleId::class, $read);
        self::assertTrue($id->equals($read));
    }

    #[DataProvider('platforms')]
    public function testNullStaysNullBothWays(AbstractPlatform $platform): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $platform));
        self::assertNull($this->type->convertToPHPValue(null, $platform));
    }

    /**
     * The mistake the per-class type exists for: a valid UUIDv7, of the wrong identifier class.
     */
    #[DataProvider('platforms')]
    public function testAnIdentifierOfAnotherClassIsRefused(AbstractPlatform $platform): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('null, '.ExampleId::class);

        $this->type->convertToDatabaseValue(OtherId::generate(), $platform);
    }

    /**
     * @return iterable<string, array{AbstractPlatform, mixed}>
     */
    public static function notAnIdentifier(): iterable
    {
        foreach (self::platforms() as $name => [$platform]) {
            yield $name.', a UUIDv7 string' => [$platform, Uuid::v7()->toRfc4122()];
            yield $name.', a UUIDv7 in bytes' => [$platform, Uuid::v7()->toBinary()];
            yield $name.', an object' => [$platform, new stdClass()];
        }
    }

    #[DataProvider('notAnIdentifier')]
    public function testOnlyAnIdentifierIsStored(AbstractPlatform $platform, mixed $value): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue($value, $platform);
    }

    public function testThePlatformIsAskedOnceAboutItsUuidTypeWhateverTheNumberOfValues(): void
    {
        $counting = new class extends SQLitePlatform {
            public int $asked = 0;

            #[Override]
            public function getGuidTypeDeclarationSQL(array $column): string
            {
                ++$this->asked;

                return parent::getGuidTypeDeclarationSQL($column);
            }
        };
        $other = clone $counting;

        foreach ([ExampleId::generate(), ExampleId::generate(), ExampleId::generate()] as $id) {
            $stored = $this->type->convertToDatabaseValue($id, $counting);
            $read = $this->type->convertToPHPValue($stored, $counting);
            self::assertNotNull($read);
            self::assertTrue($id->equals($read));
        }

        self::assertSame('BLOB', new ExampleIdType()->getSQLDeclaration([], $counting));
        self::assertSame(1, $counting->asked, 'Six conversions and a declaration, one question, shared by every type instance.');

        $this->type->convertToDatabaseValue(ExampleId::generate(), $other);
        self::assertSame(1, $other->asked, 'Each platform object is asked once of its own.');
    }

    #[DataProvider('platforms')]
    public function testANonStringDatabaseValueIsRefused(AbstractPlatform $platform): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains('"int"');

        $this->type->convertToPHPValue(42, $platform);
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function storedValuesThatAreNotAUuidV7(): iterable
    {
        foreach (self::nativePlatforms() as $name => [$platform]) {
            yield $name.', a UUIDv4' => [$platform, Uuid::v4()->toRfc4122()];
            yield $name.', not a UUID' => [$platform, 'not-a-uuid'];
            yield $name.', the 16 bytes of a UUIDv7' => [$platform, Uuid::v7()->toBinary()];
        }

        foreach (self::binaryPlatforms() as $name => [$platform]) {
            yield $name.', the 16 bytes of a UUIDv4' => [$platform, Uuid::v4()->toBinary()];
        }
    }

    /**
     * The domain refusal stays attached, as the previous exception, for whoever reads the log.
     */
    #[DataProvider('storedValuesThatAreNotAUuidV7')]
    public function testAStoredValueThatIsNotAUuidV7IsRefused(AbstractPlatform $platform, string $value): void
    {
        try {
            $this->type->convertToPHPValue($value, $platform);
            self::fail('A value that is not a UUIDv7 must not be read back as an identifier.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString(ExampleIdType::class, $valueNotConvertible->getMessage());
            self::assertInstanceOf(InvalidValue::class, $valueNotConvertible->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string, int}>
     */
    public static function storedValuesThatAreNotSixteenBytes(): iterable
    {
        foreach (self::binaryPlatforms() as $name => [$platform]) {
            yield $name.', the canonical string of a UUIDv7' => [$platform, Uuid::v7()->toRfc4122(), 36];
            yield $name.', 15 bytes' => [$platform, substr(Uuid::v7()->toBinary(), 1), 15];
            yield $name.', nothing' => [$platform, '', 0];
        }
    }

    /**
     * A binary column that holds anything but 16 bytes: the raw value is not printed, its length is.
     */
    #[DataProvider('storedValuesThatAreNotSixteenBytes')]
    public function testABinaryValueThatIsNotSixteenBytesIsRefused(AbstractPlatform $platform, string $value, int $length): void
    {
        try {
            $this->type->convertToPHPValue($value, $platform);
            self::fail('A binary value that is not 16 bytes must not be read back as an identifier.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString(\sprintf('"%d bytes"', $length), $valueNotConvertible->getMessage());
            self::assertStringContainsString(ExampleIdType::class, $valueNotConvertible->getMessage());
            self::assertInstanceOf(NotAUid::class, $valueNotConvertible->getPrevious());
        }
    }
}
