<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

/**
 * The canonical serialization of an audit row, over which its hash is taken.
 *
 * **This is a one-way door, and it closes at the first anchored row.** The field order and the encoding must
 * be fixed: a later change silently breaks the verification of every row written before it, and that failure
 * cannot be told apart from tampering. The bundle therefore ships the port and **no implementation**: the
 * application decides it, writes it, and freezes it before anchoring anything.
 *
 * **Versioned, and a version is never replaced.** Each row records the version of the serialization it was
 * hashed under, and the row hash covers it. Verification hashes the payload as stored and never serializes again;
 * the version tells a reader which encoding a payload is in. A new serialization is a new version added beside the
 * old one.
 *
 * Personal fields enter it only through their commitment (`Commitment`), never in plaintext.
 */
interface CanonicalSerializer
{
    /**
     * The identifier written into each row hashed under this serialization, and read back to verify it.
     *
     * @return non-empty-string stable for the life of the serializer: changing it is a new version
     */
    public function version(): string;

    /**
     * @param array<string, scalar|null> $fields the non-personal fields and the commitments, by name
     *
     * @return string stable bytes: same fields, same bytes, always
     */
    public function serialize(array $fields): string;
}
