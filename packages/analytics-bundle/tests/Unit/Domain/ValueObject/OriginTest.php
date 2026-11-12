<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Origin::class)]
final class OriginTest extends TestCase
{
    public function testAnOriginIsNormalized(): void
    {
        self::assertSame('https://example.com', Origin::fromString('HTTPS://Example.COM:443')->toString());
        self::assertSame('http://example.com:8080', Origin::fromString('http://example.com:8080')->toString());
    }

    public function testAnyHostABrowserCanBeOnIsAnOrigin(): void
    {
        self::assertSame('https://xn--bcher-kva.example', Origin::fromString('https://Bücher.example')->toString());
        self::assertTrue(Origin::fromString('https://bücher.example')->equals(Origin::fromString('https://xn--bcher-kva.example:443')));
        self::assertSame('http://localhost:8000', Origin::fromString('http://localhost:8000')->toString());
        self::assertSame('http://127.0.0.1', Origin::fromString('http://127.0.0.1:80')->toString());
        self::assertSame('https://[::1]:8443', Origin::fromString('https://[::1]:8443')->toString());
        self::assertSame(['https', 'example.com', 8443], [Origin::fromString('https://example.com:8443')->scheme, Origin::fromString('https://example.com:8443')->host, Origin::fromString('https://example.com:8443')->port]);
    }

    public function testTwoSpellingsOfTheSameOriginAreEqual(): void
    {
        self::assertTrue(Origin::fromString('https://example.com')->equals(Origin::fromString('https://EXAMPLE.com:443')));
        self::assertFalse(Origin::fromString('https://example.com')->equals(Origin::fromString('http://example.com')));
        self::assertFalse(Origin::fromString('https://example.com')->equals(Origin::fromString('https://example.com:8443')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAnOrigin(): iterable
    {
        yield 'with a path' => ['https://example.com/page'];
        yield 'with a query' => ['https://example.com?a=1'];
        yield 'with credentials' => ['https://user:pass@example.com'];
        yield 'with a user only' => ['https://user@example.com'];
        yield 'another scheme' => ['ftp://example.com'];
        yield 'no scheme' => ['example.com'];
        yield 'opaque' => ['null'];
        yield 'with a space' => ['https://exa mple.com'];
        yield 'with a trailing slash' => ['https://example.com/'];
        yield 'with a fragment' => ['https://example.com#a'];
        yield 'with an empty user' => ['https://@example.com'];
        yield 'with a user taken for the host' => ['https://example.com@evil.example'];
        yield 'with a backslash' => ['https://evil.example\\@example.com'];
        yield 'with no host' => ['https://'];
        yield 'with an empty host before the path' => ['https:///example.com'];
        yield 'with a port out of range' => ['https://example.com:65536'];
        yield 'with a control' => ["https://example.com\n"];
    }

    #[DataProvider('notAnOrigin')]
    public function testTheStrictFormRefusesWhatIsNotAnOrigin(string $value): void
    {
        $this->expectException(InvalidValue::class);

        Origin::fromString($value);
    }

    public function testTheOriginOfAUrlDropsEverythingButTheOrigin(): void
    {
        self::assertSame('https://example.com', Origin::ofUrl('https://example.com/a/b?code=secret#state')?->toString());
        self::assertNull(Origin::ofUrl('android-app://com.example'));
        self::assertSame('https://example.com', Origin::ofUrl('https://example.com')?->toString());
        self::assertSame('https://evil.example', Origin::ofUrl('https://evil.example\\@example.com/')?->toString(), 'A browser ends the host at the backslash.');
        self::assertSame('https://example.com', Origin::ofUrl('https://example.com/a/../b@c')?->toString());
        self::assertSame('https://example.com', Origin::ofUrl('https://example.com?q=1')?->toString());
        self::assertSame('https://example.com', Origin::ofUrl('https://example.com#top')?->toString());
        self::assertNull(Origin::ofUrl('https://user@example.com/page'), 'User information is not taken for the host.');
        self::assertNull(Origin::ofUrl('https://example.com@evil.example/page'));
        self::assertNull(Origin::ofUrl('https://example.com/a b'));
        self::assertNull(Origin::ofUrl('xhttps://example.com/'));
        self::assertNull(Origin::ofUrl('https://exa%zzmple.com/'));
    }

    public function testTheOriginHeaderWinsOverTheReferer(): void
    {
        $origin = Origin::ofRequest('https://a.example', 'https://b.example/page');

        self::assertSame('https://a.example', $origin?->toString());
    }

    public function testAnOpaqueOriginFallsBackToTheReferer(): void
    {
        self::assertSame('https://b.example', Origin::ofRequest('null', 'https://b.example/page')?->toString());
        self::assertSame('https://b.example', Origin::ofRequest(null, 'https://b.example/page')?->toString());
    }

    /**
     * A malformed `Origin` header is not rescued by the `Referer`: the request said where it came from, and that
     * is not an origin.
     */
    public function testAMalformedOriginHeaderNamesNoOrigin(): void
    {
        self::assertNull(Origin::ofRequest('https://a.example/path', 'https://b.example/page'));
    }

    public function testARequestWithNeitherHeaderNamesNoOrigin(): void
    {
        self::assertNull(Origin::ofRequest(null, null));
        self::assertNull(Origin::ofRequest('', ''));
    }
}
