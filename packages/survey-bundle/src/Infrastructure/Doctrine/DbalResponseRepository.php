<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\RecordOutcome;
use Phil\SurveyBundle\Domain\ValueObject\Response;
use Throwable;

/**
 * The responses, their addenda, the counters of the quotas and the marks of participation, through Doctrine DBAL.
 *
 * A response is recorded in one transaction: the mark inserted unless taken, each counter raised by a conditional
 * update — `taken = taken + 1 … WHERE taken < limit`, which the database runs atomically on the row it locks —, then
 * the response inserted; a mark taken or a counter at its limit rolls everything back. Concurrent respondents
 * therefore never take more places than a quota holds, on SQLite, MySQL, MariaDB and PostgreSQL alike; a deadlock the
 * database breaks is retried, four times at most, after a short random wait.
 */
final readonly class DbalResponseRepository implements ResponseRepository
{
    private const int ATTEMPTS = 5;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(Response $response, ?string $participation, array $quotas): RecordOutcome
    {
        // The counters exist before the transaction, each created once by whoever comes first: inside it, the inserts
        // of concurrent respondents would wait on each other's locks.
        foreach ($quotas as $quota) {
            Sql::insertIgnoring($this->connection, SurveyTables::QUOTAS, ['questionnaire' => $response->questionnaire, 'quota' => $quota->name, 'taken' => 0]);
        }

        for ($attempt = 1;; ++$attempt) {
            try {
                return $this->attempt($response, $participation, $quotas);
            } catch (DeadlockException $deadlockException) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $deadlockException;
                }

                usleep(random_int(1_000, 10_000) * $attempt);
            }
        }
    }

    #[Override]
    public function hasParticipated(string $questionnaire, string $subject, string $participation): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM '.SurveyTables::PARTICIPATIONS.' WHERE questionnaire = ? AND subject = ? AND respondent_digest = ?',
            [$questionnaire, $subject, $participation],
        );
    }

    #[Override]
    public function find(string $id): ?Response
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.SurveyTables::RESPONSES.' WHERE id = ?', [$id]);

        return false === $row ? null : $this->response($row);
    }

    #[Override]
    public function addAddendum(string $questionnaire, string $digest, string $text, DateTimeImmutable $at, int $max): bool
    {
        $id = $this->connection->fetchOne('SELECT id FROM '.SurveyTables::RESPONSES.' WHERE questionnaire = ? AND addendum_digest = ?', [$questionnaire, $digest]);

        if (false === $id) {
            return false;
        }

        $count = Column::int($this->connection->fetchOne('SELECT COUNT(*) FROM '.SurveyTables::ADDENDA.' WHERE response_id = ?', [$id]));

        if ($count >= $max) {
            return false;
        }

        try {
            $this->connection->insert(
                SurveyTables::ADDENDA,
                ['response_id' => $id, 'position' => $count + 1, 'body' => $text, 'added_at' => $at],
                ['position' => Types::SMALLINT, 'body' => Types::TEXT, 'added_at' => Types::DATETIME_UTC_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            // Another addendum took this place in the meantime: the respondent sends theirs again.
            return false;
        }

        return true;
    }

    #[Override]
    public function addendaOf(string $response): array
    {
        $addenda = [];

        foreach ($this->connection->fetchAllAssociative('SELECT body, added_at FROM '.SurveyTables::ADDENDA.' WHERE response_id = ? ORDER BY position', [$response]) as $row) {
            $addenda[] = new Addendum($response, Column::string($row['body']), $this->instant($row['added_at']));
        }

        return $addenda;
    }

    #[Override]
    public function count(string $questionnaire, ?string $subject = null): int
    {
        [$where, $parameters] = $this->scope($questionnaire, $subject);

        return Column::int($this->connection->fetchOne('SELECT COUNT(*) FROM '.SurveyTables::RESPONSES.' WHERE '.$where, $parameters));
    }

    #[Override]
    public function answers(string $questionnaire, string $question, ?string $subject = null): iterable
    {
        [$where, $parameters] = $this->scope($questionnaire, $subject);

        foreach ($this->connection->iterateColumn('SELECT answers FROM '.SurveyTables::RESPONSES.' WHERE '.$where, $parameters) as $answers) {
            yield Sql::decode($answers)[$question] ?? null;
        }
    }

    #[Override]
    public function quotaCounts(string $questionnaire): array
    {
        $counts = [];

        foreach ($this->connection->fetchAllNumeric('SELECT quota, taken FROM '.SurveyTables::QUOTAS.' WHERE questionnaire = ? ORDER BY quota', [$questionnaire]) as [$quota, $taken]) {
            $counts[Column::string($quota)] = Column::int($taken);
        }

        return $counts;
    }

    #[Override]
    public function ofRespondent(string $respondent): array
    {
        return array_map(
            $this->response(...),
            $this->connection->fetchAllAssociative('SELECT * FROM '.SurveyTables::RESPONSES.' WHERE respondent = ? ORDER BY submitted_at, id', [$respondent]),
        );
    }

    #[Override]
    public function eraseRespondent(string $respondent, string $participations): int
    {
        return $this->connection->transactional(static function (Connection $connection) use ($respondent, $participations): int {
            $connection->executeStatement('DELETE FROM '.SurveyTables::ADDENDA.' WHERE response_id IN (SELECT id FROM '.SurveyTables::RESPONSES.' WHERE respondent = ?)', [$respondent]);
            $deleted = (int) $connection->delete(SurveyTables::RESPONSES, ['respondent' => $respondent]);
            $connection->delete(SurveyTables::PARTICIPATIONS, ['respondent_digest' => $participations]);

            return $deleted;
        });
    }

    #[Override]
    public function participationsOf(string $participations): array
    {
        /** @var list<array{questionnaire: string, subject: string}> */
        return $this->connection->fetchAllAssociative('SELECT questionnaire, subject FROM '.SurveyTables::PARTICIPATIONS.' WHERE respondent_digest = ? ORDER BY questionnaire, subject', [$participations]);
    }

    #[Override]
    public function purgeBefore(string $questionnaire, DateTimeImmutable $before, int $limit): int
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(SurveyTables::RESPONSES)
            ->where('questionnaire = :questionnaire')
            ->andWhere('submitted_at < :before')
            ->orderBy('submitted_at')
            ->addOrderBy('id')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('before', $before, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->fetchFirstColumn();

        if ([] === $ids) {
            return 0;
        }

        return $this->connection->transactional(static function (Connection $connection) use ($ids): int {
            $connection->executeStatement('DELETE FROM '.SurveyTables::ADDENDA.' WHERE response_id IN (?)', [$ids], [ArrayParameterType::STRING]);

            return (int) $connection->executeStatement('DELETE FROM '.SurveyTables::RESPONSES.' WHERE id IN (?)', [$ids], [ArrayParameterType::STRING]);
        });
    }

    /**
     * @param list<Quota> $quotas
     */
    private function attempt(Response $response, ?string $participation, array $quotas): RecordOutcome
    {
        $this->connection->beginTransaction();

        try {
            if (null !== $participation && !Sql::insertIgnoring($this->connection, SurveyTables::PARTICIPATIONS, ['questionnaire' => $response->questionnaire, 'subject' => $response->subject ?? '', 'respondent_digest' => $participation])) {
                $this->connection->rollBack();

                return RecordOutcome::alreadyAnswered();
            }

            foreach ($quotas as $quota) {
                $taken = (int) $this->connection->executeStatement(
                    'UPDATE '.SurveyTables::QUOTAS.' SET taken = taken + 1 WHERE questionnaire = ? AND quota = ? AND taken < ?',
                    [$response->questionnaire, $quota->name, $quota->limit],
                    [Types::STRING, Types::STRING, Types::INTEGER],
                );

                if (1 !== $taken) {
                    $this->connection->rollBack();

                    return RecordOutcome::quotaFull($quota->name);
                }
            }

            $this->connection->insert(SurveyTables::RESPONSES, [
                'id' => $response->id,
                'questionnaire' => $response->questionnaire,
                'subject' => $response->subject,
                'respondent' => $response->respondent,
                'ending' => $response->ending,
                'answers' => Sql::json($response->answers),
                'computed' => Sql::json($response->computed),
                'addendum_digest' => $response->addendumDigest,
                'submitted_at' => $response->submittedAt,
            ], ['answers' => Types::TEXT, 'computed' => Types::TEXT, 'submitted_at' => Types::DATETIME_UTC_IMMUTABLE]);
            $this->connection->commit();

            return RecordOutcome::recorded();
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array{string, list<string>}
     */
    private function scope(string $questionnaire, ?string $subject): array
    {
        return null === $subject ? ['questionnaire = ?', [$questionnaire]] : ['questionnaire = ? AND subject = ?', [$questionnaire, $subject]];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function response(array $row): Response
    {
        return new Response(
            Column::string($row['id']),
            Column::string($row['questionnaire']),
            Column::nullableString($row['subject']),
            Column::nullableString($row['respondent']),
            Column::string($row['ending']),
            Sql::decode($row['answers']),
            Sql::decode($row['computed']),
            Column::nullableString($row['addendum_digest']),
            $this->instant($row['submitted_at']),
        );
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $instant = $this->connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        \assert($instant instanceof DateTimeImmutable);

        return $instant;
    }
}
