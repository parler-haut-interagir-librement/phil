<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application\Event;

/**
 * A response or a logout message of an identity provider was refused: the key of the identity provider, the reason —
 * the message of the exception, which names no person — and the class of the exception, for the logs and the alerts of
 * the application. The browser sees only that the sign-in was refused.
 */
final readonly class SamlResponseRefused
{
    public function __construct(public string $identityProvider, public string $reason, public string $exception)
    {
    }
}
