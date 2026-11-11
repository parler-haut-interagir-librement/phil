<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Unit\Domain;

use Phil\SlugBundle\Domain\Exception\InvalidSlugHistory;
use Phil\SlugBundle\Domain\SlugHistoryKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlugHistoryKeys::class)]
#[CoversClass(InvalidSlugHistory::class)]
final class SlugHistoryKeysTest extends TestCase
{
    public function testTheLimitsAreTheColumnLengths(): void
    {
        self::assertSame([100, 191], [SlugHistoryKeys::SCOPE_MAX_LENGTH, SlugHistoryKeys::SUBJECT_MAX_LENGTH]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function goodScopes(): iterable
    {
        yield 'one letter' => ['p'];
        yield 'a word' => ['page'];
        yield 'with a language' => ['page:fr'];
        yield 'every allowed character' => ['Tenant_42.shop/product-fr:CA'];
        yield 'the longest' => [str_repeat('a', 100)];
    }

    #[DataProvider('goodScopes')]
    public function testAGoodScopeIsKeptAsItIs(string $scope): void
    {
        self::assertSame($scope, SlugHistoryKeys::scope($scope));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badScopes(): iterable
    {
        yield 'empty' => ['', 'A slug history scope is 1 to 100 bytes long.'];
        yield 'one byte too long' => [str_repeat('a', 101), 'A slug history scope is 1 to 100 bytes long.'];
        yield 'a space' => ['my page', 'A slug history scope is made of ASCII letters, digits and "_ . : / -".'];
        yield 'an accent' => ['pagé', 'made of ASCII letters'];
        yield 'a trailing new line' => ["page\n", 'made of ASCII letters'];
        yield 'a percent sign' => ['page%', 'made of ASCII letters'];
    }

    #[DataProvider('badScopes')]
    public function testABadScopeIsRefused(string $scope, string $message): void
    {
        $this->expectException(InvalidSlugHistory::class);
        $this->expectExceptionMessageIsOrContains($message);

        SlugHistoryKeys::scope($scope);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function goodSubjects(): iterable
    {
        yield 'a number' => ['42'];
        yield 'a UUID' => ['0192f0c4-7b8e-7d2a-9a43-6f1e2b3c4d5e'];
        yield 'text with spaces and accents' => ['Équipe des gardiens'];
        yield 'the longest' => [str_repeat('a', 191)];
        yield 'the longest in multibyte characters' => [str_repeat('é', 95).'a'];
    }

    #[DataProvider('goodSubjects')]
    public function testAGoodSubjectIsKeptAsItIs(string $subject): void
    {
        self::assertSame($subject, SlugHistoryKeys::subject($subject));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badSubjects(): iterable
    {
        yield 'empty' => ['', 'A slug history subject is 1 to 191 bytes long.'];
        yield 'one byte too long' => [str_repeat('a', 192), 'A slug history subject is 1 to 191 bytes long.'];
        yield 'too long in bytes, not in characters' => [str_repeat('é', 96), '1 to 191 bytes long'];
        yield 'a control character' => ["a\tb", 'A slug history subject is UTF-8 text without control characters.'];
        yield 'a trailing new line' => ["42\n", 'without control characters'];
        yield 'invalid UTF-8' => ["\xC3\x28", 'without control characters'];
    }

    #[DataProvider('badSubjects')]
    public function testABadSubjectIsRefused(string $subject, string $message): void
    {
        $this->expectException(InvalidSlugHistory::class);
        $this->expectExceptionMessageIsOrContains($message);

        SlugHistoryKeys::subject($subject);
    }
}
