<?php

declare(strict_types=1);

namespace Phil\Checkin\Contract;

use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;

/**
 * The credentials an application can list, for the devices that check in offline: those of a checkpoint with their
 * codes, to fingerprint in a manifest, and one by its identifier, to confirm a scan made offline.
 */
interface CredentialCatalog
{
    /**
     * The credentials that may be scanned at a checkpoint, each with one of its codes; a credential with several
     * codes comes once per code. The manifest keeps only those that open the checkpoint.
     *
     * @return iterable<CatalogEntry>
     */
    public function forCheckpoint(CheckpointId $checkpoint): iterable;

    public function find(string $credentialId): ?Credential;
}
