<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataCategory::class)]
final class DataCategoryTest extends TestCase
{
    public function testASnakeCaseTokenIsACategory(): void
    {
        self::assertSame('person_name', DataCategory::fromString('person_name')->value);
        self::assertSame(str_repeat('a', 64), DataCategory::fromString(str_repeat('a', 64))->value);
        self::assertTrue(DataCategory::fromString('ip')->equals(DataCategory::fromString('ip')));
        self::assertFalse(DataCategory::fromString('ip')->equals(DataCategory::fromString('email')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notACategory(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Email'];
        yield 'free text' => ['an e-mail address'];
        yield 'leading digit' => ['1email'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["email\n"];
    }

    #[DataProvider('notACategory')]
    public function testAnythingElseIsRefused(string $value): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        DataCategory::fromString($value);
    }
}
