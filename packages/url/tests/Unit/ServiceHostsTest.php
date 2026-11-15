<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\ServiceHosts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceHosts::class)]
#[CoversClass(InvalidUrl::class)]
final class ServiceHostsTest extends TestCase
{
    public function testHostsAreKeptAsAnAddressNamesThem(): void
    {
        $hosts = new ServiceHosts(' whisper ', 'Gotenberg.', '::1', '[2001:DB8::0:1]', '127.1', '10.0.0.5', 'pdf.internal', 'whisper');

        self::assertSame(['whisper', 'gotenberg', '[::1]', '[2001:db8::1]', '127.0.0.1', '10.0.0.5', 'pdf.internal'], $hosts->all());
        self::assertTrue($hosts->declares('whisper'));
        self::assertTrue($hosts->declares('[::1]'));
        self::assertFalse($hosts->declares('Whisper'));
        self::assertFalse($hosts->declares('::1'));
        self::assertFalse($hosts->declares('other'));
        self::assertSame([], new ServiceHosts()->all());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notHosts(): iterable
    {
        yield 'nothing' => [''];
        yield 'spaces' => ['   '];
        yield 'a port' => ['whisper:8080'];
        yield 'an IPv6 address and a port' => ['[::1]:80'];
        yield 'a path' => ['whisper/inference'];
        yield 'a backslash' => ['whisper\\a'];
        yield 'a query' => ['whisper?a'];
        yield 'a fragment' => ['whisper#a'];
        yield 'a user' => ['ada@whisper'];
        yield 'an escape' => ['whis%70er'];
        yield 'a space inside' => ['whis per'];
        yield 'a control' => ["whis\x7Fper"];
        yield 'a zone' => ['[fe80::1%eth0]'];
        yield 'an address with a scheme' => ['http://whisper'];
        yield 'an open bracket' => ['[::1'];
        yield 'a forbidden character' => ['whis<per'];
        yield 'an IPv6 address that is none' => ['[1:2:3:4:5:6:7:8:9]'];
        yield 'a number past IPv4' => ['999999999999'];
    }

    #[DataProvider('notHosts')]
    public function testOnlyAHostIsDeclared(string $host): void
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a host a service can be declared on: name the host alone.', $host));

        new ServiceHosts('whisper', $host);
    }

    public function testTheHostsAreBounded(): void
    {
        $hosts = array_map(static fn (int $i): string => 'service'.$i, range(1, ServiceHosts::MAX_HOSTS));

        self::assertCount(ServiceHosts::MAX_HOSTS, new ServiceHosts(...$hosts)->all());

        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains('257 service hosts are declared; 256 at most.');

        new ServiceHosts(...[...$hosts, 'one-more']);
    }

    public function testAMessageCutsALongHost(): void
    {
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a host', str_repeat('a', 100)));

        new ServiceHosts(str_repeat('a', 150).'/');
    }

    public function testTheMessagesCutAtOneHundredCharactersNotBytes(): void
    {
        $long = str_repeat('é', 150);

        self::assertSame(\sprintf('"%s" is not a host a service can be declared on: name the host alone.', str_repeat('é', 100)), InvalidUrl::serviceHost($long)->getMessage());
        self::assertSame(\sprintf('"%s" is not a declared service host.', str_repeat('é', 100)), InvalidUrl::undeclaredServiceHost($long)->getMessage());
        self::assertSame(\sprintf('"%s" leaves the service at http://whisper.', str_repeat('é', 100)), InvalidUrl::leavesService($long, 'http://whisper')->getMessage());
    }
}
