<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Catalog;

use Override;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Credential;

/**
 * Several catalogs asked in their order, so that each module that admits its own credentials — tickets, registrations,
 * memberships — plugs its catalog in without knowing the others: a checkpoint's credentials are those of every
 * catalog, the first catalogs first, and a credential is found by the first catalog that knows its identifier.
 *
 * The identifiers of two catalogs must not collide: a module prefixes its own, such as `ticket:1234`.
 */
final readonly class CredentialCatalogs implements CredentialCatalog
{
    /**
     * @param iterable<CredentialCatalog> $catalogs
     */
    public function __construct(
        private iterable $catalogs = [],
    ) {
    }

    /**
     * @return iterable<CatalogEntry>
     */
    #[Override]
    public function forCheckpoint(CheckpointId $checkpoint): iterable
    {
        foreach ($this->catalogs as $catalog) {
            // One entry after the other, without the keys of each catalog, which may repeat from one to the next.
            foreach ($catalog->forCheckpoint($checkpoint) as $entry) {
                yield $entry;
            }
        }
    }

    #[Override]
    public function find(string $credentialId): ?Credential
    {
        foreach ($this->catalogs as $catalog) {
            $credential = $catalog->find($credentialId);

            if ($credential instanceof Credential) {
                return $credential;
            }
        }

        return null;
    }
}
