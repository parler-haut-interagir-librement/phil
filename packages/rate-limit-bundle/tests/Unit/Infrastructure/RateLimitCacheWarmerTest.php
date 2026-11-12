<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitCacheWarmer;
use Phil\RateLimitBundle\Tests\Unit\Fixture\HeaderKey;
use Phil\RateLimitBundle\Tests\Unit\Fixture\LimitedController;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Locator;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use Phil\RateLimitBundle\Tests\Unit\Fixture\TwoRulesController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

#[CoversClass(RateLimitCacheWarmer::class)]
final class RateLimitCacheWarmerTest extends TestCase
{
    public function testItCannotBeSkipped(): void
    {
        self::assertFalse($this->warmer([])->isOptional());
    }

    public function testNoRouterNothingToCheck(): void
    {
        self::assertSame([], new RateLimitCacheWarmer($this->limiters(), $this->resolvers())->warmUp('/tmp'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function valid(): iterable
    {
        yield 'no rule' => [[]];
        yield 'a declared limiter' => [['_rate_limit' => 'known']];
        yield 'a declared limiter and key' => [['_rate_limit' => ['limiter' => 'known', 'key' => 'ip']]];
        yield 'a class and a method' => [['_controller' => LimitedController::class.'::listed']];
        yield 'an invokable' => [['_controller' => LimitedController::class]];
        yield 'a pair' => [['_controller' => [LimitedController::class, 'listed']]];
        yield 'a service' => [['_controller' => 'app.limited::listed']];
        yield 'a closure' => [['_controller' => static fn (): null => null]];
        yield 'an unknown class' => [['_controller' => 'Unknown\\Thing::go']];
        yield 'an unknown method' => [['_controller' => LimitedController::class.'::gone']];
        yield 'a pair of other things' => [['_controller' => [1, 2]]];
        yield 'a pair holding an object' => [['_controller' => [new stdClass(), 'unknown']]];
        yield 'a method after two separators' => [['_controller' => LimitedController::class.'::unknown::again']];
    }

    /**
     * @param array<string, mixed> $defaults
     */
    #[DataProvider('valid')]
    public function testValidRulesPass(array $defaults): void
    {
        self::assertSame([], $this->warmer($defaults)->warmUp('/tmp', '/build'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an unknown limiter' => [['_rate_limit' => 'nothing'], 'The rate limits of the route "page": No rate limiter is named "nothing"; the limiters declared are "known", "other".'];
        yield 'an unknown key' => [['_rate_limit' => ['limiter' => 'known', 'key' => 'nothing']], 'The rate limits of the route "page": No key strategy is named "nothing"'];
        yield 'a wrong shape' => [['_rate_limit' => ['limiter' => 3]], 'The rate limits of the route "page": A rate limit is'];
        yield 'an attribute, unknown limiter' => [['_controller' => LimitedController::class.'::unknown'], 'No rate limiter is named "nothing"'];
        yield 'an attribute, unknown key' => [['_controller' => LimitedController::class.'::unknownKey'], 'No key strategy is named "nothing"'];
        yield 'an attribute written wrong' => [['_controller' => LimitedController::class.'::invalid'], 'A rate limit names its limiter.'];
        yield 'an attribute of a pair' => [['_controller' => [LimitedController::class, 'unknown']], 'No rate limiter is named "nothing"'];
        yield 'an attribute of a service' => [['_controller' => 'app.limited::unknown'], 'No rate limiter is named "nothing"'];
        yield 'an attribute, unknown limiter, declared key' => [['_controller' => LimitedController::class.'::unknownLimiter'], 'No rate limiter is named "nothing"'];
        yield 'the second attribute of a class' => [['_controller' => TwoRulesController::class], 'No rate limiter is named "nothing"'];
    }

    /**
     * @param array<string, mixed> $defaults
     */
    #[DataProvider('invalid')]
    public function testAnInvalidRuleStopsTheBuild(array $defaults, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $this->expectExceptionCode(0);

        $this->warmer($defaults)->warmUp('/tmp');
    }

    public function testTheKeyOfTheLimiterIsChecked(): void
    {
        $warmer = new RateLimitCacheWarmer(
            new Limiters(new Locator(['known' => new ScriptedLimiter(1)]), ['known' => ['key' => 'gone', 'amount' => 1]]),
            $this->resolvers(),
            $this->router(['_rate_limit' => 'known']),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('No key strategy is named "gone"');

        $warmer->warmUp('/tmp');
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function warmer(array $defaults): RateLimitCacheWarmer
    {
        return new RateLimitCacheWarmer($this->limiters(), $this->resolvers(), $this->router($defaults), ['app.limited' => LimitedController::class]);
    }

    private function limiters(): Limiters
    {
        return new Limiters(new Locator(['known' => new ScriptedLimiter(1), 'other' => new ScriptedLimiter(1)]), ['known' => ['key' => 'ip', 'amount' => 1], 'other' => ['key' => 'ip', 'amount' => 1]]);
    }

    private function resolvers(): KeyResolvers
    {
        return new KeyResolvers(new Locator(['ip' => new HeaderKey()]), ['ip']);
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function router(array $defaults): Router
    {
        $routes = new RouteCollection();
        $routes->add('page', new Route('/page', $defaults));

        $loader = new class($routes) extends Loader {
            public function __construct(private readonly RouteCollection $routes)
            {
                parent::__construct();
            }

            public function load(mixed $resource, ?string $type = null): RouteCollection
            {
                return $this->routes;
            }

            public function supports(mixed $resource, ?string $type = null): bool
            {
                return true;
            }
        };

        return new Router($loader, 'routes');
    }
}
