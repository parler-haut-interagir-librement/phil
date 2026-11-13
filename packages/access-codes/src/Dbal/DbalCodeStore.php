<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\BatchCounts;
use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\CodeState;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Throwable;
use UnexpectedValueException;

/**
 * The batches and the hashes of their codes in two tables of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB
 * or SQLite; instants kept as Unix timestamps.
 *
 * A batch is added in one transaction, its codes inserted 500 rows per statement. A redemption is one conditional
 * `UPDATE` of the code's row — redeemed only where it is neither redeemed, revoked nor expired —, the atomic step:
 * the database locks the row, so that of two redemptions at the same instant one changes it and the other changes
 * nothing. A revocation marks the batch and its unredeemed codes in one transaction.
 */
final readonly class DbalCodeStore implements CodeStore
{
    public const string BATCH_TABLE = 'phil_access_code_batch';

    public const string CODE_TABLE = 'phil_access_code';

    /** The rows of one insert statement. */
    public const int ROWS_PER_INSERT = 500;

    /** @var non-empty-string */
    private string $batchTable;

    /** @var non-empty-string */
    private string $codeTable;

    public function __construct(
        private Connection $connection,
        string $batchTable = self::BATCH_TABLE,
        string $codeTable = self::CODE_TABLE,
    ) {
        $this->batchTable = $this->tableName($batchTable);
        $this->codeTable = $this->tableName($codeTable);
    }

    /**
     * Adds both tables to a schema that lacks them: the one a migration builds, or the one the ORM generates, from a
     * listener of its `postGenerateSchema` event.
     */
    public function configureSchema(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable($this->batchTable)) {
            $editor->addTable($this->batchTableDefinition());
        }

        if (!$schema->hasTable($this->codeTable)) {
            $editor->addTable($this->codeTableDefinition());
        }

        return $editor->create();
    }

    #[Override]
    public function add(Batch $batch, array $hashes): void
    {
        if (\count($hashes) !== $batch->size) {
            throw InvalidConfiguration::because('A batch is stored with one distinct hash per code.');
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->insert($this->batchTable, [
                'id' => $batch->id,
                'name' => $batch->name,
                'size' => $batch->size,
                'created_at' => $batch->createdAt->getTimestamp(),
                'expires_at' => $batch->expiresAt?->getTimestamp(),
            ], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, $batch->expiresAt instanceof DateTimeImmutable ? ParameterType::INTEGER : ParameterType::NULL]);

            foreach (array_chunk($hashes, self::ROWS_PER_INSERT) as $chunk) {
                $parameters = [];
                $types = [];

                foreach ($chunk as $hash) {
                    $parameters[] = $hash;
                    $parameters[] = $batch->id;
                    $parameters[] = $batch->expiresAt?->getTimestamp();
                    $types[] = ParameterType::STRING;
                    $types[] = ParameterType::STRING;
                    $types[] = $batch->expiresAt instanceof DateTimeImmutable ? ParameterType::INTEGER : ParameterType::NULL;
                }

                $this->connection->executeStatement(
                    \sprintf('INSERT INTO %s (hash, batch_id, expires_at) VALUES %s', $this->codeTable, implode(', ', array_fill(0, \count($chunk), '(?, ?, ?)'))),
                    $parameters,
                    $types,
                );
            }

            $this->connection->commit();
        } catch (UniqueConstraintViolationException $exception) {
            $this->connection->rollBack();

            throw InvalidConfiguration::because(\sprintf('The batch "%s" or one of its codes is already stored: %s', $batch->id, $exception->getMessage()));
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function redeem(array $candidates, string $redeemer, DateTimeImmutable $now): ?string
    {
        $at = $now->getTimestamp();
        $changed = $this->connection->executeStatement(
            \sprintf('UPDATE %s SET redeemed_at = ?, redeemed_by = ? WHERE hash IN (?) AND redeemed_at IS NULL AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > ?)', $this->codeTable),
            [$at, $redeemer, $candidates, $at],
            [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::STRING, ParameterType::INTEGER],
        );

        return 1 > $changed ? null : $this->find($candidates)?->batchId;
    }

    #[Override]
    public function find(array $candidates): ?CodeState
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT batch_id, expires_at, redeemed_at, redeemed_by, revoked_at FROM %s WHERE hash IN (?)', $this->codeTable),
            [$candidates],
            [ArrayParameterType::STRING],
        );

        if (false === $row) {
            return null;
        }

        return new CodeState(
            $this->text($row['batch_id']),
            $this->instant($row['expires_at']),
            $this->instant($row['redeemed_at']),
            null === $row['redeemed_by'] ? null : $this->text($row['redeemed_by']),
            $this->instant($row['revoked_at']),
        );
    }

    #[Override]
    public function batch(string $batchId): ?Batch
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT id, name, size, created_at, expires_at, revoked_at FROM %s WHERE id = ?', $this->batchTable),
            [$batchId],
            [ParameterType::STRING],
        );

        if (false === $row) {
            return null;
        }

        return new Batch(
            $this->text($row['id']),
            $this->text($row['name']),
            $this->integer($row['size']),
            $this->instant($row['created_at']) ?? throw new UnexpectedValueException('A batch has no creation instant.'),
            $this->instant($row['expires_at']),
            $this->instant($row['revoked_at']),
        );
    }

    #[Override]
    public function revoke(string $batchId, DateTimeImmutable $now): int
    {
        $at = $now->getTimestamp();
        $this->connection->beginTransaction();

        try {
            if (1 > $this->connection->executeStatement(
                \sprintf('UPDATE %s SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', $this->batchTable),
                [$at, $batchId],
                [ParameterType::INTEGER, ParameterType::STRING],
            )) {
                $this->connection->rollBack();

                return 0;
            }

            $revoked = $this->connection->executeStatement(
                \sprintf('UPDATE %s SET revoked_at = ? WHERE batch_id = ? AND redeemed_at IS NULL AND revoked_at IS NULL', $this->codeTable),
                [$at, $batchId],
                [ParameterType::INTEGER, ParameterType::STRING],
            );
            $this->connection->commit();

            return $this->integer($revoked);
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function counts(string $batchId): BatchCounts
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT COUNT(*) AS total, COUNT(redeemed_at) AS redeemed, COUNT(revoked_at) AS revoked FROM %s WHERE batch_id = ?', $this->codeTable),
            [$batchId],
            [ParameterType::STRING],
        );

        if (false === $row) {
            throw new UnexpectedValueException('The count of a batch returned no row.');
        }

        return new BatchCounts($this->integer($row['total']), $this->integer($row['redeemed']), $this->integer($row['revoked']));
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : new DateTimeImmutable('@'.$this->integer($value));
    }

    private function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A column of the codes is not a number.');
        }

        return (int) $value;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A column of the codes is not text.');
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private function tableName(string $name): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name, $matches)) {
            throw InvalidConfiguration::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $name));
        }

        return $matches[0];
    }

    /**
     * One row per batch: its identifier, name, size, creation, expiry and revocation.
     */
    private function batchTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->batchTable)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(Batch::MAX_NAME_LENGTH)->create(),
                Column::editor()->setUnquotedName('size')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('revoked_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->create();
    }

    /**
     * One row per code: its keyed hash, its batch indexed for the counters and the revocation, its expiry copied from
     * the batch so that a redemption reads one row, and when and by whom it was redeemed or when it was revoked.
     */
    private function codeTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->codeTable)
            ->setColumns(
                Column::editor()->setUnquotedName('hash')->setTypeName(Types::STRING)->setLength(CodeHasher::LENGTH)->create(),
                Column::editor()->setUnquotedName('batch_id')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('redeemed_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('redeemed_by')->setTypeName(Types::STRING)->setLength(191)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('revoked_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('hash')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->codeTable.'_batch_id')->setUnquotedColumnNames('batch_id')->create())
            ->create();
    }
}
