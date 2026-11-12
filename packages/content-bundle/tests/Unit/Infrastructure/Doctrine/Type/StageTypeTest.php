<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StageType::class)]
final class StageTypeTest extends TestCase
{
    public function testTheColumnIsAStringOfEightByDefault(): void
    {
        self::assertSame('VARCHAR(8)', new StageType()->getSQLDeclaration([], new PostgreSQLPlatform()));
        self::assertSame('VARCHAR(16)', new StageType()->getSQLDeclaration(['length' => 16], new PostgreSQLPlatform()));
    }

    public function testEachStageRoundTripsByItsValue(): void
    {
        $type = new StageType();
        $platform = new MySQLPlatform();

        foreach (Stage::cases() as $stage) {
            self::assertSame($stage->value, $type->convertToDatabaseValue($stage, $platform));
            self::assertSame($stage, $type->convertToPHPValue($stage->value, $platform));
        }
    }

    public function testNullStaysNullBothWays(): void
    {
        self::assertNull(new StageType()->convertToDatabaseValue(null, new MySQLPlatform()));
        self::assertNull(new StageType()->convertToPHPValue(null, new MySQLPlatform()));
    }

    /**
     * The ORM unwraps a backed enum to its value before binding it: the type receives `'live'` for
     * `Stage::Live`, and must take it.
     */
    public function testTheBackingValueTheOrmPassesIsStored(): void
    {
        self::assertSame('live', new StageType()->convertToDatabaseValue('live', new MySQLPlatform()));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notAStage(): iterable
    {
        yield 'an unknown string' => ['archived'];
        yield 'the name of a case' => ['Live'];
        yield 'an integer' => [1];
    }

    #[DataProvider('notAStage')]
    public function testAnythingButAStageOrItsValueIsRefused(mixed $value): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('Expected one of the following types: null, %1$s, the value of a %1$s.', Stage::class));

        new StageType()->convertToDatabaseValue($value, new MySQLPlatform());
    }

    public function testAnUnknownStoredStageIsRefused(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains('archived');

        new StageType()->convertToPHPValue('archived', new MySQLPlatform());
    }

    public function testANonStringStoredValueIsRefused(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessageIsOrContains('"int"');

        new StageType()->convertToPHPValue(1, new MySQLPlatform());
    }
}
