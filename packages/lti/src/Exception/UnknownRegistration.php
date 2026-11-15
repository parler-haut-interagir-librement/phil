<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;

/**
 * A platform, a tool or a deployment that is not registered.
 */
final class UnknownRegistration extends RuntimeException implements LtiException
{
    public static function platform(string $issuer, ?string $clientId): self
    {
        return new self(null === $clientId
            ? \sprintf('No platform is registered for the issuer "%s", or several are and the client is not named.', $issuer)
            : \sprintf('No platform is registered for the issuer "%s" and the client "%s".', $issuer, $clientId));
    }

    public static function tool(string $clientId): self
    {
        return new self(\sprintf('No tool is registered under the client "%s".', $clientId));
    }

    public static function deployment(string $deploymentId): self
    {
        return new self(\sprintf('The deployment "%s" is not registered.', $deploymentId));
    }
}
