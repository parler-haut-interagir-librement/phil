<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use Phil\SafeHttp\AddressGuard;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressGuard::class)]
#[CoversClass(RefusedAddress::class)]
final class AddressGuardTest extends TestCase
{
    public function testAPublicAddressIsNormalised(): void
    {
        self::assertSame('https://xn--bcher-kva.example/a/%C3%A9', AddressGuard::address(' HTTPS://Bücher.example:443/a/é ')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'IPv4' => ['http://127.0.0.1/'];
        yield 'short IPv4' => ['http://127.1/'];
        yield 'decimal IPv4' => ['http://2130706433/'];
        yield 'hexadecimal IPv4' => ['http://0x7f000001/'];
        yield 'IPv6' => ['http://[::ffff:127.0.0.1]/'];
        yield 'credentials' => ['https://a:b@example.com/'];
        yield 'empty credentials' => ['https://@example.com/'];
        yield 'port 0' => ['https://example.com:0/'];
        yield 'scheme' => ['ftp://example.com/'];
        yield 'single label' => ['http://localhost/'];
        yield 'too long' => ['https://example.com/'.str_repeat('a', 2048)];
        yield 'blank' => ['https://example.com/a b'];
    }

    #[DataProvider('refused')]
    public function testAnAddressThatIsNotAPublicHostNameIsRefused(string $address): void
    {
        try {
            AddressGuard::address($address);
            self::fail('The address is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame(\sprintf('"%s" is not an http or https address of a host name.', mb_substr($address, 0, 100)), $refusedAddress->getMessage());
            self::assertInstanceOf(InvalidUrl::class, $refusedAddress->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redirects(): iterable
    {
        yield 'absolute' => ['https://example.org/x', 'https://example.org/x'];
        yield 'root relative' => ['/other?q=1', 'https://example.com/other?q=1'];
        yield 'path relative' => ['next', 'https://example.com/a/next'];
        yield 'scheme relative' => ['//cdn.example.net/f', 'https://cdn.example.net/f'];
        yield 'dot segments' => ['../../../up', 'https://example.com/up'];
        yield 'query only' => ['?page=2', 'https://example.com/a/b?page=2'];
    }

    #[DataProvider('redirects')]
    public function testARedirectIsResolvedAgainstTheAddressThatAnswered(string $location, string $expected): void
    {
        self::assertSame($expected, AddressGuard::redirect(Url::fromString('https://example.com/a/b'), $location)->toString());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function refusedRedirects(): iterable
    {
        yield 'IP address' => ['http://169.254.169.254/', true];
        yield 'credentials' => ['//user:pw@example.org/', true];
        yield 'javascript' => ['javascript:alert(1)', true];
        yield 'data' => ['data:text/html,x', true];
        yield 'not a URL' => ['http://exa mple.com/', false];
        yield 'a location of the longest length, too long once resolved' => ['/'.str_repeat('a', 2047), true];
        yield 'too long' => ['/'.str_repeat('a', 2048), false];
    }

    #[DataProvider('refusedRedirects')]
    public function testARedirectToAnAddressThatIsNotPublicIsRefused(string $location, bool $parsed): void
    {
        try {
            AddressGuard::redirect(Url::fromString('https://example.com/a/b'), $location);
            self::fail('The redirect is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame(\sprintf('The redirect to "%s" is not an http or https address of a host name.', mb_substr($location, 0, 100)), $refusedAddress->getMessage());
            self::assertSame($parsed, $refusedAddress->getPrevious() instanceof InvalidUrl);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function downgrades(): iterable
    {
        yield 'same host' => ['http://example.com/plain', 'http://example.com/plain'];
        yield 'other host' => ['http://example.org/x?q=1', 'http://example.org/x?q=1'];
        yield 'scheme in capitals' => ['HTTP://EXAMPLE.COM/', 'http://example.com/'];
        yield 'port of https' => ['http://example.com:443/', 'http://example.com:443/'];
    }

    #[DataProvider('downgrades')]
    public function testARedirectFromHttpsToHttpIsRefused(string $location, string $resolved): void
    {
        try {
            AddressGuard::redirect(Url::fromString('https://example.com/a/b'), $location);
            self::fail('The downgrade is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame(\sprintf('The redirect from "https://example.com/a/b" to "%s" leaves https for http: its headers, body and answer would travel in clear text.', $resolved), $refusedAddress->getMessage());
            self::assertNull($refusedAddress->getPrevious());
        }
    }

    public function testTheAddressesOfADowngradeAreCutInItsMessage(): void
    {
        $from = 'https://example.com/'.str_repeat('f', 200);
        $to = 'http://example.org/'.str_repeat('t', 200);

        try {
            AddressGuard::redirect(Url::fromString($from), $to);
            self::fail('The downgrade is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame(\sprintf('The redirect from "%s" to "%s" leaves https for http: its headers, body and answer would travel in clear text.', substr($from, 0, 100), substr($to, 0, 100)), $refusedAddress->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sameOrUpgradedScheme(): iterable
    {
        yield 'upgrade, same host' => ['http://example.com/a', 'https://example.com/secure', 'https://example.com/secure'];
        yield 'upgrade, other host' => ['http://example.com/a', 'https://example.org/', 'https://example.org/'];
        yield 'http, relative' => ['http://example.com/a/b', 'next', 'http://example.com/a/next'];
        yield 'http, other host' => ['http://example.com/a', 'http://example.org/', 'http://example.org/'];
        yield 'https, relative' => ['https://example.com/a/b', '/other', 'https://example.com/other'];
        yield 'https, scheme relative' => ['https://example.com/a', '//example.org/f', 'https://example.org/f'];
        yield 'https, other host' => ['https://example.com/a', 'https://example.org/', 'https://example.org/'];
    }

    #[DataProvider('sameOrUpgradedScheme')]
    public function testARedirectThatKeepsOrUpgradesTheSchemeIsFollowed(string $from, string $location, string $expected): void
    {
        self::assertSame($expected, AddressGuard::redirect(Url::fromString($from), $location)->toString());
    }

    public function testALocationOfExactlyTheLongestLengthIsResolved(): void
    {
        $path = '/'.str_repeat('a', 2048 - \strlen('https://example.com/'));

        self::assertSame(2048, \strlen(AddressGuard::redirect(Url::fromString('https://example.com/'), $path)->toString()));
    }
}
