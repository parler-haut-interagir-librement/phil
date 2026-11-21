<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentHistory;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * The register of confidentiality incidents in `phil_privacy_incident`, through DBAL, every date in UTC.
 *
 * A line saved again keeps what each changed field held in `phil_privacy_incident_revision`, in the same transaction:
 * a correction never loses what the register said before.
 *
 * It answers for the store of the same name in a retention schedule: a line on it removes the closed incidents known
 * since before the cutoff, never one still open, and never one known for less than `Incident::MINIMUM_RETENTION`
 * (section 8 of the Québec regulation), whatever the cutoff of the rule.
 */
final readonly class DbalIncidentRegister implements IncidentRegister, IncidentHistory, RetentionPurger
{
    private const string COLUMNS = 'id, summary, discovered_at, occurred_at, categories, affected, serious_risk, measures, authority_notified_at, subjects_notified_at, closed_at, information, undescribed_reason, occurred_until, occurrence_approximate, discovered_until, affected_approximate, risk_assessment, public_notice_at, public_notice_reason';

    /**
     * How many lines a purge removes per statement, under the parameter limits of every platform.
     */
    private const int PURGE_BATCH = 500;

    private ClockInterface $clock;

    public function __construct(
        private Connection $connection,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    #[Override]
    public function save(Incident $incident): void
    {
        $this->connection->transactional(function () use ($incident): void {
            $values = $this->values($incident);
            $types = [
                'serious_risk' => ParameterType::BOOLEAN,
                'occurrence_approximate' => ParameterType::BOOLEAN,
                'affected_approximate' => ParameterType::BOOLEAN,
                'affected' => null === $incident->affected ? ParameterType::NULL : ParameterType::INTEGER,
            ];
            $before = $this->find($incident->id);

            if (!$before instanceof Incident) {
                $this->connection->insert(PrivacyTables::INCIDENTS, ['id' => $incident->id] + $values, $types);

                return;
            }

            $previous = $this->fields($before);
            $next = $this->fields($incident);
            $changed = array_keys(array_filter($previous, static fn (?string $value, string $field): bool => $value !== $next[$field], \ARRAY_FILTER_USE_BOTH));

            // A save that changes nothing writes nothing, and records no correction.
            if ([] !== $changed) {
                $this->connection->update(PrivacyTables::INCIDENTS, $values, ['id' => $incident->id], $types);
                $this->revise($incident->id, $changed, $previous);
            }
        });
    }

    /**
     * @param list<string>               $changed
     * @param array<string, string|null> $previous
     */
    private function revise(string $id, array $changed, array $previous): void
    {
        $last = $this->connection->fetchOne('SELECT MAX(sequence) FROM '.PrivacyTables::INCIDENT_REVISIONS.' WHERE incident_id = ?', [$id]);
        $sequence = (is_numeric($last) ? (int) $last : 0) + 1;
        $revisedAt = $this->text($this->clock->now());

        foreach ($changed as $field) {
            $this->connection->insert(PrivacyTables::INCIDENT_REVISIONS, [
                'incident_id' => $id,
                'sequence' => $sequence,
                'field' => $field,
                'previous' => $previous[$field],
                'revised_at' => $revisedAt,
            ], ['sequence' => ParameterType::INTEGER]);
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
    public function revisions(string $id): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT incident_id, sequence, field, previous, revised_at FROM '.PrivacyTables::INCIDENT_REVISIONS.' WHERE incident_id = ? ORDER BY sequence, field',
            [$id],
        );

        return array_map(fn (array $row): IncidentRevision => new IncidentRevision(
            $this->string($row, 'incident_id'),
            is_numeric($row['sequence']) ? (int) $row['sequence'] : 0,
            $this->string($row, 'field'),
            \is_string($row['previous']) ? $row['previous'] : null,
            UtcMoments::read($this->connection, $row['revised_at'], 'revised_at'),
        ), $rows);
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::INCIDENTS;
    }

    /**
     * Removes the closed incidents known since before the cutoff of the order, and their corrections; a cutoff later
     * than the floor of section 8 gives way to the floor.
     */
    #[Override]
    public function purge(PurgeOrder $order): int
    {
        $floor = RetentionPeriod::fixed(Incident::MINIMUM_RETENTION)->cutoff($this->clock->now());
        $limit = $this->text($floor instanceof DateTimeImmutable && $floor->getTimestamp() < $order->cutoff->getTimestamp() ? $floor : $order->cutoff);
        $ids = array_values(array_filter(
            $this->connection->fetchFirstColumn(
                'SELECT id FROM '.PrivacyTables::INCIDENTS.' WHERE closed_at IS NOT NULL AND discovered_at < ? AND (discovered_until IS NULL OR discovered_until < ?)',
                [$limit, $limit],
            ),
            \is_string(...),
        ));
        $purged = 0;

        foreach (array_chunk($ids, self::PURGE_BATCH) as $batch) {
            $purged += $this->connection->transactional(function () use ($batch): int {
                $this->connection->executeStatement('DELETE FROM '.PrivacyTables::INCIDENT_REVISIONS.' WHERE incident_id IN (?)', [$batch], [ArrayParameterType::STRING]);

                return (int) $this->connection->executeStatement('DELETE FROM '.PrivacyTables::INCIDENTS.' WHERE id IN (?)', [$batch], [ArrayParameterType::STRING]);
            });
        }

        return $purged;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function values(Incident $incident): array
    {
        return [
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
            'information' => $incident->information,
            'undescribed_reason' => $incident->undescribedReason,
            'occurred_until' => $this->optional($incident->occurredUntil),
            'occurrence_approximate' => $incident->occurrenceApproximate,
            'discovered_until' => $this->optional($incident->discoveredUntil),
            'affected_approximate' => $incident->affectedApproximate,
            'risk_assessment' => $incident->riskAssessment,
            'public_notice_at' => $this->optional($incident->publicNoticeAt),
            'public_notice_reason' => $incident->publicNoticeReason?->value,
        ];
    }

    /**
     * Each field as the text a correction keeps: instants in UTC, flags as `1` or `0`, null for what is not known.
     *
     * @return array<string, string|null>
     */
    private function fields(Incident $incident): array
    {
        return array_map(static fn (bool|int|string|null $value): ?string => match (true) {
            true === $value => '1',
            false === $value => '0',
            \is_int($value) => (string) $value,
            default => $value,
        }, $this->values($incident));
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

        $reason = $this->string($row, 'public_notice_reason');

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
            $this->string($row, 'information'),
            $this->string($row, 'undescribed_reason'),
            UtcMoments::readOptional($this->connection, $row['occurred_until'], 'occurred_until'),
            (bool) $row['occurrence_approximate'],
            UtcMoments::readOptional($this->connection, $row['discovered_until'], 'discovered_until'),
            (bool) $row['affected_approximate'],
            $this->string($row, 'risk_assessment'),
            UtcMoments::readOptional($this->connection, $row['public_notice_at'], 'public_notice_at'),
            '' === $reason ? null : PublicNoticeReason::from($reason),
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
