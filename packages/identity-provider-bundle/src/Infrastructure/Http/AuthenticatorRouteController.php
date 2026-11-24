<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use LogicException;

/**
 * The controller of the routes an authenticator of the firewall answers — the passkey sign-in, the code check, the
 * callback of a federated provider —: reached only when that authenticator is missing
 * from the firewall, which it says.
 */
final readonly class AuthenticatorRouteController
{
    public function __invoke(): never
    {
        throw new LogicException("An authenticator of the firewall answers this route: add the bundle's authenticators to the firewall, as the installation guide shows.");
    }
}
