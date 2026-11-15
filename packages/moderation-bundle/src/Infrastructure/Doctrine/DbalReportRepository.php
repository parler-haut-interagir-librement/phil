<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\QueueItem;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * The reports in `phil_moderation_report`, through DBAL. The queue is one grouped query over the open reports, then
 * one for the reasons and sources of the targets of the page; a row that no longer reads is left out.
 */
final readonly class DbalReportRepository implements ReportRepository
{
    private const string COLUMNS = 'id, target_type, target_id, source, reason, reporter, comment, reported_at, decision_id';

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function add(Report $report): void
    {
        $this->connection->insert(ModerationTables::REPORTS, [
            'id' => $report->id,
            'target_type' => $report->target->type,
            'target_id' => $report->target->id,
            'source' => $report->source->value,
            'reason' => $report->reason,
            'reporter' => $report->reporter?->value,
            'comment' => $report->comment,
            'reported_at' => $report->reportedAt,
            'decision_id' => $report->decisionId,
        ], ['reported_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function openOn(TargetReference $target): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::REPORTS.' WHERE target_type = ? AND target_id = ? AND decision_id IS NULL ORDER BY reported_at, id',
            [$target->type, $target->id],
        ));
    }

    #[Override]
    public function openBy(TargetReference $target, MemberId $reporter): ?Report
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::REPORTS.' WHERE target_type = ? AND target_id = ? AND reporter = ? AND decision_id IS NULL',
            [$target->type, $target->id, $reporter->value],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function queue(int $limit, int $offset = 0, array $types = []): array
    {
        [$where, $parameters, $parameterTypes] = $this->open($types);
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(
            'SELECT target_type, target_id, COUNT(*) AS reports, MIN(reported_at) AS first_at, MAX(reported_at) AS last_at FROM '.ModerationTables::REPORTS
            .' WHERE '.$where.' GROUP BY target_type, target_id ORDER BY first_at, target_type, target_id',
            $limit,
            $offset,
        );
        $groups = $this->connection->fetchAllAssociative($sql, $parameters, $parameterTypes);

        if ([] === $groups) {
            return [];
        }

        $details = $this->details($groups);
        $items = [];

        foreach ($groups as $group) {
            try {
                $target = TargetReference::of($this->rows->required($group, 'target_type'), $this->rows->required($group, 'target_id'));
            } catch (InvalidModeration) {
                continue;
            }

            $key = $target->type."\0".$target->id;
            $items[] = new QueueItem(
                $target,
                $this->rows->number($group, 'reports'),
                $this->rows->moment($group, 'first_at'),
                $this->rows->moment($group, 'last_at'),
                array_values(array_unique($details[$key]['reasons'] ?? [])),
                array_values(array_unique($details[$key]['sources'] ?? [], \SORT_REGULAR)),
            );
        }

        return $items;
    }

    #[Override]
    public function countOpenTargets(array $types = []): int
    {
        [$where, $parameters, $parameterTypes] = $this->open($types);

        return $this->rows->number(['count' => $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (SELECT target_type, target_id FROM '.ModerationTables::REPORTS.' WHERE '.$where.' GROUP BY target_type, target_id) open_targets',
            $parameters,
            $parameterTypes,
        )], 'count');
    }

    #[Override]
    public function close(TargetReference $target, string $decisionId): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE '.ModerationTables::REPORTS.' SET decision_id = ? WHERE target_type = ? AND target_id = ? AND decision_id IS NULL',
            [$decisionId, $target->type, $target->id],
        );
    }

    #[Override]
    public function countSince(DateTimeImmutable $since): int
    {
        return $this->rows->number(['count' => $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ModerationTables::REPORTS.' WHERE reported_at >= ?',
            [$since],
            [Types::DATETIME_UTC_IMMUTABLE],
        )], 'count');
    }

    #[Override]
    public function madeBy(MemberId $reporter): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.ModerationTables::REPORTS.' WHERE reporter = ? ORDER BY reported_at DESC, id DESC',
            [$reporter->value],
        ));
    }

    #[Override]
    public function forgetReporter(MemberId $reporter): int
    {
        return (int) $this->connection->executeStatement('UPDATE '.ModerationTables::REPORTS.' SET reporter = NULL WHERE reporter = ?', [$reporter->value]);
    }

    /**
     * The condition of the open reports of the types given, with its parameters.
     *
     * @param list<string> $types
     *
     * @return array{string, list<mixed>, list<ParameterType|ArrayParameterType>}
     */
    private function open(array $types): array
    {
        return [] === $types
            ? ['decision_id IS NULL', [], []]
            : ['decision_id IS NULL AND target_type IN (?)', [$types], [ArrayParameterType::STRING]];
    }

    /**
     * The reasons and sources of the open reports of the targets, by target.
     *
     * @param list<array<string, mixed>> $groups
     *
     * @return array<string, array{reasons: list<string>, sources: list<ReportSource>}>
     */
    private function details(array $groups): array
    {
        $conditions = [];
        $parameters = [];

        foreach ($groups as $group) {
            $conditions[] = '(target_type = ? AND target_id = ?)';
            $parameters[] = $this->rows->required($group, 'target_type');
            $parameters[] = $this->rows->required($group, 'target_id');
        }

        $details = [];

        foreach ($this->connection->fetchAllAssociative(
            'SELECT target_type, target_id, reason, source FROM '.ModerationTables::REPORTS.' WHERE decision_id IS NULL AND ('.implode(' OR ', $conditions).') ORDER BY reported_at, id',
            $parameters,
        ) as $row) {
            $key = $this->rows->required($row, 'target_type')."\0".$this->rows->required($row, 'target_id');
            $details[$key] ??= ['reasons' => [], 'sources' => []];
            $details[$key]['reasons'][] = $this->rows->required($row, 'reason');
            $source = ReportSource::tryFrom($this->rows->required($row, 'source'));

            if ($source instanceof ReportSource) {
                $details[$key]['sources'][] = $source;
            }
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Report>
     */
    private function hydrateAll(array $rows): array
    {
        return array_values(array_filter(array_map($this->hydrate(...), $rows)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?Report
    {
        $source = ReportSource::tryFrom($this->rows->required($row, 'source'));

        if (!$source instanceof ReportSource) {
            return null;
        }

        try {
            return new Report(
                $this->rows->required($row, 'id'),
                TargetReference::of($this->rows->required($row, 'target_type'), $this->rows->required($row, 'target_id')),
                $source,
                $this->rows->required($row, 'reason'),
                MemberId::orNull($this->rows->text($row, 'reporter')),
                $this->rows->text($row, 'comment'),
                $this->rows->moment($row, 'reported_at'),
                $this->rows->text($row, 'decision_id'),
            );
        } catch (InvalidModeration) {
            return null;
        }
    }
}
