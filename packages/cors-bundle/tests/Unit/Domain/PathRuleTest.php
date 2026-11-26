<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Unit\Domain;

use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use Phil\CorsBundle\Infrastructure\PathPolicyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PathRule::class)]
#[CoversClass(PathPolicyResolver::class)]
final class PathRuleTest extends TestCase
{
    public function testAPatternMatchesThePath(): void
    {
        $rule = new PathRule('^/api/', new CorsPolicy(['https://a.test']));

        self::assertSame('^/api/', $rule->pattern);
        self::assertTrue($rule->matches('/api/books'));
        self::assertFalse($rule->matches('/v1/api/books'));
        self::assertFalse($rule->matches('/apis'));
    }

    public function testAPatternMayHoldBracesAndItsDelimiters(): void
    {
        $rule = new PathRule('^/v[0-9]{1,2}/#~/', new CorsPolicy(['https://a.test']));

        self::assertTrue($rule->matches('/v12/#~/x'));
        self::assertFalse($rule->matches('/v123/#~/x'));
    }

    public function testADollarMatchesOnlyTheEnd(): void
    {
        $rule = new PathRule('^/feed$', new CorsPolicy(['https://a.test']));

        self::assertTrue($rule->matches('/feed'));
        self::assertFalse($rule->matches("/feed\n"));
    }

    public function testFromConfig(): void
    {
        $rule = PathRule::fromConfig('^/', [
            'allow_origins' => ['https://a.test'],
            'allow_origin_patterns' => [],
            'allow_methods' => ['GET'],
            'allow_headers' => [],
            'expose_headers' => [],
            'allow_credentials' => false,
            'max_age' => null,
            'allow_private_network' => false,
        ]);

        self::assertSame(['GET'], $rule->policy->methods);
        self::assertTrue($rule->matches('/x'));
    }

    public function testAPatternThatDoesNotCompileIsRefused(): void
    {
        $this->expectException(InvalidCorsPolicy::class);
        $this->expectExceptionMessageIsOrContains('The path pattern "^/api/(" is not a regular expression that compiles.');

        new PathRule('^/api/(', new CorsPolicy(['https://a.test']));
    }

    public function testAnEmptyPatternIsRefused(): void
    {
        $this->expectException(InvalidCorsPolicy::class);
        $this->expectExceptionMessageIsOrContains('The path pattern "" is not a regular expression that compiles.');

        new PathRule('', new CorsPolicy(['https://a.test']));
    }

    public function testTheErrorHandlerIsRestored(): void
    {
        $handler = static fn (): bool => false;
        set_error_handler($handler);

        try {
            PathRule::compile('^/a', 'path pattern');

            try {
                PathRule::compile('(', 'path pattern');
            } catch (InvalidCorsPolicy) {
            }

            self::assertSame($handler, set_error_handler(null));
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }

    public function testAPatternThatDoesNotCompileRaisesNoWarning(): void
    {
        error_clear_last();

        try {
            PathRule::compile('(', 'path pattern');
            self::fail('The pattern does not compile.');
        } catch (InvalidCorsPolicy) {
        }

        self::assertNull(error_get_last());
    }

    public function testTheFirstMatchingRuleWins(): void
    {
        $narrow = new CorsPolicy(['https://admin.test']);
        $wide = new CorsPolicy(['*']);
        $resolver = new PathPolicyResolver([new PathRule('^/api/admin/', $narrow), new PathRule('^/api/', $wide)]);

        self::assertSame($narrow, $resolver->resolve(Request::create('/api/admin/users')));
        self::assertSame($wide, $resolver->resolve(Request::create('/api/books')));
        self::assertNull($resolver->resolve(Request::create('/books')));
    }

    public function testThePathInfoIsMatchedWithoutTheQuery(): void
    {
        $policy = new CorsPolicy(['https://a.test']);
        $resolver = new PathPolicyResolver([new PathRule('^/api$', $policy)]);

        self::assertSame($policy, $resolver->resolve(Request::create('/api?x=/other')));
        self::assertNull(new PathPolicyResolver([])->resolve(Request::create('/api')));
    }
}
