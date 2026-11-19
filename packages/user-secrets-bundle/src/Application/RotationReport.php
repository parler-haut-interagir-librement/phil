<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application;

use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * What a key rotation did: the secrets sealed again, those another process changed between the read and the write and
 * that were left as it made them, and those whose value did not open, by owner and name, left under their key.
 */
final readonly class RotationReport
{
    /**
     * @param list<array{owner: Owner, name: SecretName}> $unreadable
     */
    public function __construct(
        public int $resealed,
        public int $skipped,
        public array $unreadable,
    ) {
    }
}
