<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\NewsletterBundle\Domain\Contract\SendRunRepository;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Model\SendRun;

/**
 * The send runs in the table `phil_newsletter_send_run`. A count is added to by one `UPDATE` that adds to the column,
 * and a state changes by an `UPDATE` that names the state it changes from, so that the workers of one run lose
 * nothing to each other.
 */
final readonly class DbalSendRunRepository implements SendRunRepository
{
    private const string COLUMNS = 'id, campaign_id, run_number, status, pause_reason, queued, sent, failed, skipped, bounced, complained, base_sent, base_failed, base_bounced, base_complained, started_at, queued_at, paused_at, ended_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function create(SendRun $run): bool
    {
        $row = [
            'id' => $run->id,
            'campaign_id' => $run->campaignId,
            'run_number' => $run->number,
            'status' => $run->status->value,
            'pause_reason' => $run->pauseReason?->value,
            'queued' => $run->queued,
            'started_at' => $run->startedAt->getTimestamp(),
            'queued_at' => $run->queuedAt?->getTimestamp(),
            'paused_at' => $run->pausedAt?->getTimestamp(),
            'ended_at' => $run->endedAt?->getTimestamp(),
        ];

        foreach (SendRunCounter::cases() as $counter) {
            $row[$counter->value] = $run->count($counter);
        }

        foreach (SendRun::BREAKER_COUNTERS as $counter) {
            $row['base_'.$counter->value] = $run->baseline()[$counter->value] ?? 0;
        }

        return Sql::insertUnlessPresent($this->connection, NewsletterSchema::SEND_RUNS, $row, ['id']);
    }

    #[Override]
    public function get(string $id): ?SendRun
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, NewsletterSchema::SEND_RUNS), [$id]);

        return false === $row ? null : $this->run($row);
    }

    #[Override]
    public function ofCampaign(string $campaignId): array
    {
        return array_map($this->run(...), $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE campaign_id = ? ORDER BY run_number', self::COLUMNS, NewsletterSchema::SEND_RUNS), [$campaignId]));
    }

    #[Override]
    public function add(string $runId, SendRunCounter $counter, int $by = 1): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET %2$s = %2$s + ? WHERE id = ?', NewsletterSchema::SEND_RUNS, $counter->value),
            [$by, $runId],
            [ParameterType::INTEGER, ParameterType::STRING],
        );
    }

    #[Override]
    public function queuedAll(SendRun $run): void
    {
        $this->connection->update(NewsletterSchema::SEND_RUNS, ['queued' => $run->queued, 'queued_at' => $run->queuedAt?->getTimestamp()], ['id' => $run->id]);
    }

    #[Override]
    public function transition(SendRun $run, SendRunStatus $from): bool
    {
        $baseline = $run->baseline();

        return 1 === (int) $this->connection->update(NewsletterSchema::SEND_RUNS, [
            'status' => $run->status->value,
            'pause_reason' => $run->pauseReason?->value,
            'paused_at' => $run->pausedAt?->getTimestamp(),
            'ended_at' => $run->endedAt?->getTimestamp(),
            'base_sent' => $baseline[SendRunCounter::Sent->value] ?? 0,
            'base_failed' => $baseline[SendRunCounter::Failed->value] ?? 0,
            'base_bounced' => $baseline[SendRunCounter::Bounced->value] ?? 0,
            'base_complained' => $baseline[SendRunCounter::Complained->value] ?? 0,
        ], ['id' => $run->id, 'status' => $from->value]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function run(array $row): SendRun
    {
        $counts = [];
        $baseline = [];

        foreach (SendRunCounter::cases() as $counter) {
            $counts[$counter->value] = Sql::integer($row[$counter->value]);
        }

        foreach (SendRun::BREAKER_COUNTERS as $counter) {
            $baseline[$counter->value] = Sql::integer($row['base_'.$counter->value]);
        }

        $reason = Sql::nullableString($row['pause_reason']);

        return SendRun::restore(
            new SendRun(Sql::string($row['id']), Sql::string($row['campaign_id']), Sql::integer($row['run_number']), Sql::instant($row['started_at'])),
            SendRunStatus::from(Sql::string($row['status'])),
            null === $reason ? null : PauseReason::from($reason),
            Sql::integer($row['queued']),
            $counts,
            $baseline,
            Sql::nullableInstant($row['queued_at']),
            Sql::nullableInstant($row['paused_at']),
            Sql::nullableInstant($row['ended_at']),
        );
    }
}
