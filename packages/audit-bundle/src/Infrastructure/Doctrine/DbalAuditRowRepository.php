<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Exception\ChainConflict;

/**
 * The trail in a table of a Doctrine connection: rows are inserted, read, and never updated nor deleted here.
 */
final readonly class DbalAuditRowRepository implements AuditRowRepository
{
    /** Every column of a row, in the order {@see self::rowOf()} reads them. */
    public const string COLUMNS = 'segment, sequence, kind, recorded_at, serialization_version, payload, previous_hash, hash, action, actor, subject_type, subject_id, tenant';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function first(string $segment): ?AuditRow
    {
        return $this->edge($segment, 'ASC');
    }

    #[Override]
    public function head(string $segment): ?AuditRow
    {
        return $this->edge($segment, 'DESC');
    }

    #[Override]
    public function append(AuditRow $row): void
    {
        try {
            $this->connection->insert(AuditTable::NAME, [
                'segment' => $row->segment,
                'sequence' => $row->sequence,
                'kind' => $row->kind->value,
                'recorded_at' => $row->instant(),
                'serialization_version' => $row->version,
                'payload' => $row->payload,
                'previous_hash' => $row->previousHash,
                'hash' => $row->hash,
                'action' => $row->facts->action,
                'actor' => $row->facts->actor,
                'subject_type' => $row->facts->subject?->type,
                'subject_id' => $row->facts->subject?->id,
                'tenant' => $row->facts->tenant,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ChainConflict::at($row->segment, $row->sequence);
        }
    }

    #[Override]
    public function rows(string $segment): iterable
    {
        foreach ($this->connection->iterateAssociative(\sprintf('SELECT %s FROM %s WHERE segment = ? ORDER BY sequence', self::COLUMNS, AuditTable::NAME), [$segment]) as $row) {
            yield self::rowOf($row);
        }
    }

    #[Override]
    public function rowsAfter(string $segment, int $sequence, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE segment = ? AND sequence > ? ORDER BY sequence LIMIT %d', self::COLUMNS, AuditTable::NAME, $limit),
            [$segment, $sequence],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return array_map(self::rowOf(...), $rows);
    }

    #[Override]
    public function segments(): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(\sprintf('SELECT DISTINCT segment FROM %s ORDER BY segment', AuditTable::NAME));
    }

    /**
     * One end of the segment, read on the `(segment, sequence)` key.
     *
     * @param 'ASC'|'DESC' $direction
     */
    private function edge(string $segment, string $direction): ?AuditRow
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE segment = ? ORDER BY sequence %s LIMIT 1', self::COLUMNS, AuditTable::NAME, $direction), [$segment]);

        return false === $row ? null : self::rowOf($row);
    }

    /**
     * A row as the table holds it; its facts are taken as stored, and verification tells whether they were changed.
     *
     * @param array<string, mixed> $row
     */
    public static function rowOf(array $row): AuditRow
    {
        return AuditRow::restore(
            self::text($row['segment']),
            (int) self::text($row['sequence']),
            RowKind::from(self::text($row['kind'])),
            new DateTimeImmutable(self::text($row['recorded_at'])),
            self::text($row['serialization_version']),
            self::text($row['payload']),
            self::text($row['previous_hash']),
            self::text($row['hash']),
            self::factsOf($row),
        );
    }

    /**
     * The facts as stored. A value the table holds but `Facts` refuses — written around the bundle — is kept as is:
     * the row then fails verification, which says so, rather than the read.
     *
     * @param array<string, mixed> $row
     */
    private static function factsOf(array $row): Facts
    {
        $action = self::optional($row['action'] ?? null);
        $actor = self::optional($row['actor'] ?? null);
        $type = self::optional($row['subject_type'] ?? null);
        $id = self::optional($row['subject_id'] ?? null);
        $tenant = self::optional($row['tenant'] ?? null);

        return Facts::restore($action, $actor, null === $type || null === $id ? null : Subject::restore($type, $id), $tenant);
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function optional(mixed $value): ?string
    {
        return null === $value ? null : self::text($value);
    }
}
