<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Unit\Domain;

use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorsPolicy::class)]
#[CoversClass(InvalidCorsPolicy::class)]
#[CoversClass(PathRule::class)]
final class CorsPolicyTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $policy = new CorsPolicy(['https://app.example.com']);

        self::assertSame(['GET', 'HEAD', 'POST'], $policy->methods);
        self::assertSame([], $policy->allowHeaders);
        self::assertFalse($policy->anyHeader);
        self::assertSame([], $policy->exposeHeaders);
        self::assertFalse($policy->allowCredentials);
        self::assertNull($policy->maxAge);
        self::assertFalse($policy->allowPrivateNetwork);
    }

    public function testAnExactOriginIsAllowedAsSentAndNoOther(): void
    {
        $policy = new CorsPolicy(['https://app.example.com', 'http://localhost:8000']);

        self::assertTrue($policy->allowsOrigin('https://app.example.com'));
        self::assertTrue($policy->allowsOrigin('http://localhost:8000'));
        self::assertFalse($policy->allowsOrigin('https://app.example.com.evil.test'));
        self::assertFalse($policy->allowsOrigin('http://app.example.com'));
        self::assertFalse($policy->allowsOrigin('http://localhost'));
        self::assertFalse($policy->allowsOrigin(''));
        self::assertSame('https://app.example.com', $policy->allowOriginValue('https://app.example.com'));
    }

    public function testAnOriginIsLowercasedWhenConfigured(): void
    {
        self::assertTrue(new CorsPolicy(['HTTPS://App.Example.COM'])->allowsOrigin('https://app.example.com'));
    }

    public function testAnOriginOfAnotherSchemeOrAnIpAddressIsAnOrigin(): void
    {
        $policy = new CorsPolicy(['capacitor://localhost', 'http://[::1]:8080', 'http://127.0.0.1:65535', 'https://a_b.example:80']);

        self::assertTrue($policy->allowsOrigin('capacitor://localhost'));
        self::assertTrue($policy->allowsOrigin('http://[::1]:8080'));
        self::assertTrue($policy->allowsOrigin('http://127.0.0.1:65535'));
        self::assertTrue($policy->allowsOrigin('https://a_b.example:80'));
    }

    public function testAPatternMatchesTheWholeOrigin(): void
    {
        $policy = new CorsPolicy([], ['^https://[a-z0-9-]+\.example\.com$', '^https://b\.test$']);

        self::assertTrue($policy->allowsOrigin('https://shop.example.com'));
        self::assertTrue($policy->allowsOrigin('https://b.test'));
        self::assertFalse($policy->allowsOrigin('https://shop.example.com.evil.test'));
        self::assertFalse($policy->allowsOrigin("https://shop.example.com\n"));
        self::assertFalse($policy->allowsOrigin('https://example.com'));
    }

    public function testASubdomainLabelPatternKeepsTheDotBoundaryThatAWildcardLoses(): void
    {
        $label = new CorsPolicy([], ['^https://[a-z0-9-]+\.example\.com$']);
        $wildcard = new CorsPolicy([], ['^https://.*example\.com$']);

        self::assertTrue($label->allowsOrigin('https://shop-2.example.com'));
        self::assertFalse($label->allowsOrigin('https://evil-example.com'));
        self::assertFalse($label->allowsOrigin('https://a.b.example.com'));
        self::assertFalse($label->allowsOrigin('https://.example.com'));
        self::assertTrue($wildcard->allowsOrigin('https://evil-example.com'));
    }

    public function testAnOriginLongerThanTheBoundIsNeverCompared(): void
    {
        $policy = new CorsPolicy([], ['^https://a+\.test$']);
        $fits = 'https://'.str_repeat('a', CorsPolicy::ORIGIN_MAX_BYTES - 13).'.test';

        self::assertSame(CorsPolicy::ORIGIN_MAX_BYTES, \strlen($fits));
        self::assertTrue($policy->allowsOrigin($fits));
        self::assertFalse($policy->allowsOrigin('https://a'.$fits));
        self::assertFalse(new CorsPolicy(['*'])->allowsOrigin('https://a'.$fits));
    }

    public function testAnyOriginWithoutCredentialsAnswersAWildcard(): void
    {
        $policy = new CorsPolicy(['*']);

        self::assertTrue($policy->allowsOrigin('https://anyone.test'));
        self::assertFalse($policy->allowsOrigin(''));
        self::assertSame('*', $policy->allowOriginValue('https://anyone.test'));
    }

    public function testPatternsWithCredentialsAnswerTheOrigin(): void
    {
        $policy = new CorsPolicy([], ['^https://(a|b)\.test$'], allowCredentials: true);

        self::assertSame('https://a.test', $policy->allowOriginValue('https://a.test'));
        self::assertTrue($policy->allowCredentials);
    }

    public function testMethodsAreUppercaseUniqueAndInOrder(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowMethods: ['put', 'GET', 'PUT', 'delete']);

        self::assertSame(['PUT', 'GET', 'DELETE'], $policy->methods);
        self::assertTrue($policy->allowsMethod('put'));
        self::assertTrue($policy->allowsMethod('DELETE'));
        self::assertFalse($policy->allowsMethod('POST'));
    }

    public function testHeadersAreComparedWithoutCase(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowHeaders: ['Content-Type', 'X-Requested-With']);

        self::assertSame(['Content-Type', 'X-Requested-With'], $policy->allowHeaders);
        self::assertTrue($policy->allowsHeaders([]));
        self::assertTrue($policy->allowsHeaders(['content-type', 'x-requested-with']));
        self::assertFalse($policy->allowsHeaders(['content-type', 'authorization']));
    }

    public function testAnyHeader(): void
    {
        $policy = new CorsPolicy(['https://a.test'], allowHeaders: ['*']);

        self::assertTrue($policy->anyHeader);
        self::assertSame([], $policy->allowHeaders);
        self::assertTrue($policy->allowsHeaders(['authorization', 'x-anything']));
    }

    public function testExposedHeaders(): void
    {
        self::assertSame(['ETag', 'Link'], new CorsPolicy(['https://a.test'], exposeHeaders: ['ETag', 'Link'])->exposeHeaders);
        self::assertSame(['*'], new CorsPolicy(['https://a.test'], exposeHeaders: ['*'])->exposeHeaders);
    }

    public function testFromConfig(): void
    {
        $policy = CorsPolicy::fromConfig([
            'allow_origins' => ['https://a.test'],
            'allow_origin_patterns' => ['^https://b\.test$'],
            'allow_methods' => ['PATCH'],
            'allow_headers' => ['Authorization'],
            'expose_headers' => ['Location'],
            'allow_credentials' => true,
            'max_age' => 600,
            'allow_private_network' => true,
        ]);

        self::assertTrue($policy->allowsOrigin('https://a.test'));
        self::assertTrue($policy->allowsOrigin('https://b.test'));
        self::assertSame(['PATCH'], $policy->methods);
        self::assertSame(['Authorization'], $policy->allowHeaders);
        self::assertSame(['Location'], $policy->exposeHeaders);
        self::assertTrue($policy->allowCredentials);
        self::assertSame(600, $policy->maxAge);
        self::assertTrue($policy->allowPrivateNetwork);
    }

    public function testAZeroMaxAgeIsKept(): void
    {
        self::assertSame(0, new CorsPolicy(['https://a.test'], maxAge: 0)->maxAge);
    }

    /**
     * @return iterable<string, array{0: callable(): CorsPolicy, 1: string}>
     */
    public static function refused(): iterable
    {
        yield 'no origin' => [static fn (): CorsPolicy => new CorsPolicy(), 'The policy allows no origin: give allow_origins or allow_origin_patterns.'];
        yield 'no method' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], allowMethods: []), 'The policy allows no method: list them in allow_methods.'];
        yield 'any origin with credentials' => [static fn (): CorsPolicy => new CorsPolicy(['*'], allowCredentials: true), 'allow_origins is "*" with allow_credentials: browsers refuse a wildcard with credentials, list the values.'];
        yield 'any header with credentials' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], allowHeaders: ['*'], allowCredentials: true), 'allow_headers is "*" with allow_credentials: browsers refuse a wildcard with credentials, list the values.'];
        yield 'any exposed header with credentials' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], exposeHeaders: ['*'], allowCredentials: true), 'expose_headers is "*" with allow_credentials: browsers refuse a wildcard with credentials, list the values.'];
        yield 'wildcard mixed' => [static fn (): CorsPolicy => new CorsPolicy(['*', 'https://a.test']), 'allow_origins mixes "*" with other values: "*" stands alone.'];
        yield 'wildcard method' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], allowMethods: ['*']), 'allow_methods is "*": list the methods by name.'];
        yield 'null origin' => [static fn (): CorsPolicy => new CorsPolicy(['NULL']), 'The origin "null" is what sandboxed frames and local files send: any page can claim it, so it is never allowed.'];
        yield 'origin with a path' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test/']), '"https://a.test/" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin without a scheme' => [static fn (): CorsPolicy => new CorsPolicy(['a.test']), '"a.test" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin with a line break' => [static fn (): CorsPolicy => new CorsPolicy(["https://a.test\n"]), "\"https://a.test\n\" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash."];
        yield 'origin with a dot at the end' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test.']), '"https://a.test." is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin starting with a dot' => [static fn (): CorsPolicy => new CorsPolicy(['https://.a.test']), '"https://.a.test" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin with a scheme starting by a digit' => [static fn (): CorsPolicy => new CorsPolicy(['1http://a.test']), '"1http://a.test" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin with a long port' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test:123456']), '"https://a.test:123456" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'origin too long' => [static fn (): CorsPolicy => new CorsPolicy(['https://'.str_repeat('a', CorsPolicy::ORIGIN_MAX_BYTES - 7)]), '"https://'.str_repeat('a', CorsPolicy::ORIGIN_MAX_BYTES - 7).'" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.'];
        yield 'default port of https' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test:443']), '"https://a.test:443" has the port 443: browsers send no default port and none above 65535.'];
        yield 'default port of http' => [static fn (): CorsPolicy => new CorsPolicy(['HTTP://a.test:80']), '"HTTP://a.test:80" has the port 80: browsers send no default port and none above 65535.'];
        yield 'port above 65535' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test:65536']), '"https://a.test:65536" has the port 65536: browsers send no default port and none above 65535.'];
        yield 'pattern without ^' => [static fn (): CorsPolicy => new CorsPolicy([], ['https://a\.test$']), 'The origin pattern "https://a\.test$" is not anchored: it starts with ^ and ends with $, so that no other host can end or start with an allowed one.'];
        yield 'pattern without $' => [static fn (): CorsPolicy => new CorsPolicy([], ['^https://a\.test']), 'The origin pattern "^https://a\.test" is not anchored: it starts with ^ and ends with $, so that no other host can end or start with an allowed one.'];
        yield 'pattern ending with an escaped $' => [static fn (): CorsPolicy => new CorsPolicy([], ['^https://a\.test\$']), 'The origin pattern "^https://a\.test\$" is not anchored: it starts with ^ and ends with $, so that no other host can end or start with an allowed one.'];
        yield 'pattern that does not compile' => [static fn (): CorsPolicy => new CorsPolicy([], ['^https://(a\.test$']), 'The origin pattern "^https://(a\.test$" is not a regular expression that compiles.'];
        yield 'method that is not a token' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], allowMethods: ['GET POST']), '"GET POST" is not an HTTP method or header name.'];
        yield 'header that is not a token' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], allowHeaders: ['X-A:']), '"X-A:" is not an HTTP method or header name.'];
        yield 'header with a line break' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], exposeHeaders: ["X-A\n"]), "\"X-A\n\" is not an HTTP method or header name."];
        yield 'empty header' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], exposeHeaders: ['']), '"" is not an HTTP method or header name.'];
        yield 'negative max age' => [static fn (): CorsPolicy => new CorsPolicy(['https://a.test'], maxAge: -1), 'max_age is -1: it is a number of seconds, 0 or more.'];
    }

    /**
     * @param callable(): CorsPolicy $build
     */
    #[DataProvider('refused')]
    public function testARefusedValue(callable $build, string $message): void
    {
        $this->expectException(InvalidCorsPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAnOriginOfTheBoundIsAccepted(): void
    {
        $origin = 'https://'.str_repeat('a', CorsPolicy::ORIGIN_MAX_BYTES - 8);

        self::assertTrue(new CorsPolicy([$origin])->allowsOrigin($origin));
    }

    public function testEveryTokenCharacterIsAccepted(): void
    {
        $token = "!#$%&'*+.^_`|~09AZaz-";

        self::assertSame([$token], new CorsPolicy(['https://a.test'], allowHeaders: [$token])->allowHeaders);
    }
}
