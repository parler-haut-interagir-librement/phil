<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Url\Url;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlColumnType::class)]
final class UrlColumnTypeTest extends TestCase
{
    public function testAnAddressIsAString(): void
    {
        $type = new UrlColumnType();
        $platform = new PostgreSQLPlatform();

        self::assertSame('phil_url', UrlColumnType::NAME);
        self::assertSame('VARCHAR(2048)', $type->getSQLDeclaration([], $platform));
        self::assertSame('VARCHAR(2048)', $type->getSQLDeclaration(['length' => 10], $platform));
        self::assertSame('https://example.com/', $type->convertToDatabaseValue(Url::fromString('HTTPS://Example.com'), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertTrue(Url::fromString('https://example.com/')->equals($type->convertToPHPValue('https://example.com/', $platform) ?? Url::fromString('https://other.example/')));
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertStringContainsString('2048', $type->getSQLDeclaration(['notnull' => false], new SQLitePlatform()));
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new UrlColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue('https://example.com/', $platform);
            self::fail('A string was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\Url\\Url', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('example.com', $platform);
            self::fail('A bad address was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('"example.com" is not an http or https address.', $valueNotConvertible->getMessage());
            self::assertNotNull($valueNotConvertible->getPrevious());
        }
    }
}
