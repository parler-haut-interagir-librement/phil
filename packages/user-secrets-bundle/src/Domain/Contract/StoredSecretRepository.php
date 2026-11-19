<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Contract;

use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * The store of the secrets' rows. Every lookup but the key rotation's names the owner: there is no way to read a
 * secret without knowing whose it is.
 */
interface StoredSecretRepository
{
    public function get(Owner $owner, SecretName $name): ?StoredSecret;

    /**
     * @return list<StoredSecret> the owner's secrets, revoked ones included, by name
     */
    public function ofOwner(Owner $owner): array;

    /**
     * @return list<StoredSecret> at most `$limit` secrets not revoked and sealed under another key than `$keyId`, or in
     *                            another format than `$format`, by owner and name
     */
    public function toReseal(string $keyId, SealedFormat $format, int $limit): array;

    /**
     * Reads the row again from where it is kept, as another process may have changed it since it was read.
     */
    public function fresh(Owner $owner, SecretName $name): ?StoredSecret;

    /**
     * Saves the secret only when its row still holds `$previous`, not revoked: a compare-and-swap on the sealed value,
     * which changes at every seal. When it does not, the row is left as another process made it, and the secret given
     * is no longer to be saved: read the row again with {@see self::fresh()}.
     *
     * @return bool whether the secret was saved
     */
    public function replace(StoredSecret $secret, SealedSecret $previous): bool;

    public function save(StoredSecret ...$secrets): void;

    public function remove(StoredSecret ...$secrets): void;
}
