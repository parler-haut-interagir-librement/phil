<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredOriginsReturnToPolicy::class)]
final class DeclaredOriginsReturnToPolicyTest extends TestCase
{
    private DeclaredOriginsReturnToPolicy $policy;

    #[Override]
    protected function setUp(): void
    {
        $this->policy = new DeclaredOriginsReturnToPolicy(['https://app.example.test', 'https://app.example.test:8443', 'http://localhost', 'not a url', 'mailto:a@example.test']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accepted(): iterable
    {
        yield 'root' => ['/', '/'];
        yield 'a path' => ['/a/b', '/a/b'];
        yield 'a query' => ['/a?x=1&y=2', '/a?x=1&y=2'];
        yield 'normalized' => ['/a/../b', '/b'];
        yield 'a fragment is dropped' => ['/a#top', '/a'];
        yield 'a declared origin' => ['https://app.example.test/x?y=1', 'https://app.example.test/x?y=1'];
        yield 'an uppercase host' => ['https://APP.example.test/x', 'https://app.example.test/x'];
        yield 'a declared port' => ['https://app.example.test:8443/x', 'https://app.example.test:8443/x'];
        yield 'a default port' => ['https://app.example.test:443/x', 'https://app.example.test/x'];
        yield 'a local origin' => ['http://localhost/x', 'http://localhost/x'];
        yield 'at the length limit' => ['/'.str_repeat('a', 2047), '/'.str_repeat('a', 2047)];
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $candidate, string $expected): void
    {
        self::assertSame($expected, $this->policy->resolve($candidate));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => ['/'.str_repeat('a', 2048)];
        yield 'another origin' => ['https://evil.example.test/'];
        yield 'another port' => ['https://app.example.test:444/'];
        yield 'another scheme' => ['http://app.example.test/'];
        yield 'a host with the same prefix' => ['https://app.example.test.evil.example/'];
        yield 'credentials' => ['https://user@app.example.test/'];
        yield 'a password' => ['https://:pw@app.example.test/'];
        yield 'credentials hiding the host' => ['https://app.example.test@evil.example.test/'];
        yield 'scheme relative' => ['//evil.example.test/'];
        yield 'a backslash' => ['/\evil.example.test/'];
        yield 'three slashes' => ['///evil.example.test/'];
        yield 'normalizing to a double slash' => ['/.//evil.example.test/'];
        yield 'normalizing through a parent' => ['/a/..//evil.example.test/'];
        yield 'a tab' => ["/\t/evil.example.test/"];
        yield 'a newline' => ["/a\nb"];
        yield 'a carriage return' => ["/a\rb"];
        yield 'a null byte' => ["/a\0b"];
        yield 'a delete character' => ["/a\x7Fb"];
        yield 'a space' => ['/a b'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,x'];
        yield 'a scheme declared without host' => ['mailto:b@example.test'];
        yield 'a relative path' => ['a/b'];
        yield 'a query alone' => ['?a=1'];
        yield 'the internal base' => ['https://local.invalid/x'];
        yield 'unparseable' => ['https://[bad/'];
    }

    #[DataProvider('refused')]
    public function testRefused(string $candidate): void
    {
        self::assertNull($this->policy->resolve($candidate));
    }

    public function testWithoutDeclaredOriginsOnlyPathsPass(): void
    {
        $policy = new DeclaredOriginsReturnToPolicy([]);

        self::assertSame('/a', $policy->resolve('/a'));
        self::assertNull($policy->resolve('https://app.example.test/'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function declarable(): iterable
    {
        yield 'https' => ['https://app.example.test', true];
        yield 'a port' => ['https://app.example.test:8443', true];
        yield 'a trailing slash' => ['https://app.example.test/', true];
        yield 'localhost' => ['http://localhost', true];
        yield 'loopback' => ['http://127.0.0.1:8000', true];
        yield 'ipv6 loopback' => ['http://[::1]:8000', true];
        yield 'plain http' => ['http://app.example.test', false];
        yield 'a path' => ['https://app.example.test/a', false];
        yield 'a query' => ['https://app.example.test?a', false];
        yield 'uppercase' => ['https://APP.example.test', false];
        yield 'not an origin' => ['app.example.test', false];
        yield 'another scheme' => ['ftp://app.example.test', false];
        yield 'another scheme on a loopback host' => ['ftp://localhost', false];
    }

    #[DataProvider('declarable')]
    public function testDeclarableOrigins(string $origin, bool $expected): void
    {
        self::assertSame($expected, DeclaredOriginsReturnToPolicy::isDeclarable($origin));
    }
}
