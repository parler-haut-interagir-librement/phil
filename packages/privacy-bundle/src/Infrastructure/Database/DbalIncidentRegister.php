<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * The register of confidentiality incidents in `phil_privacy_incident`, through DBAL, every date in UTC.
 *
 * It answers for the store of the same name in a retention schedule: a line on it removes the closed incidents
 * discovered before the cutoff, never one still open. The law asks for at least five years from discovery.
 */
final readonly class DbalIncidentRegister implements IncidentRegister, RetentionPurger
{
    private const string COLUMNS = 'id, summary, discovered_at, occurred_at, categories, affected, serious_risk, measures, authority_notified_at, subjects_notified_at, closed_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Incident $incident): void
    {
        $values = [
            'summary' => $incident->summary,
            'discovered_at' => $this->text($incident->discoveredAt),
            'occurred_at' => $this->optional($incident->occurredAt),
            'categories' => json_encode(array_map(static fn (DataCategory $category): string => $category->value, $incident->categories), \JSON_THROW_ON_ERROR),
            'affected' => $incident->affected,
            'serious_risk' => $incident->seriousRisk,
            'measures' => $incident->measures,
            'authority_notified_at' => $this->optional($incident->authorityNotifiedAt),
            'subjects_notified_at' => $this->optional($incident->subjectsNotifiedAt),
            'closed_at' => $this->optional($incident->closedAt),
        ];
        $types = ['serious_risk' => ParameterType::BOOLEAN, 'affected' => null === $incident->affected ? ParameterType::NULL : ParameterType::INTEGER];

        if (false === $this->connection->fetchOne('SELECT 1 FROM '.PrivacyTables::INCIDENTS.' WHERE id = ?', [$incident->id])) {
            $this->connection->insert(PrivacyTables::INCIDENTS, ['id' => $incident->id] + $values, $types);
        } else {
            $this->connection->update(PrivacyTables::INCIDENTS, $values, ['id' => $incident->id], $types);
        }
    }

    #[Override]
    public function find(string $id): ?Incident
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.PrivacyTables::INCIDENTS.' WHERE id = ?', [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function all(): array
    {
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative('SELECT '.self::COLUMNS.' FROM '.PrivacyTables::INCIDENTS.' ORDER BY discovered_at DESC, id'));
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::INCIDENTS;
    }

    #[Override]
    public function purge(PurgeOrder $order): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM '.PrivacyTables::INCIDENTS.' WHERE closed_at IS NOT NULL AND discovered_at < ?',
            [$this->text($order->cutoff)],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Incident
    {
        $decoded = json_decode($this->string($row, 'categories'), true, 2, \JSON_THROW_ON_ERROR);
        $categories = [];

        foreach (\is_array($decoded) ? $decoded : [] as $category) {
            $categories[] = DataCategory::fromString(\is_string($category) ? $category : '');
        }

        return new Incident(
            $this->string($row, 'id'),
            $this->string($row, 'summary'),
            UtcMoments::read($this->connection, $row['discovered_at'], 'discovered_at'),
            UtcMoments::readOptional($this->connection, $row['occurred_at'], 'occurred_at'),
            $categories,
            is_numeric($row['affected']) ? (int) $row['affected'] : null,
            (bool) $row['serious_risk'],
            $this->string($row, 'measures'),
            UtcMoments::readOptional($this->connection, $row['authority_notified_at'], 'authority_notified_at'),
            UtcMoments::readOptional($this->connection, $row['subjects_notified_at'], 'subjects_notified_at'),
            UtcMoments::readOptional($this->connection, $row['closed_at'], 'closed_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $column): string
    {
        return \is_string($row[$column]) ? $row[$column] : '';
    }

    private function optional(?DateTimeImmutable $moment): ?string
    {
        return UtcMoments::optionalText($this->connection, $moment);
    }

    private function text(DateTimeImmutable $moment): string
    {
        return UtcMoments::text($this->connection, $moment);
    }
}
