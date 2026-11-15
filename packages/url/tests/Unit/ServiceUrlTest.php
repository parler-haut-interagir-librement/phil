<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Url\AddressReader;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\ServiceHosts;
use Phil\Url\ServiceUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceUrl::class)]
#[CoversClass(AddressReader::class)]
#[CoversClass(InvalidUrl::class)]
final class ServiceUrlTest extends TestCase
{
    private ServiceHosts $hosts;

    protected function setUp(): void
    {
        $this->hosts = new ServiceHosts('whisper', 'gotenberg', '10.0.0.5', '::1', 'pdf.internal');
    }

    public function testAServiceAddressIsReadInParts(): void
    {
        $url = ServiceUrl::fromString('http://whisper:8080/inference?lang=fr#top', $this->hosts);

        self::assertSame(['http', 'whisper', 8080, '/inference', 'lang=fr', 'top'], [$url->scheme, $url->host, $url->port, $url->path, $url->query, $url->fragment]);
        self::assertSame('http://whisper:8080/inference?lang=fr#top', $url->toString());
        self::assertSame('http://whisper:8080/inference?lang=fr#top', (string) $url);
        self::assertSame('http://whisper:8080', $url->origin());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function services(): iterable
    {
        yield 'a bare name' => ['http://whisper:8080', 'http://whisper:8080/', 'http://whisper:8080'];
        yield 'in capitals' => ['HTTP://Gotenberg:3000/Forms', 'http://gotenberg:3000/Forms', 'http://gotenberg:3000'];
        yield 'a trailing dot' => ['http://gotenberg./', 'http://gotenberg/', 'http://gotenberg'];
        yield 'the default port' => ['https://whisper:443/', 'https://whisper/', 'https://whisper'];
        yield 'an IPv4 address' => ['http://10.0.0.5:9000/', 'http://10.0.0.5:9000/', 'http://10.0.0.5:9000'];
        yield 'an IPv4 address written short' => ['http://10.5/', 'http://10.0.0.5/', 'http://10.0.0.5'];
        yield 'an IPv6 address' => ['http://[0:0::1]:9000/a/../b', 'http://[::1]:9000/b', 'http://[::1]:9000'];
        yield 'a private name' => ['https://pdf.internal/convert?a=<b>', 'https://pdf.internal/convert?a=%3Cb%3E', 'https://pdf.internal'];
        yield 'empty query and fragment' => ['http://whisper/?#', 'http://whisper/', 'http://whisper'];
    }

    #[DataProvider('services')]
    public function testADeclaredHostIsRead(string $text, string $written, string $origin): void
    {
        $url = ServiceUrl::fromString($text, $this->hosts);

        self::assertSame($written, $url->toString());
        self::assertSame($origin, $url->origin());
        self::assertTrue($url->equals(ServiceUrl::fromString($written, $this->hosts)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'a host not declared' => ['http://redis:6379/', '"redis" is not a declared service host.'];
        yield 'a public host not declared' => ['https://example.com/', '"example.com" is not a declared service host.'];
        yield 'an address not declared' => ['http://10.0.0.6/', '"10.0.0.6" is not a declared service host.'];
        yield 'a name under a declared one' => ['http://a.whisper/', '"a.whisper" is not a declared service host.'];
        yield 'another scheme' => ['ftp://whisper/', '"ftp://whisper/" is not an http or https address.'];
        yield 'a user' => ['http://ada:secret@whisper/', 'A web address carries no user name nor password.'];
        yield 'port 0' => ['http://whisper:0/', '0 is not a port a web address can name.'];
        yield 'a space' => ['http://whisper/a b', 'is not an http or https address.'];
        yield 'a port too high' => ['http://whisper:65536/', 'is not an http or https address.'];
        yield 'too long' => ['http://whisper/'.str_repeat('a', 2048), 'A web address is 2048 characters at most; this one is 2063.'];
        yield 'too long once encoded' => ['http://whisper/'.str_repeat('<', 1000), 'A web address is 2048 characters at most; this one is 3015.'];
    }

    #[DataProvider('refused')]
    public function testOtherAddressesAreRefused(string $text, string $message): void
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains($message);

        ServiceUrl::fromString($text, $this->hosts);
    }

    public function testUrlKeepsRefusingAServiceHost(): void
    {
        foreach (['http://whisper:8080', 'http://10.0.0.5/', 'http://[::1]/'] as $text) {
            try {
                Url::fromString($text);
                self::fail($text.' was read as a Url.');
            } catch (InvalidUrl $invalidUrl) {
                self::assertStringEndsWith('is not a host name.', $invalidUrl->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function references(): iterable
    {
        yield 'a relative path' => ['inference', 'http://whisper:8080/v1/inference'];
        yield 'an absolute path' => ['/health?full=1', 'http://whisper:8080/health?full=1'];
        yield 'a climb past the root' => ['../../../x', 'http://whisper:8080/x'];
        yield 'a query alone' => ['?limit=5', 'http://whisper:8080/v1/models?limit=5'];
        yield 'the same origin, written whole' => ['HTTP://Whisper:8080/a', 'http://whisper:8080/a'];
        yield 'nothing' => ['', 'http://whisper:8080/v1/models'];
    }

    #[DataProvider('references')]
    public function testAReferenceIsResolvedOnTheSameService(string $reference, string $written): void
    {
        self::assertSame($written, ServiceUrl::fromString('http://whisper:8080/v1/models', $this->hosts)->resolve($reference)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function leaving(): iterable
    {
        yield 'another host' => ['//gotenberg:8080/', '"//gotenberg:8080/" leaves the service at http://whisper:8080.'];
        yield 'an undeclared host' => ['http://redis:8080/', '"http://redis:8080/" leaves the service at http://whisper:8080.'];
        yield 'another scheme' => ['https://whisper:8080/', '"https://whisper:8080/" leaves the service at http://whisper:8080.'];
        yield 'another port' => ['//whisper:8081/', '"//whisper:8081/" leaves the service at http://whisper:8080.'];
        yield 'the default port' => ['//whisper/', '"//whisper/" leaves the service at http://whisper:8080.'];
        yield 'credentials' => ['//ada@whisper:8080/', 'A web address carries no user name nor password.'];
        yield 'no address' => ['http://[::1/', '"http://[::1/" is not an http or https address.'];
        yield 'too long' => [str_repeat('../', 683), 'is not an http or https address.'];
    }

    #[DataProvider('leaving')]
    public function testAReferenceStaysOnItsService(string $reference, string $message): void
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains($message);

        ServiceUrl::fromString('http://whisper:8080/v1/', $this->hosts)->resolve($reference);
    }

    public function testAReferenceOfTheLimitIsResolved(): void
    {
        self::assertSame('http://whisper:8080/', ServiceUrl::fromString('http://whisper:8080/v1/', $this->hosts)->resolve(str_repeat('../', 682).'..')->toString());
    }

    public function testEqualAddressesAreEqual(): void
    {
        self::assertTrue(ServiceUrl::fromString('http://whisper:80/a', $this->hosts)->equals(ServiceUrl::fromString('HTTP://whisper/a', $this->hosts)));
        self::assertFalse(ServiceUrl::fromString('http://whisper/a', $this->hosts)->equals(ServiceUrl::fromString('http://whisper/b', $this->hosts)));
    }
}
