<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;

/**
 * A store that hands out copies and keeps copies, as a database does: a change that is not saved is lost.
 */
final readonly class DetachedRepository implements StoredSecretRepository
{
    private InMemoryStoredSecretRepository $rows;

    public function __construct()
    {
        $this->rows = new InMemoryStoredSecretRepository();
    }

    #[Override]
    public function get(Owner $owner, SecretName $name): ?StoredSecret
    {
        $secret = $this->rows->get($owner, $name);

        return $secret instanceof StoredSecret ? clone $secret : null;
    }

    #[Override]
    public function ofOwner(Owner $owner): array
    {
        return $this->copies($this->rows->ofOwner($owner));
    }

    #[Override]
    public function toReseal(string $keyId, SealedFormat $format, int $limit, ?StoredSecret $after = null): array
    {
        return $this->copies($this->rows->toReseal($keyId, $format, $limit, $after));
    }

    #[Override]
    public function countToReseal(string $keyId, SealedFormat $format): array
    {
        return $this->rows->countToReseal($keyId, $format);
    }

    #[Override]
    public function fresh(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->get($owner, $name);
    }

    #[Override]
    public function replace(StoredSecret $secret, SealedSecret $previous): bool
    {
        return $this->rows->replace(clone $secret, $previous);
    }

    #[Override]
    public function save(StoredSecret ...$secrets): void
    {
        $this->rows->save(...$this->copies(array_values($secrets)));
    }

    #[Override]
    public function remove(StoredSecret ...$secrets): void
    {
        $this->rows->remove(...$secrets);
    }

    /**
     * @param list<StoredSecret> $secrets
     *
     * @return list<StoredSecret>
     */
    private function copies(array $secrets): array
    {
        return array_map(static fn (StoredSecret $secret): StoredSecret => clone $secret, $secrets);
    }
}
