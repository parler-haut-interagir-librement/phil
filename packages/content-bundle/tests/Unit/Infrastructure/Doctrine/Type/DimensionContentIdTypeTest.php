<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Phil\ContentBundle\Domain\Content\DimensionContentId;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DimensionContentIdType::class)]
final class DimensionContentIdTypeTest extends TestCase
{
    public function testTheColumnIsANativeUuidOrSixteenBytes(): void
    {
        self::assertSame('UUID', new DimensionContentIdType()->getSQLDeclaration([], new PostgreSQLPlatform()));
        self::assertSame('BINARY(16)', new DimensionContentIdType()->getSQLDeclaration([], new MySQLPlatform()));
    }

    public function testItReadsBackARowIdentifier(): void
    {
        $type = new DimensionContentIdType();
        $platform = new MySQLPlatform();
        $id = DimensionContentId::generate();

        $read = $type->convertToPHPValue($type->convertToDatabaseValue($id, $platform), $platform);

        self::assertInstanceOf(DimensionContentId::class, $read);
        self::assertTrue($id->equals($read));
    }
}
