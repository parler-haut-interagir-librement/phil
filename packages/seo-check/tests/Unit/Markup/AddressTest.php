<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Markup;

use Phil\SeoCheck\Markup\Address;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    #[DataProvider('addresses')]
    public function testAnAddressIsResolvedAgainstThePage(string $value, ?string $expected): void
    {
        self::assertSame($expected, Address::resolve($value, Url::fromString('https://example.org/fr/news/opening?x=1'))?->toString());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function addresses(): iterable
    {
        yield 'absolute' => ['https://example.com/a', 'https://example.com/a'];
        yield 'relative to the path' => ['other', 'https://example.org/fr/news/other'];
        yield 'relative to the host' => ['/en/', 'https://example.org/en/'];
        yield 'relative to the scheme' => ['//cdn.example.net/a.css', 'https://cdn.example.net/a.css'];
        yield 'a fragment' => ['#top', 'https://example.org/fr/news/opening?x=1#top'];
        yield 'empty' => ['', 'https://example.org/fr/news/opening?x=1'];
        yield 'spaces around' => ["\n /a \t", 'https://example.org/a'];
        yield 'another scheme' => ['mailto:shop@example.org', null];
        yield 'credentials' => ['https://user:secret@example.org/', null];
        yield 'an IP address' => ['http://192.0.2.1/', null];
        yield 'malformed' => ['https://exa mple.org/', null];
        yield 'too long' => ['/'.str_repeat('a', Address::MAX_LENGTH), null];
    }

    public function testTheFragmentIsDroppedOnDemand(): void
    {
        self::assertSame('https://example.org/a', Address::resolve('/a#b', Url::fromString('https://example.org/'), keepFragment: false)?->toString());
        self::assertSame('https://example.org/a#b', Address::resolve('/a#b', Url::fromString('https://example.org/'))?->toString());
    }

    public function testLocalhostIsAnAddressOnAPageServedThere(): void
    {
        self::assertSame('http://localhost/a', Address::resolve('/a', Url::fromString('http://localhost/', allowLocalhost: true))?->toString());
        self::assertSame('https://shop.localhost/a', Address::resolve('/a', Url::fromString('https://shop.localhost/', allowLocalhost: true))?->toString());
        self::assertNull(Address::resolve('http://localhost/a', Url::fromString('https://example.org/')));
        self::assertNull(Address::resolve('http://localhost/a', Url::fromString('https://notlocalhost.org/')));
    }

    public function testAnAbsoluteAddressHasItsScheme(): void
    {
        self::assertTrue(Address::isAbsolute(' https://example.org/a '));
        self::assertTrue(Address::isAbsolute('mailto:shop@example.org'));
        self::assertFalse(Address::isAbsolute('/a'));
        self::assertFalse(Address::isAbsolute('//example.org/a'));
        self::assertTrue(Address::isAbsolute('https://example.org/'.str_repeat('a', Address::MAX_LENGTH - 20)));
        self::assertFalse(Address::isAbsolute('https://example.org/'.str_repeat('a', Address::MAX_LENGTH - 19)));
    }

    public function testHtmlWhitespaceIsTrimmed(): void
    {
        self::assertSame("a\u{A0}", Address::trim(" \t\n\r\fa\u{A0}"));
    }

    public function testAnAddressIsReadUpToItsLongestLength(): void
    {
        // Dot segments make the address resolved shorter than the value: the bound is on the value.
        $base = Url::fromString('https://example.org/');
        $longest = 'https://example.org/'.str_repeat('./', 2037).'ab';

        self::assertSame(Address::MAX_LENGTH, \strlen($longest));
        self::assertSame('https://example.org/ab', Address::resolve($longest, $base)?->toString());
        self::assertNull(Address::resolve($longest.'c', $base));
    }
}
