<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ReviewsBundle\Domain\Contract\RatingRepository;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\CriterionRating;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The ratings kept through DBAL, one row per subject. A computation first takes the row of its subject — an update of
 * its version, which the database serialises —, then reads the published reviews and writes the ratings in the same
 * transaction: two computations at the same moment run one after the other, and the second reads what the first could
 * not see.
 */
final readonly class DbalRatingRepository implements RatingRepository
{
    private const array TYPES = ['criteria' => Types::JSON, 'superlatives' => Types::JSON, 'suspended' => Types::BOOLEAN];

    private Columns $columns;

    public function __construct(
        private Connection $connection,
    ) {
        $this->columns = new Columns($connection);
    }

    #[Override]
    public function summary(SubjectRef $subject): RatingSummary
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM '.ReviewTables::SUBJECT.' WHERE subject_type = ? AND subject_id = ?',
            [$subject->type, $subject->id],
        );

        return false === $row ? RatingSummary::empty($subject) : $this->summaryOf($subject, $row);
    }

    #[Override]
    public function recompute(SubjectRef $subject, BayesianPrior $prior): RatingSummary
    {
        return $this->connection->transactional(function (Connection $connection) use ($subject, $prior): RatingSummary {
            $this->take($connection, $subject);
            $where = ' WHERE r.subject_type = ? AND r.subject_id = ? AND r.status = ?';
            $parameters = [$subject->type, $subject->id, ReviewStatus::Published->value];

            $totals = $connection->fetchAssociative(
                'SELECT COUNT(*) AS reviews, SUM(r.overall) AS total, SUM(CASE WHEN r.origin = ? THEN 0 ELSE 1 END) AS foreign_reviews FROM '.ReviewTables::REVIEW.' r'.$where,
                [Origin::Site->value, ...$parameters],
            );
            $totals = false === $totals ? [] : $totals;

            $count = Columns::int($totals['reviews'] ?? 0);
            $sum = Columns::int($totals['total'] ?? 0);

            $criteria = [];

            foreach ($connection->fetchAllAssociative(
                'SELECT s.criterion, SUM(s.score) AS total, COUNT(*) AS reviews FROM '.ReviewTables::SCORE.' s JOIN '.ReviewTables::REVIEW.' r ON r.id = s.review_id'.$where.' GROUP BY s.criterion ORDER BY s.criterion',
                $parameters,
            ) as $row) {
                $rated = Columns::int($row['reviews']);
                $criteria[Columns::string($row['criterion'])] = ['mean' => Columns::int($row['total']) / max(1, $rated), 'count' => $rated];
            }

            $superlatives = [];

            foreach ($connection->fetchAllAssociative(
                'SELECT l.label, COUNT(*) AS times FROM '.ReviewTables::SUPERLATIVE.' l JOIN '.ReviewTables::REVIEW.' r ON r.id = l.review_id'.$where.' GROUP BY l.label ORDER BY l.label',
                $parameters,
            ) as $row) {
                $superlatives[Columns::string($row['label'])] = Columns::int($row['times']);
            }

            $connection->update(ReviewTables::SUBJECT, [
                'review_count' => $count,
                'rating_sum' => $sum,
                'foreign_count' => Columns::int($totals['foreign_reviews'] ?? 0),
                'weighted' => 0 === $count ? null : $prior->weigh($count, $sum),
                'criteria' => $criteria,
                'superlatives' => $superlatives,
            ], ['subject_type' => $subject->type, 'subject_id' => $subject->id], self::TYPES);

            $row = $connection->fetchAssociative(
                'SELECT * FROM '.ReviewTables::SUBJECT.' WHERE subject_type = ? AND subject_id = ?',
                [$subject->type, $subject->id],
            );

            return false === $row ? RatingSummary::empty($subject) : $this->summaryOf($subject, $row);
        });
    }

    #[Override]
    public function suspend(SubjectRef $subject, bool $suspended): void
    {
        $this->connection->transactional(function (Connection $connection) use ($subject, $suspended): void {
            $this->take($connection, $subject);
            $connection->update(ReviewTables::SUBJECT, ['suspended' => $suspended], ['subject_type' => $subject->type, 'subject_id' => $subject->id], self::TYPES);
        });
    }

    #[Override]
    public function ranking(string $type, int $limit): array
    {
        $ranking = [];

        foreach ($this->connection->fetchAllAssociative(
            'SELECT * FROM '.ReviewTables::SUBJECT.' WHERE subject_type = ? AND review_count > 0 ORDER BY weighted DESC, review_count DESC, subject_id ASC LIMIT '.$limit,
            [$type],
        ) as $row) {
            $ranking[] = $this->summaryOf(SubjectRef::of($type, Columns::string($row['subject_id'])), $row);
        }

        return $ranking;
    }

    #[Override]
    public function subjects(string $type): iterable
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT subject_id FROM '.ReviewTables::SUBJECT.' WHERE subject_type = ? UNION SELECT subject_id FROM '.ReviewTables::REVIEW.' WHERE subject_type = ? ORDER BY subject_id',
            [$type, $type],
        );

        foreach ($ids as $id) {
            yield SubjectRef::of($type, Columns::string($id));
        }
    }

    /**
     * Takes the row of the subject for the transaction, creating it empty when it does not exist yet.
     */
    private function take(Connection $connection, SubjectRef $subject): void
    {
        $key = ['subject_type' => $subject->type, 'subject_id' => $subject->id];
        $lock = 'UPDATE '.ReviewTables::SUBJECT.' SET version = version + 1 WHERE subject_type = ? AND subject_id = ?';

        if (0 < Columns::int($connection->executeStatement($lock, [$subject->type, $subject->id]))) {
            return;
        }

        try {
            // A savepoint: a row another transaction created meanwhile refuses this one and leaves the transaction usable.
            $connection->transactional(static function (Connection $nested) use ($key): void {
                $nested->insert(ReviewTables::SUBJECT, [
                    ...$key,
                    'review_count' => 0,
                    'rating_sum' => 0,
                    'foreign_count' => 0,
                    'weighted' => null,
                    'criteria' => [],
                    'superlatives' => [],
                    'suspended' => false,
                    'version' => 1,
                ], self::TYPES);
            });
        } catch (UniqueConstraintViolationException) {
            $connection->executeStatement($lock, [$subject->type, $subject->id]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function summaryOf(SubjectRef $subject, array $row): RatingSummary
    {
        $criteria = [];

        foreach ($this->columns->json($row['criteria'] ?? null) as $code => $rating) {
            if (\is_string($code) && \is_array($rating) && is_numeric($rating['mean'] ?? null)) {
                $criteria[$code] = new CriterionRating($code, (float) $rating['mean'], Columns::int($rating['count'] ?? 0));
            }
        }

        $superlatives = [];

        foreach ($this->columns->json($row['superlatives'] ?? null) as $label => $times) {
            if (\is_string($label)) {
                $superlatives[$label] = Columns::int($times);
            }
        }

        $weighted = $row['weighted'] ?? null;

        return new RatingSummary(
            $subject,
            Columns::int($row['review_count'] ?? 0),
            Columns::int($row['rating_sum'] ?? 0),
            is_numeric($weighted) ? (float) $weighted : null,
            $criteria,
            $superlatives,
            Columns::int($row['foreign_count'] ?? 0),
            $this->columns->bool($row['suspended'] ?? false),
        );
    }
}
