<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use JsonException;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarningsBundle\Infrastructure\Doctrine\ContentWarningsColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(ContentWarningsColumnType::class)]
final class ContentWarningsColumnTypeTest extends TestCase
{
    public function testWarningsAreJson(): void
    {
        $type = new ContentWarningsColumnType();
        $platform = new PostgreSQLPlatform();
        $warnings = ContentWarnings::of(new ContentWarning('gore', Severity::Severe, 'Zoé/Act II'), new ContentWarning('spiders'));
        $json = '[{"theme":"gore","severity":"severe","note":"Zoé/Act II"},{"theme":"spiders"}]';

        self::assertSame('phil_content_warnings', ContentWarningsColumnType::NAME);
        self::assertSame('JSON', $type->getSQLDeclaration([], $platform));
        self::assertSame($json, $type->convertToDatabaseValue($warnings, $platform));
        self::assertSame('[]', $type->convertToDatabaseValue(ContentWarnings::none(), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertEquals($warnings, $type->convertToPHPValue($json, $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testOtherValuesAreRefused(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: null, Phil\\ContentWarnings\\ContentWarnings');

        new ContentWarningsColumnType()->convertToDatabaseValue(['gore'], new PostgreSQLPlatform());
    }

    /**
     * @return iterable<string, array{mixed, class-string|null, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not a string' => [3, null, ''];
        yield 'not JSON' => ['[', JsonException::class, 'Syntax error'];
        yield 'not a list' => ['"gore"', UnreadableWarnings::class, 'the column holds no list'];
        yield 'nested too deeply' => ['[{"theme":{"a":1}}]', JsonException::class, 'Maximum stack depth exceeded'];
        yield 'not warnings' => ['[{"theme":"Gore"}]', UnreadableWarnings::class, '"Gore" is not a theme key'];
    }

    /**
     * @param class-string|null $previous
     */
    #[DataProvider('unreadable')]
    public function testStoredValuesThatAreNoWarningsAreRefused(mixed $value, ?string $previous, string $message): void
    {
        try {
            new ContentWarningsColumnType()->convertToPHPValue($value, new PostgreSQLPlatform());
            self::fail('The value is converted.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertSame($previous, $valueNotConvertible->getPrevious() instanceof Throwable ? $valueNotConvertible->getPrevious()::class : null);
            self::assertStringContainsString($message, $valueNotConvertible->getMessage());
        }
    }
}
