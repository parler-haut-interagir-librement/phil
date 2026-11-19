<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * A store that keeps copies, as {@see DetachedRepository} does, and runs once what another process does between the
 * key rotation's read of a batch and its writes.
 */
final class MeanwhileRepository implements StoredSecretRepository
{
    public function __construct(
        private readonly DetachedRepository $rows,
        private ?Closure $meanwhile,
    ) {
    }

    #[Override]
    public function get(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->rows->get($owner, $name);
    }

    #[Override]
    public function ofOwner(Owner $owner): array
    {
        return $this->rows->ofOwner($owner);
    }

    #[Override]
    public function toReseal(string $keyId, SealedFormat $format, int $limit, ?StoredSecret $after = null): array
    {
        $batch = $this->rows->toReseal($keyId, $format, $limit, $after);
        $meanwhile = $this->meanwhile;
        // Once only: the other process acts during the first batch.
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $batch;
    }

    #[Override]
    public function countToReseal(string $keyId, SealedFormat $format): array
    {
        return $this->rows->countToReseal($keyId, $format);
    }

    #[Override]
    public function fresh(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->rows->fresh($owner, $name);
    }

    #[Override]
    public function replace(StoredSecret $secret, SealedSecret $previous): bool
    {
        return $this->rows->replace($secret, $previous);
    }

    #[Override]
    public function save(StoredSecret ...$secrets): void
    {
        $this->rows->save(...$secrets);
    }

    #[Override]
    public function remove(StoredSecret ...$secrets): void
    {
        $this->rows->remove(...$secrets);
    }
}
