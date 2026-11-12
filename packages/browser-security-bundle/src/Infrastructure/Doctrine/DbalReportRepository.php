<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;
use Phil\BrowserSecurityBundle\Domain\ValueObject\StoredReport;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;

/**
 * The violations in `phil_csp_report`, through DBAL: a report adds one to the row of its fingerprint in a single
 * statement, and creates the row when there is none and the table holds fewer rows than its capacity — the race of
 * two first reports ends in the same update.
 */
final readonly class DbalReportRepository implements ReportRepository
{
    public const int DEFAULT_CAPACITY = 10_000;

    private const string COLUMNS = 'document_uri, blocked_uri, effective_directive, disposition, source_file, line_number, column_number, status_code, sample, client, hits, first_seen_at, last_seen_at';

    public function __construct(
        private Connection $connection,
        private int $capacity = self::DEFAULT_CAPACITY,
    ) {
    }

    #[Override]
    public function record(ViolationReport $report, ?string $client, DateTimeImmutable $at): void
    {
        $fingerprint = $report->fingerprint();

        if ($this->add($fingerprint, $client, $at) || $this->full()) {
            return;
        }

        try {
            $this->connection->insert(ReportTable::NAME, [
                'fingerprint' => $fingerprint,
                'document_uri' => $report->documentUri,
                'blocked_uri' => $report->blockedUri,
                'effective_directive' => $report->effectiveDirective,
                'disposition' => $report->disposition->value,
                'source_file' => $report->sourceFile,
                'line_number' => $report->line,
                'column_number' => $report->column,
                'status_code' => $report->statusCode,
                'sample' => $report->sample,
                'client' => $client,
                'hits' => 1,
                'first_seen_at' => $at,
                'last_seen_at' => $at,
            ], ['first_seen_at' => Types::DATETIME_IMMUTABLE, 'last_seen_at' => Types::DATETIME_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            $this->add($fingerprint, $client, $at);
        }
    }

    #[Override]
    public function latest(int $limit): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT '.self::COLUMNS.' FROM '.ReportTable::NAME.' ORDER BY last_seen_at DESC, id DESC', $limit);

        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative($sql));
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement('DELETE FROM '.ReportTable::NAME.' WHERE last_seen_at < ?', [$before], [Types::DATETIME_IMMUTABLE]);
    }

    private function add(string $fingerprint, ?string $client, DateTimeImmutable $at): bool
    {
        return 0 < $this->connection->executeStatement(
            'UPDATE '.ReportTable::NAME.' SET hits = hits + 1, last_seen_at = ?, client = ? WHERE fingerprint = ?',
            [$at, $client, $fingerprint],
            [Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::STRING],
        );
    }

    private function full(): bool
    {
        return $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM '.ReportTable::NAME)) >= $this->capacity;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StoredReport
    {
        return new StoredReport(
            new ViolationReport(
                $this->text($row['document_uri']) ?? '',
                $this->text($row['blocked_uri']) ?? '',
                $this->text($row['effective_directive']) ?? '',
                Disposition::tryFrom($this->text($row['disposition']) ?? '') ?? Disposition::Enforce,
                $this->text($row['source_file']),
                $this->integer($row['line_number']),
                $this->integer($row['column_number']),
                $this->integer($row['status_code']),
                $this->text($row['sample']),
            ),
            $this->integer($row['hits']) ?? 0,
            $this->moment($row['first_seen_at']),
            $this->moment($row['last_seen_at']),
            $this->text($row['client']),
        );
    }

    /**
     * A date column read back: every platform gives it as text the constructor reads.
     */
    private function moment(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($this->text($value) ?? '');
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
