<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;

/**
 * What a person entrusts for a service: an API key or an OAuth token.
 */
interface Credential
{
    public function kind(): CredentialKind;

    /**
     * When the credential stops working, as far as the bundle knows; null when it does not expire.
     */
    public function expiresAt(): ?DateTimeImmutable;

    /**
     * The credential as the data sealed in the store, its secrets in clear: only the encryption reads it.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
