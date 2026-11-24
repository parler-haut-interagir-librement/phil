<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\UrlNormalizer;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlNormalizer::class)]
final class UrlNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function keys(): iterable
    {
        yield 'the fragment dropped' => ['https://example.com/a#part', 'https://example.com/a'];
        yield 'the host in lowercase' => ['https://EXAMPLE.com/A', 'https://example.com/A'];
        yield 'the default port dropped' => ['https://example.com:443/a', 'https://example.com/a'];
        yield 'another port kept' => ['https://example.com:8443/a?x=1#f', 'https://example.com:8443/a?x=1'];
        yield 'the query kept in its order' => ['https://example.com/?b=2&a=1', 'https://example.com/?b=2&a=1'];
    }

    #[DataProvider('keys')]
    public function testTheKeyOfAnAddress(string $url, string $key): void
    {
        self::assertSame($key, new UrlNormalizer()->key(Url::fromString($url)));
    }

    public function testTheQueryIsOnlySortedWhenAsked(): void
    {
        $url = Url::fromString('https://example.com/p?b=2&a=1&b=1&ab=0&a&0=z');

        self::assertFalse(new UrlNormalizer()->sortQuery);
        self::assertSame('https://example.com/p?b=2&a=1&b=1&ab=0&a&0=z', new UrlNormalizer()->key($url));
        self::assertSame('https://example.com/p?0=z&a=1&a&ab=0&b=2&b=1', new UrlNormalizer(sortQuery: true)->key($url));
        self::assertSame('https://example.com/p', new UrlNormalizer(sortQuery: true)->key(Url::fromString('https://example.com/p#x')));
    }

    public function testTheAddressRequestedHasNoFragment(): void
    {
        $normalizer = new UrlNormalizer(sortQuery: true);
        $plain = Url::fromString('https://example.com/p?b=1&a=2');

        self::assertSame($plain, $normalizer->withoutFragment($plain));
        self::assertSame('https://example.com:8080/p?b=1&a=2', $normalizer->withoutFragment(Url::fromString('https://example.com:8080/p?b=1&a=2#x'))->toString());
        self::assertSame('http://localhost/p', $normalizer->withoutFragment(Url::fromString('http://localhost/p#x', allowLocalhost: true))->toString());
    }
}
