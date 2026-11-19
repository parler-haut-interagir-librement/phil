<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Audit;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * One thing that happened to one secret: what, whose, which, when, and for a read, the purpose the task gave. It
 * never carries the value.
 */
final readonly class SecretEvent
{
    public function __construct(
        public SecretEventKind $kind,
        public Owner $owner,
        public SecretName $name,
        public DateTimeImmutable $at,
        public ?string $purpose = null,
    ) {
    }
}
