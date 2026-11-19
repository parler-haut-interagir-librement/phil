<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * The secrets in an array, for tests and for applications without a database. They last as long as the process.
 *
 * The rows are the objects saved; the sealed value of each row is also kept as it was saved, for the compare-and-swap
 * of {@see self::replace()}, since the object in hand may already hold the next one.
 */
final class InMemoryStoredSecretRepository implements StoredSecretRepository
{
    /**
     * @var array<string, array<string, StoredSecret>> by owner, then name
     */
    private array $secrets = [];

    /**
     * @var array<string, array<string, string>> the sealed value of each row as it was saved, empty when revoked
     */
    private array $saved = [];

    #[Override]
    public function get(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->secrets[$owner->value][$name->value] ?? null;
    }

    #[Override]
    public function ofOwner(Owner $owner): array
    {
        $secrets = $this->secrets[$owner->value] ?? [];
        ksort($secrets, \SORT_STRING);

        return array_values($secrets);
    }

    #[Override]
    public function toReseal(string $keyId, SealedFormat $format, int $limit, ?StoredSecret $after = null): array
    {
        $found = array_filter(
            $this->allToReseal($keyId, $format),
            static fn (StoredSecret $secret): bool => !$after instanceof StoredSecret || self::compare($secret, $after) > 0,
        );

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function countToReseal(string $keyId, SealedFormat $format): array
    {
        $counts = [];

        foreach ($this->allToReseal($keyId, $format) as $secret) {
            $counts[$secret->sealed()->keyId] = ($counts[$secret->sealed()->keyId] ?? 0) + 1;
        }

        ksort($counts, \SORT_STRING);

        return $counts;
    }

    #[Override]
    public function fresh(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->get($owner, $name);
    }

    #[Override]
    public function replace(StoredSecret $secret, SealedSecret $previous): bool
    {
        if ('' === $previous->ciphertext || ($this->saved[$secret->owner()->value][$secret->name()->value] ?? null) !== $previous->ciphertext) {
            return false;
        }

        $this->save($secret);

        return true;
    }

    #[Override]
    public function save(StoredSecret ...$secrets): void
    {
        foreach ($secrets as $secret) {
            $this->secrets[$secret->owner()->value][$secret->name()->value] = $secret;
            $this->saved[$secret->owner()->value][$secret->name()->value] = $secret->revokedAt() instanceof DateTimeImmutable ? '' : $secret->sealed()->ciphertext;
        }
    }

    #[Override]
    public function remove(StoredSecret ...$secrets): void
    {
        foreach ($secrets as $secret) {
            unset($this->secrets[$secret->owner()->value][$secret->name()->value], $this->saved[$secret->owner()->value][$secret->name()->value]);
        }
    }

    /**
     * @return list<StoredSecret> every secret not revoked and sealed under another key or in another format, by owner
     *                            and name
     */
    private function allToReseal(string $keyId, SealedFormat $format): array
    {
        $found = [];

        foreach ($this->secrets as $secrets) {
            foreach ($secrets as $secret) {
                if (!$secret->revokedAt() instanceof DateTimeImmutable && ($secret->sealed()->keyId !== $keyId || $secret->sealed()->format() !== $format)) {
                    $found[] = $secret;
                }
            }
        }

        usort($found, self::compare(...));

        return $found;
    }

    private static function compare(StoredSecret $a, StoredSecret $b): int
    {
        $byOwner = strcmp($a->owner()->value, $b->owner()->value);

        return 0 !== $byOwner ? $byOwner : strcmp($a->name()->value, $b->name()->value);
    }
}
