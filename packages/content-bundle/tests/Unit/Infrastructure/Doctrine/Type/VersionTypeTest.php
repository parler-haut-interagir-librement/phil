<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionType::class)]
final class VersionTypeTest extends TestCase
{
    public function testTheColumnIsAnIntegerBoundAsOne(): void
    {
        self::assertSame('INT', new VersionType()->getSQLDeclaration([], new MySQLPlatform()));
        self::assertSame('INT', new VersionType()->getSQLDeclaration([], new PostgreSQLPlatform()));
        self::assertSame(ParameterType::INTEGER, new VersionType()->getBindingType());
    }

    public function testAVersionIsStoredAsItsNumber(): void
    {
        self::assertSame(7, new VersionType()->convertToDatabaseValue(Version::of(7), new MySQLPlatform()));
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function storedNumbers(): iterable
    {
        yield 'an integer' => [12];
        yield 'a numeric string' => ['12'];
    }

    #[DataProvider('storedNumbers')]
    public function testAStoredNumberIsReadBack(int|string $stored): void
    {
        self::assertSame(12, new VersionType()->convertToPHPValue($stored, new MySQLPlatform())?->number);
    }

    public function testNullStaysNullBothWays(): void
    {
        self::assertNull(new VersionType()->convertToDatabaseValue(null, new MySQLPlatform()));
        self::assertNull(new VersionType()->convertToPHPValue(null, new MySQLPlatform()));
    }

    public function testOnlyAVersionIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: null, '.Version::class.'.');

        new VersionType()->convertToDatabaseValue(3, new MySQLPlatform());
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function storedNumbersBelowOne(): iterable
    {
        yield 'zero' => [0];
        yield 'a negative string' => ['-2'];
    }

    #[DataProvider('storedNumbersBelowOne')]
    public function testAStoredNumberBelowOneIsRefused(int|string $stored): void
    {
        try {
            new VersionType()->convertToPHPValue($stored, new MySQLPlatform());
            self::fail('A version below one must not be read back.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString((string) $stored, $valueNotConvertible->getMessage());
            self::assertInstanceOf(InvalidLocaleDimension::class, $valueNotConvertible->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function storedValuesThatAreNotNumbers(): iterable
    {
        yield 'text' => ['three', '"string"'];
        yield 'a decimal string' => ['1.5', '"string"'];
        yield 'a number and a trailing newline' => ["12\n", '"string"'];
        yield 'a float' => [1.0, '"float"'];
    }

    #[DataProvider('storedValuesThatAreNotNumbers')]
    public function testAStoredValueThatIsNotAWholeNumberIsRefused(mixed $stored, string $type): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains($type);

        new VersionType()->convertToPHPValue($stored, new MySQLPlatform());
    }
}
