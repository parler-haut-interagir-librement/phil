<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Psr\Container\ContainerInterface;

/**
 * The key strategies by name: the bundle's four and every service implementing `KeyResolver`, by its service id.
 */
final readonly class KeyResolvers
{
    /**
     * @param ContainerInterface $resolvers the resolvers, by strategy name
     * @param list<string>       $names     the names the locator holds, for the message of an unknown one
     */
    public function __construct(
        private ContainerInterface $resolvers,
        private array $names,
    ) {
    }

    /**
     * @throws UnknownLimiter when no strategy has that name
     */
    public function get(string $strategy): KeyResolver
    {
        if (!$this->resolvers->has($strategy)) {
            throw UnknownLimiter::key($strategy, $this->names);
        }

        /** @var KeyResolver */
        return $this->resolvers->get($strategy);
    }
}
