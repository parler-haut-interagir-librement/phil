<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

/**
 * What a verified sign-in with a passkey gives: the new counter to keep, whether the user was verified — by a PIN or
 * a biometric, which makes the passkey two factors —, and whether the passkey is backed up.
 */
final readonly class VerifiedAssertion
{
    public function __construct(
        public int $signCount,
        public bool $userVerified,
        public bool $backedUp,
    ) {
    }
}
