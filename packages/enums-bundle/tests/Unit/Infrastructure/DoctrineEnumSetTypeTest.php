<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Doctrine\EnumSetStorage;
use Phil\EnumsBundle\Infrastructure\Doctrine\EnumSetType;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumSetType::class)]
final class DoctrineEnumSetTypeTest extends TestCase
{
    public function testItKnowsItsEnumAndStorage(): void
    {
        $type = new EnumSetType(Access::class, EnumSetStorage::Bitmask);

        self::assertSame(Access::class, $type->enum());
        self::assertSame(EnumSetStorage::Bitmask, $type->storage());
    }

    public function testTheColumnIsAnIntegerOrJson(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame('INT', new EnumSetType(Access::class, EnumSetStorage::Bitmask)->getSQLDeclaration([], $platform));
        self::assertSame('JSON', new EnumSetType(Access::class, EnumSetStorage::Json)->getSQLDeclaration([], $platform));
    }

    public function testABitmaskGoesBothWays(): void
    {
        $type = new EnumSetType(Access::class, EnumSetStorage::Bitmask);
        $platform = new PostgreSQLPlatform();

        self::assertSame(3, $type->convertToDatabaseValue(EnumSet::of(Access::class, Access::Read, Access::Write), $platform));
        self::assertSame(0, $type->convertToDatabaseValue(EnumSet::none(Access::class), $platform));
        $write = $type->convertToPHPValue(2, $platform);
        $both = $type->convertToPHPValue('3', $platform);

        self::assertNotNull($write);
        self::assertNotNull($both);
        self::assertSame([2], $write->values());
        self::assertSame([1, 2], $both->values());
        self::assertSame(Access::class, $both->enum());
    }

    public function testAJsonListGoesBothWays(): void
    {
        $type = new EnumSetType(OrderStatus::class, EnumSetStorage::Json);
        $platform = new PostgreSQLPlatform();

        self::assertSame('["pending","cancelled"]', $type->convertToDatabaseValue(EnumSet::of(OrderStatus::class, OrderStatus::CANCELLED, OrderStatus::PENDING), $platform));
        self::assertSame('[]', $type->convertToDatabaseValue(EnumSet::none(OrderStatus::class), $platform));
        self::assertSame([OrderStatus::PENDING, OrderStatus::CANCELLED], [...$type->convertToPHPValue('["cancelled","pending"]', $platform) ?? []]);
        self::assertSame([Shape::Circle], [...new EnumSetType(Shape::class, EnumSetStorage::Json)->convertToPHPValue('["Circle"]', $platform) ?? []]);
        self::assertSame([Access::Write], [...new EnumSetType(Access::class, EnumSetStorage::Json)->convertToPHPValue('[2]', $platform) ?? []]);
    }

    public function testNullStaysNull(): void
    {
        $type = new EnumSetType(Access::class, EnumSetStorage::Json);
        $platform = new PostgreSQLPlatform();

        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notASetOfTheEnum(): iterable
    {
        yield 'a case' => [Access::Read];
        yield 'a set of another enum' => [EnumSet::none(Shape::class)];
    }

    #[DataProvider('notASetOfTheEnum')]
    public function testOnlyASetOfItsEnumIsStored(mixed $value): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: null, '.EnumSet::class.'<'.Access::class.'>.');

        new EnumSetType(Access::class, EnumSetStorage::Bitmask)->convertToDatabaseValue($value, new PostgreSQLPlatform());
    }

    /**
     * @return iterable<string, array{EnumSetStorage, mixed, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'a bit no case stands for' => [EnumSetStorage::Bitmask, 8, 'has bits no case of enum'];
        yield 'not a number' => [EnumSetStorage::Bitmask, 'three', 'A bitmask is an integer.'];
        yield 'not json' => [EnumSetStorage::Json, '[1', 'Syntax error'];
        yield 'json that is not a string' => [EnumSetStorage::Json, ['1'], 'Syntax error'];
        yield 'not a list' => [EnumSetStorage::Json, '3', 'A set is stored as a JSON list.'];
        yield 'too deep' => [EnumSetStorage::Json, '[[1]]', 'Maximum stack depth exceeded'];
        yield 'an unknown value' => [EnumSetStorage::Json, '[1,8]', '8 is no value of enum '.Access::class.'.'];
        yield 'a value of the wrong kind' => [EnumSetStorage::Json, '[true]', 'true is no value of enum '.Access::class.'.'];
    }

    #[DataProvider('unreadable')]
    public function testAStoredValueThatNamesNoCaseFailsToConvert(EnumSetStorage $storage, mixed $value, string $message): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains($message);

        new EnumSetType(Access::class, $storage)->convertToPHPValue($value, new PostgreSQLPlatform());
    }
}
