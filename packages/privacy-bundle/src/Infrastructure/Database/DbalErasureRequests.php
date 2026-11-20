<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * The erasure requests and their steps, in the tables of `PrivacyTables`, on the connection of the outbox: `open()`
 * writes the request, its steps and the outbox records in one transaction.
 *
 * Every change of a step is one `UPDATE` that names the states it starts from, and the lease it holds when it holds
 * one: two workers never run the same step while its lease runs, and a finished step is never run again, on every
 * platform, without locking rows.
 *
 * As the purger of the store `phil_privacy_erasure_request`, it removes the requests closed before the cutoff of a
 * retention rule, with their steps; a request that is not closed is never purged.
 *
 * The store keeps no state of its own between calls: one instance serves every message of a worker.
 */
final readonly class DbalErasureRequests implements ErasureRequestStore, RetentionPurger
{
    private const string STEP_COLUMNS = 'request_id, module, state, attempts, effects, reason, updated_at, completed_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function open(ErasureRequest $request, array $steps, Closure $alsoInTransaction): void
    {
        $this->connection->transactional(function () use ($request, $steps, $alsoInTransaction): void {
            $this->connection->insert(PrivacyTables::ERASURE_REQUESTS, [
                'id' => $request->id,
                'subject' => $request->subject?->value,
                'guest_request' => $request->guestRequestId,
                'inventory_version' => $request->inventoryVersion,
                'state' => $request->state->value,
                'requested_at' => $this->text($request->requestedAt),
                'closed_at' => UtcMoments::optionalText($this->connection, $request->closedAt),
                'access_hash' => $request->accessHash,
            ]);

            foreach ($steps as $step) {
                $this->connection->insert(PrivacyTables::ERASURE_STEPS, [
                    'request_id' => $step->requestId,
                    'module' => $step->module,
                    'state' => $step->state->value,
                    'attempts' => $step->attempts,
                    'idempotency_key' => $step->idempotencyKey(),
                    'updated_at' => $this->text($step->updatedAt),
                ]);
            }

            $alsoInTransaction();
        });
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function find(string $id): ?ErasureRequest
    {
        $row = $this->connection->fetchAssociative('SELECT id, subject, guest_request, inventory_version, state, requested_at, closed_at, access_hash FROM '.PrivacyTables::ERASURE_REQUESTS.' WHERE id = ?', [$id]);

        if (false === $row) {
            return null;
        }

        $state = RequestState::tryFrom($this->string($row['state'])) ?? throw InvalidPrivacyValue::because('An erasure request of the table has no known state.');
        $subject = $this->nullableString($row['subject']);

        return new ErasureRequest(
            $this->string($row['id']),
            null === $subject ? null : DataSubject::fromString($subject),
            $this->nullableString($row['guest_request']),
            $this->string($row['inventory_version']),
            $state,
            UtcMoments::read($this->connection, $row['requested_at'], 'requested_at'),
            UtcMoments::readOptional($this->connection, $row['closed_at'], 'closed_at'),
            $this->nullableString($row['access_hash']),
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function steps(string $id): array
    {
        return array_map($this->stepOf(...), $this->connection->fetchAllAssociative('SELECT '.self::STEP_COLUMNS.' FROM '.PrivacyTables::ERASURE_STEPS.' WHERE request_id = ? ORDER BY module', [$id]));
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function step(string $id, string $module): ?ErasureStep
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::STEP_COLUMNS.' FROM '.PrivacyTables::ERASURE_STEPS.' WHERE request_id = ? AND module = ?', [$id, $module]);

        return false === $row ? null : $this->stepOf($row);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function lease(string $id, string $module, string $lease, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): bool
    {
        $at = $this->text($now);

        return $this->changed(
            "SET state = 'running', lease = ?, lease_until = ?, reason = NULL, updated_at = ? WHERE request_id = ? AND module = ?"
            ." AND (state IN ('pending', 'needs_attention') OR (state = 'running' AND lease_until IS NOT NULL AND lease_until <= ?))",
            [$lease, $this->text($leaseUntil), $at, $id, $module, $at],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function complete(string $id, string $module, string $lease, array $effects, DateTimeImmutable $now): bool
    {
        $at = $this->text($now);

        return $this->changed(
            "SET state = 'completed', lease = NULL, lease_until = NULL, effects = ?, updated_at = ?, completed_at = ? WHERE request_id = ? AND module = ? AND state = 'running' AND lease = ?",
            [$this->effects($effects), $at, $at, $id, $module, $lease],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function await(string $id, string $module, string $lease, DateTimeImmutable $now): bool
    {
        return $this->changed(
            "SET lease = NULL, lease_until = NULL, updated_at = ? WHERE request_id = ? AND module = ? AND state = 'running' AND lease = ?",
            [$this->text($now), $id, $module, $lease],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function acknowledge(string $idempotencyKey, array $effects, DateTimeImmutable $now): bool
    {
        $at = $this->text($now);

        return $this->changed(
            "SET state = 'completed', effects = ?, updated_at = ?, completed_at = ? WHERE idempotency_key = ? AND state = 'running' AND lease IS NULL",
            [$this->effects($effects), $at, $at, $idempotencyKey],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function release(string $id, string $module, string $lease, DateTimeImmutable $now): bool
    {
        return $this->changed(
            "SET state = 'pending', attempts = attempts + 1, lease = NULL, lease_until = NULL, updated_at = ? WHERE request_id = ? AND module = ? AND state = 'running' AND lease = ?",
            [$this->text($now), $id, $module, $lease],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function flag(string $id, string $module, AttentionReason $reason, DateTimeImmutable $now): bool
    {
        return $this->changed(
            "SET state = 'needs_attention', reason = ?, lease = NULL, lease_until = NULL, updated_at = ? WHERE request_id = ? AND module = ? AND state IN ('pending', 'running')",
            [$reason->value, $this->text($now), $id, $module],
        );
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function reopen(string $id, DateTimeImmutable $now): array
    {
        $modules = $this->connection->fetchFirstColumn(
            'SELECT module FROM '.PrivacyTables::ERASURE_STEPS." WHERE request_id = ? AND (state = 'needs_attention' OR (state = 'running' AND lease IS NULL)) ORDER BY module",
            [$id],
        );

        $this->connection->executeStatement(
            'UPDATE '.PrivacyTables::ERASURE_STEPS." SET state = 'pending', reason = NULL, updated_at = ? WHERE request_id = ? AND (state = 'needs_attention' OR (state = 'running' AND lease IS NULL))",
            [$this->text($now), $id],
        );

        return array_map($this->string(...), $modules);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function settle(string $id, RequestState $state, ?DateTimeImmutable $closedAt): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE '.PrivacyTables::ERASURE_REQUESTS." SET state = ?, closed_at = ? WHERE id = ? AND state NOT IN ('completed', 'failed')",
            [$state->value, $state->isClosed() ? UtcMoments::optionalText($this->connection, $closedAt) : null, $id],
        );
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::ERASURE_REQUESTS;
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function purge(PurgeOrder $order): int
    {
        return $this->connection->transactional(function () use ($order): int {
            $condition = "state IN ('completed', 'failed') AND closed_at IS NOT NULL AND closed_at < ?";
            $cutoff = [$this->text($order->cutoff)];
            $this->connection->executeStatement('DELETE FROM '.PrivacyTables::ERASURE_STEPS.' WHERE request_id IN (SELECT id FROM '.PrivacyTables::ERASURE_REQUESTS.' WHERE '.$condition.')', $cutoff);

            return (int) $this->connection->executeStatement('DELETE FROM '.PrivacyTables::ERASURE_REQUESTS.' WHERE '.$condition, $cutoff);
        });
    }

    /**
     * @param non-empty-string $set        the `SET … WHERE …` of the steps' table
     * @param list<int|string> $parameters
     *
     * @throws Exception when the database fails
     */
    private function changed(string $set, array $parameters): bool
    {
        return 1 === (int) $this->connection->executeStatement('UPDATE '.PrivacyTables::ERASURE_STEPS.' '.$set, $parameters);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidPrivacyValue when a column is not what the table keeps
     */
    private function stepOf(array $row): ErasureStep
    {
        $state = StepState::tryFrom($this->string($row['state'] ?? null)) ?? throw InvalidPrivacyValue::because('An erasure step of the table has no known state.');
        $reason = $this->nullableString($row['reason'] ?? null);
        $effects = $this->nullableString($row['effects'] ?? null);

        return new ErasureStep(
            $this->string($row['request_id'] ?? null),
            $this->string($row['module'] ?? null),
            $state,
            (int) $this->string($row['attempts'] ?? null),
            null === $effects ? [] : $this->readEffects($effects),
            UtcMoments::read($this->connection, $row['updated_at'] ?? null, 'updated_at'),
            UtcMoments::readOptional($this->connection, $row['completed_at'] ?? null, 'completed_at'),
            null === $reason ? null : AttentionReason::tryFrom($reason),
        );
    }

    /**
     * @param array<string, ErasureEffect> $effects
     */
    private function effects(array $effects): string
    {
        return json_encode(array_map(static fn (ErasureEffect $effect): string => $effect->value, $effects), \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, ErasureEffect>
     *
     * @throws InvalidPrivacyValue when the column is not the JSON of a report's effects
     */
    private function readEffects(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidPrivacyValue::because('The effects of an erasure step are a JSON object of effects by store.');
        }

        $effects = [];

        foreach (\is_array($decoded) ? $decoded : [] as $store => $effect) {
            $effects[(string) $store] = ErasureEffect::tryFrom(\is_string($effect) ? $effect : '') ?? throw InvalidPrivacyValue::because('The effects of an erasure step name an unknown effect.');
        }

        return $effects;
    }

    private function string(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? (string) $value : throw InvalidPrivacyValue::because('A column of the erasure tables holds no text.');
    }

    private function nullableString(mixed $value): ?string
    {
        return null === $value ? null : $this->string($value);
    }

    private function text(DateTimeImmutable $moment): string
    {
        return UtcMoments::text($this->connection, $moment);
    }
}
