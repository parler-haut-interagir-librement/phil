<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use Override;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CredentialCatalogs::class)]
final class CredentialCatalogsTest extends TestCase
{
    public function testACheckpointHasTheCredentialsOfEveryCatalogInTheirOrder(): void
    {
        $catalogs = new CredentialCatalogs([new FixedCredentials(), $this->registrations()]);
        $entries = $catalogs->forCheckpoint(CheckpointId::of('hall'));

        // The keys of each catalog start again at zero: the entries must not overwrite each other.
        self::assertSame(['T-1', 'T-2', 'registration:7'], array_map(static fn (CatalogEntry $entry): string => $entry->credential->id, iterator_to_array($entries)));
        self::assertSame([], iterator_to_array(new CredentialCatalogs()->forCheckpoint(CheckpointId::of('hall'))));
    }

    public function testACredentialIsFoundByTheFirstCatalogThatKnowsIt(): void
    {
        $catalogs = new CredentialCatalogs([new FixedCredentials(), $this->registrations()]);

        self::assertSame('Élise', $catalogs->find('T-1')?->label);
        self::assertSame('Registration 7', $catalogs->find('registration:7')?->label);
        self::assertNull($catalogs->find('ticket:404'));
        self::assertNull(new CredentialCatalogs()->find('T-1'));
    }

    /**
     * A second module: one registration, which knows nothing of the tickets.
     */
    private function registrations(): CredentialCatalog
    {
        return new class implements CredentialCatalog {
            #[Override]
            public function forCheckpoint(CheckpointId $checkpoint): iterable
            {
                yield new CatalogEntry(CredentialCode::of('REG-7-0123456789'), Credential::of('registration:7', 'Registration 7'));
            }

            #[Override]
            public function find(string $credentialId): ?Credential
            {
                return 'registration:7' === $credentialId ? Credential::of('registration:7', 'Registration 7') : null;
            }
        };
    }
}
