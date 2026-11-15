<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Federation\Contract;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The port a federated provider's package implements, tagged `phil_identity_provider.external_provider`: it sends the
 * person to the provider with its own state and PKCE, and reads them back on the callback. The bundle links the
 * identity to an account and signs it in; the package keeps the provider's protocol, its keys and its tokens.
 */
interface ExternalProvider
{
    /**
     * The provider's name, a lowercase word such as `google`: the path of its routes and the key of its identities.
     */
    public function name(): string;

    /**
     * The name shown on its button, such as "Google".
     */
    public function label(): string;

    /**
     * The answer that sends the person to the provider, which will come back to the callback address.
     */
    public function start(Request $request, string $callbackUrl): Response;

    /**
     * The identity the provider vouches for, read from its answer on the callback.
     *
     * @throws Refused when the answer does not hold: a state that does not match, an error of the provider
     */
    public function finish(Request $request, string $callbackUrl): ExternalIdentity;
}
