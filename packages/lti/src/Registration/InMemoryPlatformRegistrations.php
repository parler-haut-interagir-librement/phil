<?php

declare(strict_types=1);

namespace Phil\Lti\Registration;

use Override;
use Phil\Lti\Contract\PlatformRegistrations;

/**
 * Platforms registered in memory, from the configuration of the application or in tests.
 */
final class InMemoryPlatformRegistrations implements PlatformRegistrations
{
    /** @var array<string, PlatformRegistration> */
    private array $platforms = [];

    /** @var array<string, array<string, true>> */
    private array $deployments = [];

    /**
     * @param list<string> $deploymentIds
     */
    public function add(PlatformRegistration $platform, array $deploymentIds = []): self
    {
        $this->platforms[$platform->key()] = $platform;

        foreach ($deploymentIds as $deploymentId) {
            $this->deployments[$platform->key()][$deploymentId] = true;
        }

        return $this;
    }

    #[Override]
    public function find(string $issuer, ?string $clientId = null): ?PlatformRegistration
    {
        $found = array_values(array_filter($this->platforms, static fn (PlatformRegistration $platform): bool => $platform->issuer === $issuer && (null === $clientId || $platform->clientId === $clientId)));

        return 1 === \count($found) ? $found[0] : null;
    }

    #[Override]
    public function hasDeployment(PlatformRegistration $platform, string $deploymentId): bool
    {
        return isset($this->deployments[$platform->key()][$deploymentId]);
    }
}
