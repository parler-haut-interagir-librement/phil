<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use Phil\OpenBadges\Verification\VerifiedCredential;

/**
 * What the verification page shows: the verified credential or the reason it was refused, whether its status applies
 * (null when unknown), and whether the identity given is the recipient's (null when none was given).
 */
final readonly class CheckResult
{
    public function __construct(
        public ?VerifiedCredential $credential,
        public ?bool $statusApplies = null,
        public ?bool $recipientMatches = null,
        public ?string $reason = null,
    ) {
    }

    public static function refused(string $reason): self
    {
        return new self(null, reason: $reason);
    }

    /**
     * Whether the credential holds: verified, not revoked nor suspended, and the recipient's when an identity was
     * given.
     */
    public function holds(): bool
    {
        return $this->credential instanceof VerifiedCredential && true !== $this->statusApplies && false !== $this->recipientMatches;
    }

    /**
     * A field of the credential's achievement, or of the credential itself for a credential of another kind.
     */
    public function achievement(string $field): ?string
    {
        $credential = $this->credential instanceof VerifiedCredential ? $this->credential->credential : [];
        $subject = $credential['credentialSubject'] ?? null;
        $achievement = \is_array($subject) && \is_array($subject['achievement'] ?? null) ? $subject['achievement'] : $credential;
        $value = $achievement[$field] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * The issuer's name, or its identifier.
     */
    public function issuerName(): ?string
    {
        if (!$this->credential instanceof VerifiedCredential) {
            return null;
        }

        $issuer = $this->credential->credential['issuer'] ?? null;
        $name = \is_array($issuer) ? $issuer['name'] ?? null : null;

        return \is_string($name) ? $name : $this->credential->issuer();
    }
}
