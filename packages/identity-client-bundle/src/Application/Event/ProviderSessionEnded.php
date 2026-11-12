<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * A verified logout token ended a session at the provider.
 */
final readonly class ProviderSessionEnded
{
    public function __construct(
        public string $providerSessionId,
    ) {
    }
}
