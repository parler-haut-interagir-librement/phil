<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedirectPolicy::class)]
final class RedirectPolicyTest extends TestCase
{
    public function testAnAddressOnAnAllowedOriginIsNormalised(): void
    {
        $policy = new RedirectPolicy(['https://example.org', 'https://book.example.net:8443/']);

        self::assertSame('https://example.org/thanks?a=1', $policy->check('HTTPS://Example.ORG:443/thanks?a=1'));
        self::assertSame('https://book.example.net:8443/slot', $policy->check('https://book.example.net:8443/slot'));
        self::assertTrue($policy->allows('https://example.org/'));
        // A backslash is a slash, as browsers read it: the address stays on the allowed host.
        self::assertSame('https://example.org/@evil.example/', $policy->check('https://example.org\\@evil.example/'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'another host' => ['https://evil.example/'];
        yield 'a host under an allowed one' => ['https://sub.example.org/'];
        yield 'a host that ends like an allowed one' => ['https://badexample.org/'];
        yield 'another scheme' => ['http://example.org/'];
        yield 'another port' => ['https://example.org:8443/'];
        yield 'a relative address' => ['/thanks'];
        yield 'a protocol-relative address' => ['//evil.example/'];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'credentials before the host' => ['https://example.org@evil.example/'];
        yield 'no address' => ['not an address'];
    }

    #[DataProvider('refused')]
    public function testAnyOtherAddressIsRefused(string $redirect): void
    {
        $policy = new RedirectPolicy(['https://example.org']);

        self::assertFalse($policy->allows($redirect));
        $this->expectException(InvalidQuestionnaire::class);
        $policy->check($redirect);
    }

    public function testWithNoOriginNothingRedirects(): void
    {
        self::assertFalse(new RedirectPolicy()->allows('https://example.org/'));
    }

    public function testLocalhostIsAHostOnlyWhenAllowed(): void
    {
        self::assertSame('http://localhost:8000/done', new RedirectPolicy(['http://localhost:8000'], true)->check('http://localhost:8000/done'));

        $this->expectException(InvalidQuestionnaire::class);
        new RedirectPolicy(['http://localhost:8000']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badOrigins(): iterable
    {
        yield 'a path' => ['https://example.org/path', 'An allowed origin is a scheme, a host and a port only'];
        yield 'a query' => ['https://example.org/?a=1', 'An allowed origin is a scheme, a host and a port only'];
        yield 'a fragment' => ['https://example.org/#top', 'An allowed origin is a scheme, a host and a port only'];
        yield 'not an address' => ['example', 'The allowed origin "example" is refused'];
    }

    #[DataProvider('badOrigins')]
    public function testAnOriginIsAnOriginOnly(string $origin, string $message): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RedirectPolicy([$origin]);
    }

    public function testTheRefusalNamesTheAddress(): void
    {
        $this->expectExceptionMessageIsOrContains('The redirect "https://evil.example/" is not on an origin the application allows.');

        new RedirectPolicy(['https://example.org'])->check('https://evil.example/');
    }
}
