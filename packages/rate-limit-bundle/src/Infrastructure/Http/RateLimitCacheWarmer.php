<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Http;

use LogicException;
use Override;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\RateLimitException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Stops the build on a rule of a route or a controller that a request would only discover: the `_rate_limit` default
 * and the `#[RateLimit]` attributes of the controller of every route, each naming a declared limiter and key strategy.
 *
 * It runs when the routes can be read, after the container is compiled, and it is not optional: Symfony runs it each
 * time it builds the container. A controller written as a closure has no class to read and waits for its first request.
 */
final readonly class RateLimitCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param array<string, string> $controllers the class of each controller service whose id is not a class
     */
    public function __construct(
        private Limiters $limiters,
        private KeyResolvers $resolvers,
        private ?RouterInterface $router = null,
        private array $controllers = [],
    ) {
    }

    #[Override]
    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return list<string> no file to preload
     *
     * @throws LogicException when a rule names what nothing declares, or is written wrong
     */
    #[Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->router?->getRouteCollection() ?? [] as $name => $route) {
            try {
                foreach ([...$this->attributes($route->getDefault('_controller')), ...RateLimitRules::from($route->getDefault('_rate_limit'))] as $rule) {
                    $this->check($rule);
                }
            } catch (RateLimitException $rateLimitException) {
                throw new LogicException(\sprintf('The rate limits of the route "%s": %s', $name, $rateLimitException->getMessage()), 0, $rateLimitException);
            }
        }

        return [];
    }

    private function check(RateLimit $rule): void
    {
        $this->resolvers->get($rule->key ?? $this->limiters->keyOf($rule->limiter));
        $this->limiters->get($rule->limiter);
    }

    /**
     * The attributes of a controller, written `class::method`, `service::method`, `class` for an invokable, or as a
     * pair of strings: those of the class, then those of the method. A pair holding an object has no class to read.
     *
     * @return list<RateLimit>
     */
    private function attributes(mixed $controller): array
    {
        if (\is_array($controller)) {
            $controller = implode('::', array_filter($controller, \is_string(...)));
        }

        if (!\is_string($controller)) {
            return [];
        }

        [$id, $method] = explode('::', $controller, 2) + [1 => '__invoke'];
        $class = $this->controllers[$id] ?? $id;

        if (!class_exists($class) || !method_exists($class, $method)) {
            return [];
        }

        return array_map(
            static fn (ReflectionAttribute $attribute): RateLimit => $attribute->newInstance(),
            [...new ReflectionClass($class)->getAttributes(RateLimit::class), ...new ReflectionMethod($class, $method)->getAttributes(RateLimit::class)],
        );
    }
}
