<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Registration\ToolRegistration;

/**
 * The tools registered with the platform, and their deployments.
 */
interface ToolRegistrations
{
    public function find(string $clientId): ?ToolRegistration;

    public function hasDeployment(ToolRegistration $tool, string $deploymentId): bool;
}
