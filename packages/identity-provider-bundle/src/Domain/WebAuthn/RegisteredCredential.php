<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

/**
 * What a verified registration gives to keep: the credential identifier, its COSE public key, its counter, its
 * AAGUID, whether the user was verified, whether the passkey may be — and is — synced to other devices, and the format
 * of its attestation statement and what that statement proved.
 */
final readonly class RegisteredCredential
{
    public const int FORMAT_MAX_LENGTH = 32;

    public function __construct(
        public string $credentialId,
        public string $coseKey,
        public int $signCount,
        public string $aaguid,
        public bool $userVerified,
        public bool $backupEligible,
        public bool $backedUp,
        public string $attestationFormat = 'none',
        public AttestationType $attestationType = AttestationType::None,
    ) {
    }
}
