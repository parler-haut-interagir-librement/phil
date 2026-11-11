<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Verification;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Status\StatusListEntry;

/**
 * A credential whose proof verified and whose period of validity holds: the credential without its proof, and how it
 * was secured — `jwt` or `data-integrity`.
 */
final readonly class VerifiedCredential
{
    /**
     * @param array<array-key, mixed> $credential
     */
    public function __construct(
        public array $credential,
        public string $securedAs,
    ) {
    }

    /**
     * The credential's status entry, when it can be revoked or suspended through a Bitstring Status List.
     *
     * @throws InvalidCredential when its status is of another kind
     */
    public function status(): ?StatusListEntry
    {
        $status = $this->credential['credentialStatus'] ?? null;

        if (null === $status) {
            return null;
        }

        return StatusListEntry::fromArray(\is_array($status) ? $status : []);
    }

    /**
     * The issuer's identifier.
     */
    public function issuer(): ?string
    {
        $issuer = $this->credential['issuer'] ?? null;
        $id = \is_array($issuer) ? $issuer['id'] ?? null : $issuer;

        return \is_string($id) ? $id : null;
    }
}
