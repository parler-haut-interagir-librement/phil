<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit\Fixture;

use Override;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;

/**
 * Two tickets, `CODE-1` for `T-1` and `CODE-2` for `T-2`, both a resolver and a catalog.
 */
final class FixedCredentials implements CredentialResolver, CredentialCatalog
{
    /** @var array<string, Credential> */
    private array $byCode;

    public function __construct()
    {
        $this->byCode = ['CODE-1' => Credential::of('T-1', 'Élise'), 'CODE-2' => Credential::of('T-2', maxEntries: null)];
    }

    #[Override]
    public function resolve(CredentialCode $code): ?Credential
    {
        return $this->byCode[$code->value] ?? null;
    }

    #[Override]
    public function forCheckpoint(CheckpointId $checkpoint): iterable
    {
        foreach ($this->byCode as $code => $credential) {
            yield new CatalogEntry(CredentialCode::of($code), $credential);
        }
    }

    #[Override]
    public function find(string $credentialId): ?Credential
    {
        foreach ($this->byCode as $credential) {
            if ($credential->id === $credentialId) {
                return $credential;
            }
        }

        return null;
    }
}
