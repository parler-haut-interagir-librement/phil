<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit\Fixture;

use Override;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;

/**
 * Credentials by code, both a resolver and a catalog.
 */
final class FixedCredentials implements CredentialResolver, CredentialCatalog
{
    /**
     * @param array<string, Credential> $byCode
     */
    public function __construct(
        public array $byCode = [],
    ) {
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
