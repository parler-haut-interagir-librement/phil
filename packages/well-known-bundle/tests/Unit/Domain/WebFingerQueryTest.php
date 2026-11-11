<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerQuery;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebFingerQuery::class)]
#[CoversClass(WebFingerResource::class)]
#[CoversClass(InvalidValue::class)]
final class WebFingerQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, ?string, ?string}>
     */
    public static function resources(): iterable
    {
        yield 'an account' => ['acct:alice@example.com', 'acct:alice@example.com', 'acct', 'alice', 'example.com'];
        yield 'an account in capitals' => ['ACCT:Alice@Example.COM', 'acct:Alice@example.com', 'acct', 'Alice', 'example.com'];
        yield 'an account with a port' => ['acct:alice@example.com:8443', 'acct:alice@example.com:8443', 'acct', 'alice', 'example.com:8443'];
        yield 'an encoded user' => ['acct:juliet%40capulet.example@shoppingsite.example', 'acct:juliet%40capulet.example@shoppingsite.example', 'acct', 'juliet@capulet.example', 'shoppingsite.example'];
        yield 'a user of every allowed character' => ["acct:a-._~!$&'()*+,;=1@h", "acct:a-._~!$&'()*+,;=1@h", 'acct', "a-._~!$&'()*+,;=1", 'h'];
        yield 'a page' => ['https://Example.com/@alice?x=1#top', 'https://example.com/@alice?x=1#top', 'https', null, 'example.com'];
        yield 'a page in capitals' => ['HTTPS://EXAMPLE.COM', 'https://example.com', 'https', null, 'example.com'];
        yield 'a page with a query' => ['https://example.com?a', 'https://example.com?a', 'https', null, 'example.com'];
        yield 'a page with a fragment' => ['https://example.com#a', 'https://example.com#a', 'https', null, 'example.com'];
        yield 'a page with a port' => ['https://example.com:443/', 'https://example.com:443/', 'https', null, 'example.com:443'];
        yield 'another scheme' => ['MailTo:alice@example.com', 'mailto:alice@example.com', 'mailto', null, null];
        yield 'a long scheme' => [str_repeat('s', 32).':x', str_repeat('s', 32).':x', str_repeat('s', 32), null, null];
        yield 'a scheme of every character' => ['a1+.-:x', 'a1+.-:x', 'a1+.-', null, null];
    }

    #[DataProvider('resources')]
    public function testAResource(string $text, string $uri, string $scheme, ?string $user, ?string $host): void
    {
        $resource = WebFingerResource::parse($text);

        self::assertSame([$uri, $scheme, $user, $host], [$resource->uri, $resource->scheme, $resource->user, $resource->host]);
        self::assertSame('acct' === $scheme, $resource->isAccount());
        self::assertSame($uri, WebFingerResource::parse($resource->uri)->uri);
    }

    public function testTheLongestResource(): void
    {
        $text = 'https://example.com/'.str_repeat('a', 2028);

        self::assertSame($text, WebFingerResource::parse($text)->uri);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badResources(): iterable
    {
        yield 'empty' => ['', 'A WebFinger resource is an absolute URI without spaces, 2048 bytes at most.'];
        yield 'too long' => ['https://example.com/'.str_repeat('a', 2029), 'absolute URI'];
        yield 'no scheme' => ['alice@example.com', 'absolute URI'];
        yield 'a scheme starting with a digit' => ['1acct:alice@example.com', 'absolute URI'];
        yield 'a scheme too long' => [str_repeat('s', 33).':x', 'absolute URI'];
        yield 'a space' => ['acct:alice @example.com', 'absolute URI'];
        yield 'a new line' => ["acct:alice@example.com\n", 'absolute URI'];
        yield 'a null byte' => ["acct:alice\0@example.com", 'absolute URI'];
        yield 'a delete' => ["acct:alice\x7F@example.com", 'absolute URI'];
        yield 'no at' => ['acct:alice', 'An acct: resource is "acct:" followed by a user, "@" and a host.'];
        yield 'no user' => ['acct:@example.com', 'An acct: resource'];
        yield 'a leading at' => ['acct:@alice@example.com', 'An acct: resource'];
        yield 'no host' => ['acct:alice@', 'An acct: resource'];
        yield 'a bad host' => ['acct:alice@-example.com', 'An acct: resource'];
        yield 'a host with an underscore' => ['acct:alice@exa_mple.com', 'An acct: resource'];
        yield 'a host ending in a dot' => ['acct:alice@example.', 'An acct: resource'];
        yield 'a host with a slash' => ['acct:alice@example.com/x', 'An acct: resource'];
        yield 'a bad escape' => ['acct:a%zz@example.com', 'An acct: resource'];
        yield 'a user with a slash' => ['acct:a/b@example.com', 'An acct: resource'];
        yield 'a page without slashes' => ['https:example.com', 'An https: resource is "https://" followed by a host.'];
        yield 'a page without a host' => ['https:///path', 'An https: resource'];
        yield 'a page with credentials' => ['https://user@example.com/', 'An https: resource'];
        yield 'a page with a bad host' => ['https://exa_mple.com/', 'An https: resource'];
        yield 'a page with a long port' => ['https://example.com:123456/', 'An https: resource'];
        yield 'a page with a long label' => ['https://'.str_repeat('a', 64).'.com/', 'An https: resource'];
    }

    #[DataProvider('badResources')]
    public function testABadResourceIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        WebFingerResource::parse($text);
    }

    public function testALabelOf63(): void
    {
        self::assertSame(str_repeat('a', 63).'.com', WebFingerResource::parse('https://'.str_repeat('a', 63).'.com')->host);
    }

    public function testAQuery(): void
    {
        $query = WebFingerQuery::parse('rel=self&resource=acct%3Aalice%40example.com&x=1&&rel=http%3A%2F%2Fwebfinger.net%2Frel%2Favatar&rel=self&flag');

        self::assertSame('acct:alice@example.com', $query->resource->uri);
        self::assertSame(['self', 'http://webfinger.net/rel/avatar'], $query->rels);
        self::assertSame([], WebFingerQuery::parse('resource=acct:a@b')->rels);
        self::assertSame(['a', 'b'], WebFingerQuery::parse('rel=a&rel=a&rel=b&resource=acct:a@b')->rels);
        self::assertSame('https://example.com/a+b', WebFingerQuery::parse('re%73ource=https%3A%2F%2Fexample.com%2Fa%2Bb')->resource->uri);
        self::assertSame('acct:a=b@c', WebFingerQuery::parse('resource=acct:a=b@c')->resource->uri);
    }

    public function testTheBounds(): void
    {
        $query = WebFingerQuery::parse('resource=acct:a@b'.str_repeat('&rel=r', 32));
        self::assertSame(['r'], $query->rels);

        $long = 'resource=acct:a@b&x='.str_repeat('a', WebFingerQuery::MAX_LENGTH - 20);
        self::assertSame(WebFingerQuery::MAX_LENGTH, \strlen($long));
        self::assertSame('acct:a@b', WebFingerQuery::parse($long)->resource->uri);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badQueries(): iterable
    {
        yield 'empty' => ['', 'A WebFinger query names one resource.'];
        yield 'no resource' => ['rel=self', 'names one resource'];
        yield 'two resources' => ['resource=acct:a@b&resource=acct:a@b', 'names one resource'];
        yield 'a bad resource' => ['resource=alice', 'absolute URI'];
        yield 'too many rels' => ['resource=acct:a@b'.str_repeat('&rel=r', 33), 'A WebFinger query names 32 link relations at most.'];
        yield 'too long' => ['resource=acct:a@b&x='.str_repeat('a', WebFingerQuery::MAX_LENGTH - 19), 'A WebFinger query is 4096 bytes at most.'];
    }

    #[DataProvider('badQueries')]
    public function testABadQueryIsRefused(string $query, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        WebFingerQuery::parse($query);
    }
}
