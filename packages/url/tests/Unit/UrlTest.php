<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Url::class)]
#[CoversClass(InvalidUrl::class)]
final class UrlTest extends TestCase
{
    public function testAnAddressIsReadInParts(): void
    {
        $url = Url::fromString('https://www.example.com:8443/shop/tea?colour=green#top');

        self::assertSame(['https', 'www.example.com', 8443, '/shop/tea', 'colour=green', 'top'], [$url->scheme, $url->host, $url->port, $url->path, $url->query, $url->fragment]);
        self::assertSame('https://www.example.com:8443/shop/tea?colour=green#top', $url->toString());
        self::assertSame('https://www.example.com:8443/shop/tea?colour=green#top', (string) $url);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalised(): iterable
    {
        yield 'case' => ['HTTPS://Example.COM/Shop', 'https://example.com/Shop'];
        yield 'no path' => ['https://example.com', 'https://example.com/'];
        yield 'the https port' => ['https://example.com:443/a', 'https://example.com/a'];
        yield 'the http port' => ['http://example.com:80/a', 'http://example.com/a'];
        yield 'another port' => ['http://example.com:443/a', 'http://example.com:443/a'];
        yield 'an international host' => ['https://bücher.example/', 'https://xn--bcher-kva.example/'];
        yield 'a trailing dot' => ['https://example.com./', 'https://example.com/'];
        yield 'spaces around' => ['  https://example.com/a  ', 'https://example.com/a'];
        yield 'an empty query' => ['https://example.com/?', 'https://example.com/'];
        yield 'an empty fragment' => ['https://example.com/a#', 'https://example.com/a'];
        yield 'a label of digits inside the host' => ['https://www.1.example/', 'https://www.1.example/'];
        yield 'a dot segment' => ['https://example.com/a/./b', 'https://example.com/a/b'];
        yield 'a dot segment last' => ['https://example.com/a/.', 'https://example.com/a/'];
        yield 'a double dot segment' => ['https://example.com/a/../b', 'https://example.com/b'];
        yield 'a double dot segment last' => ['https://example.com/a/b/..', 'https://example.com/a/'];
        yield 'double dots above the root' => ['https://example.com/../../b', 'https://example.com/b'];
        yield 'an encoded dot segment' => ['https://example.com/a/%2e/b', 'https://example.com/a/b'];
        yield 'a double dot encoded in lowercase' => ['https://example.com/a/%2e%2e/b', 'https://example.com/b'];
        yield 'a double dot encoded in uppercase' => ['https://example.com/a/%2E%2E/b', 'https://example.com/b'];
        yield 'a double dot, its second dot encoded' => ['https://example.com/a/.%2e/b', 'https://example.com/b'];
        yield 'a double dot, its first dot encoded' => ['https://example.com/a/%2e./b', 'https://example.com/b'];
        yield 'backslashes' => ['https://example.com\\a\\b', 'https://example.com/a/b'];
        yield 'backslashes around double dots' => ['https://example.com/a\\..\\b', 'https://example.com/b'];
        yield 'a backslash, then double dots' => ['https://example.com/a/\\../b', 'https://example.com/a/b'];
        yield 'an encoded slash' => ['https://example.com/a%2Fb', 'https://example.com/a%2Fb'];
        yield 'an encoded slash after double dots' => ['https://example.com/docs/..%2Fadmin', 'https://example.com/docs/..%2Fadmin'];
        yield 'a tag in the path' => ['https://example.com/<script>', 'https://example.com/%3Cscript%3E'];
        yield 'quotes in the path' => ['https://example.com/"a"\'b\'', "https://example.com/%22a%22'b'"];
        yield 'braces and backticks in the path' => ['https://example.com/{a}`b`', 'https://example.com/%7Ba%7D%60b%60'];
        yield 'a tag and quotes in the query' => ['https://example.com/?q=<b>"\'', 'https://example.com/?q=%3Cb%3E%22%27'];
        yield 'a tag and quotes in the fragment' => ['https://example.com/#<b>"\'`', "https://example.com/#%3Cb%3E%22'%60"];
        yield 'letters in the path, query and fragment' => ['https://example.com/thé?thé=vert#thé', 'https://example.com/th%C3%A9?th%C3%A9=vert#th%C3%A9'];
        yield 'an escape kept as written' => ['https://example.com/%7e%41', 'https://example.com/%7e%41'];
        yield 'a dot segment in the query kept' => ['https://example.com/a?next=/b/../c', 'https://example.com/a?next=/b/../c'];
    }

    #[DataProvider('normalised')]
    public function testAnAddressIsNormalised(string $text, string $written): void
    {
        self::assertSame($written, Url::fromString($text)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'no scheme' => ['example.com', '"example.com" is not an http or https address.'];
        yield 'another scheme' => ['ftp://example.com/', '"ftp://example.com/" is not an http or https address.'];
        yield 'a scheme with a prefix' => ['xhttps://example.com/', '"xhttps://example.com/" is not an http or https address.'];
        yield 'a query and no host' => ['https://?q=1', '"https://?q=1" is not an http or https address.'];
        yield 'a scheme inside' => ['see https://example.com/', '"see https://example.com/" is not an http or https address.'];
        yield 'a space' => ['https://example.com/a b', '"https://example.com/a b" is not an http or https address.'];
        yield 'no host' => ['https:///path', '"https:///path" is not an http or https address.'];
        yield 'a user' => ['https://ada@example.com/', 'A web address carries no user name nor password.'];
        yield 'a password' => ['https://:secret@example.com/', 'A web address carries no user name nor password.'];
        yield 'a bare name' => ['https://localhost/', '"localhost" is not a host name.'];
        yield 'an IPv4 address' => ['http://192.168.0.1/', '"192.168.0.1" is not a host name.'];
        yield 'the loopback address' => ['http://127.0.0.1:8000/', '"127.0.0.1" is not a host name.'];
        yield 'a short IPv4 address' => ['http://127.1/', '"127.0.0.1" is not a host name.'];
        yield 'an IPv6 address' => ['http://[::1]/', '"[::1]" is not a host name.'];
        yield 'a top-level domain of digits' => ['https://shop.123/', '"https://shop.123/" is not an http or https address.'];
        yield 'port 0' => ['https://example.com:0/', '0 is not a port a web address can name.'];
        yield 'a port too high' => ['https://example.com:65536/', '"https://example.com:65536/" is not an http or https address.'];
        yield 'an underscore' => ['https://my_shop.example/', '"my_shop.example" is not a host name.'];
        yield 'a dash first' => ['https://-shop.example/', '"-shop.example" is not a host name.'];
        yield 'a long label' => ['https://'.str_repeat('a', 64).'.example/', \sprintf('"%s.example" is not a host name.', str_repeat('a', 64))];
        yield 'a long label in the middle' => ['https://www.'.str_repeat('b', 64).'.example/', \sprintf('"www.%s.example" is not a host name.', str_repeat('b', 64))];
        yield 'a dot for host' => ['https://.', '"." is not a host name.'];
        yield 'two dots for host' => ['https://..', '".." is not a host name.'];
        yield 'three dots for host' => ['https://...', '"..." is not a host name.'];
        yield 'a dot for host, then a path' => ['https://./path', '"." is not a host name.'];
        yield 'a dot for host, then a port' => ['https://.:8080/', '"." is not a host name.'];
        yield 'dots for host, then a query' => ['http://...?q=1', '"..." is not a host name.'];
        yield 'a dot for host, then a fragment' => ['https://.#top', '"." is not a host name.'];
        yield 'an empty label inside' => ['https://shop..example/', '"shop..example" is not a host name.'];
        yield 'an empty label first' => ['https://.example.com/', '".example.com" is not a host name.'];
        yield 'two trailing dots' => ['https://example.com../', '"example.com.." is not a host name.'];
        yield 'a user, the scheme in capitals' => ['HTTPS://ada@example.com/', 'A web address carries no user name nor password.'];
        yield 'an empty user' => ['https://@example.com/', 'A web address carries no user name nor password.'];
        yield 'a user before a backslash' => ['https://ada@example.com\\a', 'A web address carries no user name nor password.'];
        yield 'a backslash for host' => ['https://\\example.com/', '"https://\\example.com/" is not an http or https address.'];
        yield 'a short IPv4 address in hexadecimal' => ['http://0x7f.1/', '"127.0.0.1" is not a host name.'];
        yield 'an encoded slash in the host' => ['https://ex%2Fample.com/', '"https://ex%2Fample.com/" is not an http or https address.'];
        yield 'a null byte in the host' => ["https://ex\0ample.com/", 'is not an http or https address.'];
    }

    #[DataProvider('refused')]
    public function testOtherTextsAreRefused(string $text, string $message): void
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains($message);

        Url::fromString($text);
    }

    public function testAnAddressHas2048CharactersAtMost(): void
    {
        $prefix = 'https://example.com/';
        self::assertSame(2048, \strlen(Url::fromString($prefix.str_repeat('a', 2048 - \strlen($prefix)))->toString()));

        try {
            Url::fromString($prefix.str_repeat('a', 2049 - \strlen($prefix)));
            self::fail('A long address was accepted.');
        } catch (InvalidUrl $invalidUrl) {
            self::assertSame('A web address is 2048 characters at most; this one is 2049.', $invalidUrl->getMessage());
        }

        self::assertSame(\sprintf('"%s" is not an http or https address.', str_repeat('é', 100)), InvalidUrl::notAUrl(str_repeat('é', 150))->getMessage());
        self::assertSame(\sprintf('"%s" is not a host name.', str_repeat('é', 100)), InvalidUrl::host(str_repeat('é', 150))->getMessage());
        self::assertInstanceOf(UrlException::class, InvalidUrl::credentials());
    }

    public function testATextLongerThanTheLimitIsRefusedEvenWhenItsNormalisedFormIsShort(): void
    {
        $text = 'https://example.com/'.str_repeat('./', 1100);

        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A web address is 2048 characters at most; this one is %d.', \strlen($text)));

        Url::fromString($text);
    }

    public function testAnAddressLongerOnceEncodedIsRefused(): void
    {
        $prefix = 'https://example.com/';
        $text = $prefix.str_repeat('<', 1000);
        self::assertLessThanOrEqual(2048, \strlen($text));

        try {
            Url::fromString($text);
            self::fail('An address longer once encoded was accepted.');
        } catch (InvalidUrl $invalidUrl) {
            self::assertSame(\sprintf('A web address is 2048 characters at most; this one is %d.', \strlen($prefix) + 3000), $invalidUrl->getMessage());
        }

        self::assertSame(2048, \strlen(Url::fromString($prefix.str_repeat('a', 2025).'<')->toString()));
    }

    public function testAPathAllowedByPrefixCannotClimbOutOfIt(): void
    {
        foreach (['https://example.com/docs/../admin', 'https://example.com/docs/%2e%2e/admin', 'https://example.com/docs\\..\\admin', 'https://example.com/docs/.%2E/admin'] as $text) {
            $url = Url::fromString($text);

            self::assertSame('/admin', $url->path, $text);
            self::assertSame(['admin'], $url->segments(), $text);
            self::assertFalse(str_starts_with($url->path, '/docs/'), $text);
            self::assertTrue($url->equals(Url::fromString('https://example.com/admin')), $text);
        }

        self::assertSame(['docs', '..%2Fadmin'], Url::fromString('https://example.com/docs/..%2Fadmin')->segments());
    }

    public function testAnInternationalHostComesBackTheSame(): void
    {
        $url = Url::fromString('https://Bücher.Example/thé');

        self::assertSame('xn--bcher-kva.example', $url->host);
        self::assertSame('https://xn--bcher-kva.example/th%C3%A9', $url->toString());
        self::assertTrue($url->equals(Url::fromString($url->toString())));
        self::assertSame('bücher.example/thé', $url->display());
    }

    public function testALongHostIsRefused(): void
    {
        $host = implode('.', array_fill(0, 4, str_repeat('a', 63))).'.ex';
        self::assertGreaterThan(253, \strlen($host));

        $this->expectException(InvalidUrl::class);

        Url::fromString('https://'.$host.'/');
    }

    public function testAHostOf253CharactersIsAccepted(): void
    {
        $host = implode('.', array_fill(0, 3, str_repeat('a', 63))).'.'.str_repeat('b', 61);
        self::assertSame(253, \strlen($host));

        self::assertSame($host, Url::fromString('https://'.$host.'/')->host);
    }

    public function testEqualAddressesAreEqual(): void
    {
        self::assertTrue(Url::fromString('HTTPS://Example.com:443')->equals(Url::fromString('https://example.com/')));
        self::assertFalse(Url::fromString('https://example.com/a')->equals(Url::fromString('https://example.com/b')));
    }

    public function testAHostIsOnADomainOrUnderIt(): void
    {
        $url = Url::fromString('https://m.facebook.com/shop');

        self::assertTrue($url->isOn('facebook.com'));
        self::assertTrue($url->isOn('example.com', 'FACEBOOK.COM'));
        self::assertTrue($url->isOn('m.facebook.com'));
        self::assertFalse($url->isOn('book.com'));
        self::assertFalse($url->isOn());
        self::assertFalse(Url::fromString('https://notfacebook.com/')->isOn('facebook.com'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function displays(): iterable
    {
        yield 'a home page' => ['https://www.example.com/', 'example.com'];
        yield 'a page' => ['https://example.com/shop/', 'example.com/shop'];
        yield 'a query' => ['http://www.example.com/shop?tea=1#top', 'example.com/shop?tea=1'];
        yield 'a port' => ['https://example.com:8443/', 'example.com:8443'];
        yield 'www inside' => ['https://shop.www.example/', 'shop.www.example'];
        yield 'an international host' => ['https://www.bücher.example/bücher', 'bücher.example/bücher'];
        yield 'an international host in ASCII' => ['https://xn--bcher-kva.example/', 'bücher.example'];
        yield 'digits in a label' => ['https://1password.com/', '1password.com'];
        yield 'a host passing for another' => ['https://xn--pple-43d.com/', 'xn--pple-43d.com'];
        yield 'a host in one script' => ['https://xn--80adxhks.xn--p1ai/', 'москва.рф'];
        yield 'letters in the path and query' => ['https://example.com/thé?q=café', 'example.com/thé?q=café'];
        yield 'letters whose bytes pass for controls' => ['https://example.com/voilà/5€', 'example.com/voilà/5€'];
        yield 'escapes of ASCII kept' => ['https://example.com/a%20b%3C?q=%2F', 'example.com/a%20b%3C?q=%2F'];
        yield 'an escape that is no UTF-8 kept' => ['https://example.com/%C3', 'example.com/%C3'];
        yield 'an invisible letter kept encoded' => ['https://example.com/a%E2%80%AEb', 'example.com/a%E2%80%AEb'];
        yield 'a non-breaking space kept encoded' => ['https://example.com/a%C2%A0b', 'example.com/a%C2%A0b'];
    }

    #[DataProvider('displays')]
    public function testAnAddressIsDisplayedAsPeopleReadIt(string $text, string $display): void
    {
        self::assertSame($display, Url::fromString($text)->display());
    }

    public function testThePathIsCutInSegments(): void
    {
        self::assertSame(['@ada', 'posts'], Url::fromString('https://example.social//@ada/posts/')->segments());
        self::assertSame([], Url::fromString('https://example.social')->segments());
    }
}
