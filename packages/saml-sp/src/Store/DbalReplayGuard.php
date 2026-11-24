<?php

declare(strict_types=1);

namespace Phil\SamlSp\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Override;
use Phil\SamlSp\Contract\ReplayGuard;
use Psr\Clock\ClockInterface;

/**
 * Remembers the assertions in a table of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite: one row
 * per assertion, keyed by the SHA-256 of its issuer and its ID, with the instant it may be forgotten. The primary key
 * makes the first use one atomic `INSERT`: of two servers that receive the same assertion at the same moment, one
 * inserts the row and the other is refused by the database, with no read before the write.
 *
 * Before the insert, the row of the same assertion is deleted when it has expired, so an assertion past its expiry
 * takes no place; `purge()` deletes every expired row, from a scheduled task. On PostgreSQL the insert is
 * `ON CONFLICT DO NOTHING`, which leaves an open transaction usable; elsewhere a unique violation is the refusal.
 *
 * The key column is 64 hexadecimal characters, compared byte by byte (`ascii_bin` on MySQL and MariaDB); the expiry is
 * a `datetime_utc_immutable`, written and read in UTC whatever the time zone of the instant given.
 */
final readonly class DbalReplayGuard implements ReplayGuard
{
    public const string TABLE = 'phil_saml_used_assertion';

    /** The length of the key: a SHA-256 in hexadecimal. */
    public const int KEY_LENGTH = 64;

    /** @var non-empty-string */
    private string $table;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        string $table = self::TABLE,
    ) {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $table, $matches)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table));
        }

        $this->table = $matches[0];
    }

    #[Override]
    public function firstUse(string $issuer, string $assertionId, DateTimeImmutable $until): bool
    {
        $key = self::key($issuer, $assertionId);

        // An expired row takes no place: once it is gone, the insert decides alone.
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE assertion = ? AND expires_at <= ?', $this->table),
            [$key, $this->clock->now()],
            [Types::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );

        try {
            return 0 < $this->connection->executeStatement(
                $this->insertion($this->connection->getDatabasePlatform()),
                [$key, $until],
                [Types::STRING, Types::DATETIME_UTC_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Forgets the assertions expired at `$now`; run it from a scheduled task.
     *
     * @return int how many were forgotten
     */
    public function purge(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->table),
            [$now],
            [Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    /**
     * Adds the table to a schema that lacks it: the one a migration builds, or the one the ORM generates, from a
     * listener of its `postGenerateSchema` event. A schema that has it is returned as is.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        $key = Column::editor()->setUnquotedName('assertion')->setTypeName(Types::STRING)->setLength(self::KEY_LENGTH)->setFixed(true);

        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $key->setCharset('ascii')->setCollation('ascii_bin');
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                $key->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assertion')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->table.'_expires_at')->setUnquotedColumnNames('expires_at')->create())
            ->create())->create();
    }

    /**
     * The key of an assertion: the SHA-256 of the length of the issuer, a colon, the issuer and the ID, so that no two
     * pairs share it.
     */
    public static function key(string $issuer, string $assertionId): string
    {
        return hash('sha256', \strlen($issuer).':'.$issuer.$assertionId);
    }

    /**
     * The insert of a first use on this platform.
     */
    private function insertion(AbstractPlatform $platform): string
    {
        $insert = \sprintf('INSERT INTO %s (assertion, expires_at) VALUES (?, ?)', $this->table);

        return $platform instanceof PostgreSQLPlatform ? $insert.' ON CONFLICT (assertion) DO NOTHING' : $insert;
    }
}
