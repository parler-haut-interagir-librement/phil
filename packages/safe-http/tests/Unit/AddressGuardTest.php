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
        yield 'downgrade' => ['http://example.com/plain', 'http://example.com/plain'];
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

    public function testALocationOfExactlyTheLongestLengthIsResolved(): void
    {
        $path = '/'.str_repeat('a', 2048 - \strlen('https://example.com/'));

        self::assertSame(2048, \strlen(AddressGuard::redirect(Url::fromString('https://example.com/'), $path)->toString()));
    }
}
