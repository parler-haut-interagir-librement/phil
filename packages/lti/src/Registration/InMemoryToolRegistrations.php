<?php

declare(strict_types=1);

namespace Phil\Lti\Registration;

use Override;
use Phil\Lti\Contract\ToolRegistrations;

/**
 * Tools registered in memory, from the configuration of the application or in tests.
 */
final class InMemoryToolRegistrations implements ToolRegistrations
{
    /** @var array<string, ToolRegistration> */
    private array $tools = [];

    /** @var array<string, array<string, true>> */
    private array $deployments = [];

    /**
     * @param list<string> $deploymentIds
     */
    public function add(ToolRegistration $tool, array $deploymentIds = []): self
    {
        $this->tools[$tool->clientId] = $tool;

        foreach ($deploymentIds as $deploymentId) {
            $this->deployments[$tool->clientId][$deploymentId] = true;
        }

        return $this;
    }

    #[Override]
    public function find(string $clientId): ?ToolRegistration
    {
        return $this->tools[$clientId] ?? null;
    }

    #[Override]
    public function hasDeployment(ToolRegistration $tool, string $deploymentId): bool
    {
        return isset($this->deployments[$tool->clientId][$deploymentId]);
    }
}
