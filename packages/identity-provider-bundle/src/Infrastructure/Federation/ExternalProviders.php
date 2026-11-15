<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Federation;

use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;

/**
 * The federated providers installed, by name.
 */
final readonly class ExternalProviders
{
    /** @var array<string, ExternalProvider> */
    private array $providers;

    /**
     * @param iterable<ExternalProvider> $providers
     */
    public function __construct(iterable $providers = [])
    {
        $byName = [];

        foreach ($providers as $provider) {
            $byName[$provider->name()] = $provider;
        }

        ksort($byName, \SORT_STRING);
        $this->providers = $byName;
    }

    public function get(string $name): ?ExternalProvider
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return list<ExternalProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }
}
