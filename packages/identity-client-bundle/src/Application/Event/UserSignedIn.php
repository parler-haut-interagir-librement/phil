<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * A sign-in through the provider completed. Carries identifiers only.
 */
final readonly class UserSignedIn
{
    public function __construct(
        public string $subject,
        public ?string $providerSessionId,
        public ?string $authenticationContextClass,
    ) {
    }
}
