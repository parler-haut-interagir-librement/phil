<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\LtiBundle\Application\Contract\KeySealer;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The keys of the application in a table, each private key sealed by the key management service as the JSON of its
 * private JSON Web Key:
 *
 * - the signing key is the newest key not retired; a first one is made when the table holds none;
 * - a rotation makes a new key and retires the ones before, which stay published for the overlap, so that a token
 *   signed just before the rotation still verifies; then they leave the key set, and `prune()` removes them;
 * - the keys opened are kept until `reset()`, which a worker calls between requests.
 */
final class DbalKeyStore implements KeyStore, ResetInterface
{
    private ?RsaPrivateKey $signing = null;

    private ?KeySet $published = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly LtiTables $tables,
        private readonly KeySealer $sealer,
        private readonly ClockInterface $clock,
        private readonly int $overlap = 604_800,
        private readonly int $bits = RsaPrivateKey::DEFAULT_BITS,
    ) {
    }

    #[Override]
    public function signingKey(): RsaPrivateKey
    {
        if ($this->signing instanceof RsaPrivateKey) {
            return $this->signing;
        }

        $row = $this->connection->fetchAssociative(\sprintf('SELECT kid, sealed FROM %s WHERE retired_at IS NULL ORDER BY created_at DESC, kid DESC', $this->tables->keys));

        if (false === $row) {
            return $this->signing = $this->rotate();
        }

        $kid = Row::string($row, 'kid');

        return $this->signing = RsaPrivateKey::fromJson($this->sealer->open($kid, Row::string($row, 'sealed')), $kid);
    }

    #[Override]
    public function publicKeys(): KeySet
    {
        if ($this->published instanceof KeySet) {
            return $this->published;
        }

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT kid, sealed FROM %s WHERE retired_at IS NULL OR retired_at > ? ORDER BY created_at DESC, kid DESC', $this->tables->keys), [$this->clock->now()->getTimestamp() - $this->overlap]);

        if ([] === $rows) {
            $this->signingKey();

            return $this->publicKeys();
        }

        $keys = [];

        foreach (\array_slice($rows, 0, KeySet::MAX_KEYS) as $row) {
            $kid = Row::string($row, 'kid');
            $keys[] = RsaPrivateKey::fromJson($this->sealer->open($kid, Row::string($row, 'sealed')), $kid)->publicKey;
        }

        return $this->published = new KeySet($keys);
    }

    /**
     * Makes a new signing key and retires the others.
     */
    public function rotate(): RsaPrivateKey
    {
        $key = RsaPrivateKey::generate($this->bits, sodium_bin2hex(random_bytes(8)));
        $now = $this->clock->now()->getTimestamp();

        $this->connection->transactional(function (Connection $connection) use ($key, $now): void {
            $connection->executeStatement(\sprintf('UPDATE %s SET retired_at = ? WHERE retired_at IS NULL', $this->tables->keys), [$now]);
            $connection->insert($this->tables->keys, ['kid' => $key->keyId(), 'sealed' => $this->sealer->seal($key->keyId(), $key->toJson()), 'created_at' => $now, 'retired_at' => null]);
        });
        $this->reset();

        return $key;
    }

    /**
     * Whether the signing key is older than this many seconds, or missing.
     */
    public function isDue(int $lifetime): bool
    {
        $created = $this->connection->fetchOne(\sprintf('SELECT MAX(created_at) FROM %s WHERE retired_at IS NULL', $this->tables->keys));

        return !is_numeric($created) || (int) $created <= $this->clock->now()->getTimestamp() - $lifetime;
    }

    /**
     * Removes the keys retired before the overlap; the count removed.
     */
    public function prune(): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE retired_at IS NOT NULL AND retired_at <= ?', $this->tables->keys), [$this->clock->now()->getTimestamp() - $this->overlap]);
    }

    #[Override]
    public function reset(): void
    {
        $this->signing = null;
        $this->published = null;
    }
}
