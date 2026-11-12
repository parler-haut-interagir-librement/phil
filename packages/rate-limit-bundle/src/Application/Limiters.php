<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Psr\Container\ContainerInterface;

/**
 * The declared limiters by name, each with the key strategy and the amount a rule that names neither takes, and
 * whether it observes rather than refuses. The limiters themselves are built on first use, from a service locator.
 */
final readonly class Limiters
{
    /**
     * @param ContainerInterface                                             $limiters the limiters, by name
     * @param array<string, array{key: string, amount: int, observe?: bool}> $defaults the key strategy and amount of each, by name, and whether it only observes
     */
    public function __construct(
        private ContainerInterface $limiters,
        private array $defaults,
    ) {
    }

    /**
     * @throws UnknownLimiter when no limiter has that name
     */
    public function get(string $name): Limiter
    {
        $this->defaultsOf($name);

        /** @var Limiter */
        return $this->limiters->get($name);
    }

    public function keyOf(string $name): string
    {
        return $this->defaultsOf($name)['key'];
    }

    public function amountOf(string $name): int
    {
        return $this->defaultsOf($name)['amount'];
    }

    /**
     * Whether the limiter of that name only observes: its refusals are reported, never enforced.
     *
     * @throws UnknownLimiter when no limiter has that name
     */
    public function observes(string $name): bool
    {
        return $this->defaultsOf($name)['observe'] ?? false;
    }

    /**
     * @return array{key: string, amount: int, observe?: bool}
     */
    private function defaultsOf(string $name): array
    {
        return $this->defaults[$name] ?? throw UnknownLimiter::named($name, array_keys($this->defaults));
    }
}
