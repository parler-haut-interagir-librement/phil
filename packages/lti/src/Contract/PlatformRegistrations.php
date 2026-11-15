<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Registration\PlatformRegistration;

/**
 * The platforms registered with the tool, and their deployments.
 */
interface PlatformRegistrations
{
    /**
     * The platform of this issuer and client; without the client, the only platform of the issuer. Null when none
     * matches, or when the client is not given and the issuer has several.
     */
    public function find(string $issuer, ?string $clientId = null): ?PlatformRegistration;

    /**
     * Whether the platform registered this deployment of the tool.
     */
    public function hasDeployment(PlatformRegistration $platform, string $deploymentId): bool;
}
