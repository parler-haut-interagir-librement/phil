<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Redaction\UrlRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlRedactor::class)]
final class UrlRedactorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function queryStrings(): iterable
    {
        yield 'path' => ['/reset?token=abc&lang=fr', '/reset?[redacted]'];
        yield 'absolute URL' => ['https://example.com/a?code=1', 'https://example.com/a?[redacted]'];
        yield 'fragment kept' => ['/a?x=1#section', '/a?[redacted]#section'];
        yield 'inside a sentence' => ['GET /a?x=1 failed', 'GET /a?[redacted] failed'];
        yield 'quoted' => ['uri="/a?x=1"', 'uri="/a?[redacted]"'];
        yield 'two URLs' => ['/a?x=1 then /b?y=2', '/a?[redacted] then /b?[redacted]'];
        yield 'bare secret as name' => ['/a?abc123', '/a?[redacted]'];
        yield 'at the start of the text' => ['?token=abc', '?[redacted]'];
        yield 'after a space' => ['query: ?token=abc', 'query: ?[redacted]'];
        yield 'after a question mark' => ['what??token=abc', 'what?[redacted]'];
        yield 'question at the end' => ['Is it done?', 'Is it done?'];
        yield 'question then space' => ['Done? Yes.', 'Done? Yes.'];
    }

    #[DataProvider('queryStrings')]
    public function testEveryQueryStringIsRedactedWhole(string $text, string $expected): void
    {
        self::assertSame($expected, new UrlRedactor()->redact($text));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function userInformation(): iterable
    {
        yield 'a Valkey DSN' => ['redis://app:pw@valkey:6379', 'redis://[redacted]@valkey:6379'];
        yield 'a PostgreSQL DSN with a query string' => ['postgresql://app:secret@db:5432/app?charset=utf8', 'postgresql://[redacted]@db:5432/app?[redacted]'];
        yield 'a user name alone' => ['https://token@example.com/a', 'https://[redacted]@example.com/a'];
        yield 'a password holding an at sign' => ['smtp://user:p@ss@mail:25', 'smtp://[redacted]@mail:25'];
        yield 'an upper-case scheme' => ['REDIS://app:pw@valkey', 'REDIS://[redacted]@valkey'];
        yield 'in a message' => ['Connection to "redis://app:pw@valkey:6379" failed', 'Connection to "redis://[redacted]@valkey:6379" failed'];
        yield 'an at sign in the path' => ['https://example.com/users/@me', 'https://example.com/users/@me'];
        yield 'an at sign in the query string' => ['https://example.com?to=a@b', 'https://example.com?[redacted]'];
        yield 'an at sign in the fragment' => ['https://example.com#a@b', 'https://example.com#a@b'];
        yield 'an address without a scheme' => ['mail info@example.com', 'mail info@example.com'];
        yield 'an empty user information' => ['https://@example.com', 'https://[redacted]@example.com'];
    }

    #[DataProvider('userInformation')]
    public function testTheUserInformationOfAnAbsoluteUrlIsRedacted(string $text, string $expected): void
    {
        self::assertSame($expected, new UrlRedactor()->redact($text));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenPaths(): iterable
    {
        yield 'path' => ['/p/abc123', '/p/[redacted]'];
        yield 'deeper path' => ['/p/abc123/edit', '/p/[redacted]/edit'];
        yield 'absolute URL' => ['https://example.com/p/abc123', 'https://example.com/p/[redacted]'];
        yield 'with query string' => ['/p/abc123?lang=fr', '/p/[redacted]?[redacted]'];
        yield 'in a sentence' => ['GET /p/abc 200', 'GET /p/[redacted] 200'];
        yield 'nested under another path' => ['/shop/p/abc', '/shop/p/abc'];
        yield 'prefix alone' => ['/p/', '/p/'];
    }

    #[DataProvider('tokenPaths')]
    public function testTheSegmentAfterADeclaredPrefixIsRedacted(string $text, string $expected): void
    {
        self::assertSame($expected, new UrlRedactor(['/p/'])->redact($text));
    }

    /**
     * A prefix is matched literally: its `.` is a dot, not any character.
     */
    public function testAPrefixIsNotAPattern(): void
    {
        $redactor = new UrlRedactor(['/a.b/']);

        self::assertSame('/axb/xyz', $redactor->redact('/axb/xyz'));
        self::assertSame('/a.b/[redacted]', $redactor->redact('/a.b/xyz'));
    }

    public function testAPrefixOfSeveralSegmentsIsAccepted(): void
    {
        self::assertSame('/invite/accept/[redacted]', new UrlRedactor(['/invite/accept/'])->redact('/invite/accept/xyz'));
    }

    public function testEveryDeclaredPrefixIsRedactedInOnePass(): void
    {
        $redactor = new UrlRedactor(['/p/', '/invite/accept/', '/reset/']);

        self::assertSame(
            'GET /p/[redacted] then /invite/accept/[redacted]/x and https://example.com/reset/[redacted]?[redacted] but /invite/xyz',
            $redactor->redact('GET /p/abc then /invite/accept/def/x and https://example.com/reset/ghi?lang=fr but /invite/xyz'),
        );
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function nestedPrefixes(): iterable
    {
        yield 'the shorter declared first' => [['/p/', '/p/x/']];
        yield 'the longer declared first' => [['/p/x/', '/p/']];
    }

    /**
     * Where one declared prefix starts another, the token of each is redacted, whatever the order of declaration.
     *
     * @param list<string> $prefixes
     */
    #[DataProvider('nestedPrefixes')]
    public function testANestedPrefixRedactsTheTokenOfEach(array $prefixes): void
    {
        $redactor = new UrlRedactor($prefixes);

        self::assertSame('/p/[redacted]/[redacted]/edit', $redactor->redact('/p/x/abc/edit'));
        self::assertSame('/p/[redacted]/[redacted]', $redactor->redact('/P/X/abc'));
        self::assertSame('/p/[redacted]', $redactor->redact('/p/x'));
        self::assertSame('/p/[redacted]/', $redactor->redact('/p/y/'));
    }

    /**
     * Three prefixes nested in one another: every segment after one of them goes, and only those.
     */
    public function testPrefixesNestedThreeDeepRedactEachToken(): void
    {
        $redactor = new UrlRedactor(['/a/b/c/d/', '/a/', '/a/b/c/', '/q/b/']);

        self::assertSame('/a/[redacted]/c/[redacted]/[redacted]', $redactor->redact('/a/b/c/d/e'));
        self::assertSame('/a/[redacted]/c/[redacted]', $redactor->redact('/A/b/C/x'));
    }

    public function testAFailingTokenPathPatternReturnsThePlaceholder(): void
    {
        $limit = \ini_get('pcre.backtrack_limit');
        $redactor = new UrlRedactor(['/failing/']);
        ini_set('pcre.backtrack_limit', '1');

        try {
            self::assertSame('[redacted]', $redactor->redact('/failing/'.str_repeat('a', 10_000)));
        } finally {
            ini_set('pcre.backtrack_limit', false === $limit ? '1000000' : $limit);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAPrefix(): iterable
    {
        yield 'no leading slash' => ['p/'];
        yield 'no trailing slash' => ['/p'];
        yield 'a segment after the last slash' => ['/p/x'];
        yield 'root' => ['/'];
        yield 'query' => ['/p?/'];
        yield 'trailing newline' => ["/p/\n"];
    }

    #[DataProvider('notAPrefix')]
    public function testAMalformedPrefixIsRefused(string $prefix): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new UrlRedactor([$prefix]);
    }
}
