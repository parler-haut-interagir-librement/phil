<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\Status\Verification;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The reviews kept through DBAL. A second review of one subject by one author, and a second vote of one member on one
 * review, are refused by the unique constraints of the tables, so that two requests at the same moment cannot both
 * write one; the counts of the votes are written again from the votes by one statement.
 */
final readonly class DbalReviewRepository implements ReviewRepository
{
    private const array TYPES = [
        'compensated' => Types::BOOLEAN,
        'written_at' => Types::DATETIME_UTC_IMMUTABLE,
        'edited_at' => Types::DATETIME_UTC_IMMUTABLE,
        'withdrawn_at' => Types::DATETIME_UTC_IMMUTABLE,
        'response_at' => Types::DATETIME_UTC_IMMUTABLE,
        'contest_filed_at' => Types::DATETIME_UTC_IMMUTABLE,
        'contest_settled_at' => Types::DATETIME_UTC_IMMUTABLE,
    ];

    private Columns $columns;

    public function __construct(
        private Connection $connection,
    ) {
        $this->columns = new Columns($connection);
    }

    #[Override]
    public function get(ReviewId $id): ?Review
    {
        return $this->hydrate($this->connection->fetchAllAssociative('SELECT * FROM '.ReviewTables::REVIEW.' WHERE id = ?', [$id->value]))[0] ?? null;
    }

    #[Override]
    public function of(SubjectRef $subject, MemberId $author): ?Review
    {
        return $this->hydrate($this->connection->fetchAllAssociative(
            'SELECT * FROM '.ReviewTables::REVIEW.' WHERE subject_type = ? AND subject_id = ? AND author = ?',
            [$subject->type, $subject->id, $author->value],
        ))[0] ?? null;
    }

    #[Override]
    public function add(Review $review): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($review): void {
                $connection->insert(ReviewTables::REVIEW, [
                    'id' => $review->id->value,
                    'subject_type' => $review->subject->type,
                    'subject_id' => $review->subject->id,
                    'origin' => $review->origin->value,
                    'verification' => $review->verification?->value,
                    'written_at' => $review->writtenAt,
                    'helpful_count' => 0,
                    'unhelpful_count' => 0,
                    ...$this->state($review),
                ], self::TYPES);
                $this->writeScores($connection, $review);
            });
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw DuplicateReview::of((string) $review->subject, $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function save(Review $review): void
    {
        $this->connection->transactional(function (Connection $connection) use ($review): void {
            $connection->update(ReviewTables::REVIEW, $this->state($review), ['id' => $review->id->value], self::TYPES);
            $connection->delete(ReviewTables::SCORE, ['review_id' => $review->id->value]);
            $connection->delete(ReviewTables::SUPERLATIVE, ['review_id' => $review->id->value]);
            $this->writeScores($connection, $review);
        });
    }

    #[Override]
    public function published(SubjectRef $subject, ReviewOrder $order, int $limit, int $offset = 0): array
    {
        $sort = match ($order) {
            ReviewOrder::Helpful => 'helpful_count DESC, ',
            ReviewOrder::Newest => '',
            ReviewOrder::Highest => 'overall DESC, ',
            ReviewOrder::Lowest => 'overall ASC, ',
        };

        return $this->hydrate($this->connection->fetchAllAssociative(
            'SELECT * FROM '.ReviewTables::REVIEW.' WHERE subject_type = ? AND subject_id = ? AND status = ? ORDER BY '.$sort.'written_at DESC, id DESC'
            .' LIMIT '.$limit.' OFFSET '.$offset,
            [$subject->type, $subject->id, ReviewStatus::Published->value],
        ));
    }

    #[Override]
    public function countPublished(SubjectRef $subject): int
    {
        return Columns::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ReviewTables::REVIEW.' WHERE subject_type = ? AND subject_id = ? AND status = ?',
            [$subject->type, $subject->id, ReviewStatus::Published->value],
        ));
    }

    #[Override]
    public function vote(ReviewId $review, MemberId $voter, bool $helpful, DateTimeImmutable $at): void
    {
        $row = ['helpful' => $helpful ? 1 : 0, 'voted_at' => $at];
        $types = ['voted_at' => Types::DATETIME_UTC_IMMUTABLE];

        try {
            // A savepoint when the caller holds a transaction: the refused insert leaves it usable on PostgreSQL.
            $this->connection->transactional(static function (Connection $connection) use ($review, $voter, $row, $types): void {
                $connection->insert(ReviewTables::VOTE, ['review_id' => $review->value, 'voter' => $voter->value, ...$row], $types);
            });
        } catch (UniqueConstraintViolationException) {
            $this->connection->update(ReviewTables::VOTE, $row, ['review_id' => $review->value, 'voter' => $voter->value], $types);
        }

        $this->recount($review->value);
    }

    #[Override]
    public function retractVote(ReviewId $review, MemberId $voter): void
    {
        $this->connection->delete(ReviewTables::VOTE, ['review_id' => $review->value, 'voter' => $voter->value]);
        $this->recount($review->value);
    }

    #[Override]
    public function voteOf(ReviewId $review, MemberId $voter): ?bool
    {
        $helpful = $this->connection->fetchOne('SELECT helpful FROM '.ReviewTables::VOTE.' WHERE review_id = ? AND voter = ?', [$review->value, $voter->value]);

        return false === $helpful ? null : 1 === Columns::int($helpful);
    }

    #[Override]
    public function by(MemberId $author): array
    {
        return $this->hydrate($this->connection->fetchAllAssociative(
            'SELECT * FROM '.ReviewTables::REVIEW.' WHERE author = ? ORDER BY written_at, id',
            [$author->value],
        ));
    }

    #[Override]
    public function votesBy(MemberId $voter): array
    {
        $votes = [];

        foreach ($this->connection->fetchAllAssociative('SELECT review_id, helpful, voted_at FROM '.ReviewTables::VOTE.' WHERE voter = ? ORDER BY voted_at, review_id', [$voter->value]) as $row) {
            $at = $this->columns->moment($row['voted_at']);

            if ($at instanceof DateTimeImmutable) {
                $votes[] = ['review' => ReviewId::fromString(Columns::string($row['review_id'])), 'helpful' => 1 === Columns::int($row['helpful']), 'at' => $at];
            }
        }

        return $votes;
    }

    #[Override]
    public function respondedBy(MemberId $responder): array
    {
        return $this->hydrate($this->connection->fetchAllAssociative(
            'SELECT * FROM '.ReviewTables::REVIEW.' WHERE response_by = ? ORDER BY response_at, id',
            [$responder->value],
        ));
    }

    #[Override]
    public function forgetVotes(MemberId $voter): array
    {
        $reviews = array_map(Columns::string(...), $this->connection->fetchFirstColumn('SELECT review_id FROM '.ReviewTables::VOTE.' WHERE voter = ? ORDER BY review_id', [$voter->value]));
        $this->connection->delete(ReviewTables::VOTE, ['voter' => $voter->value]);

        foreach ($reviews as $review) {
            $this->recount($review);
        }

        return array_map(ReviewId::fromString(...), $reviews);
    }

    #[Override]
    public function forgetResponses(MemberId $responder): array
    {
        $subjects = [];

        foreach ($this->connection->fetchAllAssociative('SELECT DISTINCT subject_type, subject_id FROM '.ReviewTables::REVIEW.' WHERE response_by = ? ORDER BY subject_type, subject_id', [$responder->value]) as $row) {
            $subjects[] = SubjectRef::of(Columns::string($row['subject_type']), Columns::string($row['subject_id']));
        }

        $this->connection->update(ReviewTables::REVIEW, ['response' => null, 'response_by' => null, 'response_at' => null], ['response_by' => $responder->value]);

        return $subjects;
    }

    #[Override]
    public function remove(ReviewId $id): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($id): void {
            foreach ([ReviewTables::VOTE, ReviewTables::SCORE, ReviewTables::SUPERLATIVE] as $table) {
                $connection->delete($table, ['review_id' => $id->value]);
            }

            $connection->delete(ReviewTables::REVIEW, ['id' => $id->value]);
        });
    }

    /**
     * The columns a review changes over its life; the counts of its votes are written from the votes only, so that
     * saving a review read before a vote does not undo it.
     *
     * @return array<string, mixed>
     */
    private function state(Review $review): array
    {
        $content = $review->content();
        $response = $review->response();
        $contest = $review->contest();

        return [
            'author' => $review->author()?->value,
            'overall' => $content->overall,
            'title' => $content->title,
            'body' => $content->body,
            'compensated' => $review->compensation()->received,
            'compensation_note' => $review->compensation()->note,
            'status' => $review->status()->value,
            'removal_criterion' => $review->removalCriterion(),
            'edited_at' => $review->editedAt(),
            'withdrawn_at' => $review->withdrawnAt(),
            'response' => $response?->text,
            'response_by' => $response?->responder?->value,
            'response_at' => $response?->at,
            'contest_evidence' => $contest?->evidence,
            'contest_filed_at' => $contest?->filedAt,
            'contest_outcome' => $contest?->outcome?->value,
            'contest_settled_at' => $contest?->settledAt,
        ];
    }

    private function writeScores(Connection $connection, Review $review): void
    {
        foreach ($review->content()->criteria as $criterion => $score) {
            $connection->insert(ReviewTables::SCORE, ['review_id' => $review->id->value, 'criterion' => $criterion, 'score' => $score]);
        }

        foreach ($review->content()->superlatives as $label) {
            $connection->insert(ReviewTables::SUPERLATIVE, ['review_id' => $review->id->value, 'label' => $label]);
        }
    }

    /**
     * Writes the counts of the review again from its votes, in one statement.
     */
    private function recount(string $review): void
    {
        $this->connection->executeStatement(
            'UPDATE '.ReviewTables::REVIEW.' SET'
            .' helpful_count = (SELECT COUNT(*) FROM '.ReviewTables::VOTE.' WHERE review_id = ? AND helpful = 1),'
            .' unhelpful_count = (SELECT COUNT(*) FROM '.ReviewTables::VOTE.' WHERE review_id = ? AND helpful = 0)'
            .' WHERE id = ?',
            [$review, $review, $review],
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Review>
     */
    private function hydrate(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }

        $ids = array_map(static fn (array $row): string => Columns::string($row['id']), $rows);
        $scores = [];
        $labels = [];

        foreach ($this->connection->fetchAllAssociative('SELECT review_id, criterion, score FROM '.ReviewTables::SCORE.' WHERE review_id IN (?)', [$ids], [ArrayParameterType::STRING]) as $row) {
            $scores[Columns::string($row['review_id'])][Columns::string($row['criterion'])] = Columns::int($row['score']);
        }

        foreach ($this->connection->fetchAllAssociative('SELECT review_id, label FROM '.ReviewTables::SUPERLATIVE.' WHERE review_id IN (?) ORDER BY label', [$ids], [ArrayParameterType::STRING]) as $row) {
            $labels[Columns::string($row['review_id'])][] = Columns::string($row['label']);
        }

        return array_map(fn (array $row): Review => $this->review($row, $scores[Columns::string($row['id'])] ?? [], $labels[Columns::string($row['id'])] ?? []), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int>   $scores
     * @param list<string>         $labels
     */
    private function review(array $row, array $scores, array $labels): Review
    {
        $author = Columns::text($row['author']);
        $responseText = Columns::text($row['response']);
        $responseAt = $this->columns->moment($row['response_at']);
        $responder = Columns::text($row['response_by']);
        $evidence = Columns::text($row['contest_evidence']);
        $filedAt = $this->columns->moment($row['contest_filed_at']);
        $outcome = Columns::text($row['contest_outcome']);
        $verification = Columns::text($row['verification']);

        return Review::reconstitute(
            ReviewId::fromString(Columns::string($row['id'])),
            SubjectRef::of(Columns::string($row['subject_type']), Columns::string($row['subject_id'])),
            null === $author ? null : MemberId::fromString($author),
            new ReviewContent(Columns::int($row['overall']), $scores, Columns::text($row['title']), Columns::text($row['body']), $labels),
            Compensation::of($this->columns->bool($row['compensated']), Columns::text($row['compensation_note'])),
            Origin::from(Columns::string($row['origin'])),
            null === $verification ? null : Verification::from($verification),
            $this->columns->moment($row['written_at']) ?? new DateTimeImmutable('@0'),
            $this->columns->moment($row['edited_at']),
            ReviewStatus::from(Columns::string($row['status'])),
            Columns::text($row['removal_criterion']),
            $this->columns->moment($row['withdrawn_at']),
            null === $responseText || !$responseAt instanceof DateTimeImmutable ? null : new Response($responseText, null === $responder ? null : MemberId::fromString($responder), $responseAt),
            null === $evidence || !$filedAt instanceof DateTimeImmutable ? null : new Contest(
                $evidence,
                $filedAt,
                null === $outcome ? null : ContestOutcome::from($outcome),
                $this->columns->moment($row['contest_settled_at']),
            ),
            Columns::int($row['helpful_count']),
            Columns::int($row['unhelpful_count']),
        );
    }
}
