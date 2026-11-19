<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

/**
 * What the owner sees of a secret in the list of theirs: everything but the value.
 */
final readonly class SecretSummary
{
    public function __construct(
        public SecretName $name,
        public ServiceName $service,
        public CredentialKind $kind,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }

    public static function of(StoredSecret $secret): self
    {
        return new self($secret->name(), $secret->service(), $secret->kind(), $secret->createdAt(), $secret->updatedAt(), $secret->lastUsedAt(), $secret->expiresAt(), $secret->revokedAt());
    }
}
