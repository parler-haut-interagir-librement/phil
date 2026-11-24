<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PDO;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\Status\Verification;
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\CriterionRating;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Doctrine\Columns;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalRatingRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\ReviewTables;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalReviewRepository::class)]
#[CoversClass(DbalRatingRepository::class)]
#[CoversClass(ReviewTables::class)]
#[CoversClass(Columns::class)]
final class DbalStoresTest extends TestCase
{
    private Store $store;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->at = new DateTimeImmutable('2026-11-21 10:00:00 UTC');
    }

    public function testTheTablesAreAddedOnceToASchema(): void
    {
        $schema = ReviewTables::configure(Schema::editor()->create());

        self::assertSame(
            [ReviewTables::REVIEW, ReviewTables::SCORE, ReviewTables::SUBJECT, ReviewTables::SUPERLATIVE, ReviewTables::VOTE],
            $this->tables($schema),
        );
        self::assertSame($this->tables($schema), $this->tables(ReviewTables::configure($schema)));
        self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $schema->getTable(ReviewTables::REVIEW)->getColumn('written_at')->getTypeName());
        self::assertFalse($schema->getTable(ReviewTables::REVIEW)->getColumn('author')->getNotnull());
        self::assertTrue($schema->getTable(ReviewTables::REVIEW)->getColumn('overall')->getNotnull());
        self::assertSame(36, $schema->getTable(ReviewTables::REVIEW)->getColumn('id')->getLength());
        self::assertTrue($schema->getTable(ReviewTables::REVIEW)->getColumn('id')->getFixed());
    }

    public function testAReviewIsKeptWithEverything(): void
    {
        $review = $this->review('ada', new ReviewContent(4, ['rules' => 3, 'replayability' => 5], 'Title', 'Body', ['quick', 'family']), Compensation::received('A copy'), Verification::Attendance);
        $this->store->repository->add($review);
        $review->respond(new Response('Thanks', Store::member('shop'), $this->at));
        $review->withdraw('spam', $this->at->modify('+1 hour'));
        $review->contestWithdrawal(new Contest('Evidence', $this->at->modify('+2 hours')));
        $review->settleContest(ContestOutcome::Rejected, $this->at->modify('+3 hours'));

        $this->store->repository->save($review);

        $kept = $this->store->repository->get($review->id);
        self::assertNotNull($kept);
        self::assertSame('ada', $kept->author()?->value);
        self::assertSame(['replayability' => 5, 'rules' => 3], $kept->content()->criteria);
        self::assertSame(['family', 'quick'], $kept->content()->superlatives);
        self::assertSame('Title', $kept->content()->title);
        self::assertSame('Body', $kept->content()->body);
        self::assertSame('A copy', $kept->compensation()->note);
        self::assertTrue($kept->compensation()->received);
        self::assertSame(Verification::Attendance, $kept->verification);
        self::assertSame(Origin::Site, $kept->origin);
        self::assertEquals($this->at, $kept->writtenAt);
        self::assertSame('spam', $kept->removalCriterion());
        self::assertEquals($this->at->modify('+1 hour'), $kept->withdrawnAt());
        self::assertSame('Thanks', $kept->response()?->text);
        self::assertSame('shop', $kept->response()->responder?->value);
        self::assertEquals($this->at, $kept->response()->at);
        self::assertSame('Evidence', $kept->contest()?->evidence);
        self::assertEquals($this->at->modify('+2 hours'), $kept->contest()->filedAt);
        self::assertSame(ContestOutcome::Rejected, $kept->contest()->outcome);
        self::assertEquals($this->at->modify('+3 hours'), $kept->contest()->settledAt);
        self::assertSame('UTC', $kept->writtenAt->getTimezone()->getName());
        self::assertSame([$review->id->value], array_map(static fn (Review $r): string => $r->id->value, $this->store->repository->respondedBy(Store::member('shop'))));
        self::assertNull($this->store->repository->get(ReviewId::generate()));
    }

    public function testAMomentIsKeptInUtc(): void
    {
        $review = Review::write(ReviewId::generate(), Store::game(), Store::member('ada'), new ReviewContent(3), Compensation::none(), new DateTimeImmutable('2026-11-21 05:00:00 America/Toronto'));
        $this->store->repository->add($review);

        self::assertSame('2026-11-21 10:00:00', $this->store->connection->fetchOne('SELECT written_at FROM phil_review'));
    }

    public function testASecondReviewIsRefusedByTheStore(): void
    {
        $this->store->repository->add($this->review('ada'));

        try {
            $this->store->repository->add($this->review('ada'));
            self::fail('The store took a second review.');
        } catch (DuplicateReview $duplicateReview) {
            self::assertSame('The member already reviewed game:catan.', $duplicateReview->getMessage());
        }

        self::assertSame(1, Columns::int($this->store->connection->fetchOne('SELECT COUNT(*) FROM phil_review')));
        self::assertSame(0, Columns::int($this->store->connection->fetchOne('SELECT COUNT(*) FROM phil_review_score')));
    }

    public function testAnAnonymisedReviewLeavesRoomAndManyAnonymousReviewsCoexist(): void
    {
        foreach (['ada', 'bob'] as $author) {
            $review = $this->review($author);
            $this->store->repository->add($review);
            $review->anonymise();
            $this->store->repository->save($review);
        }

        $this->store->repository->add($this->review('ada'));
        self::assertSame(3, $this->store->repository->countPublished(Store::game()));
        self::assertNull($this->store->repository->of(Store::game(), Store::member('bob')));
    }

    public function testSavingAReviewReadBeforeAVoteKeepsTheVote(): void
    {
        $review = $this->review('ada');
        $this->store->repository->add($review);
        $stale = $this->store->repository->get($review->id);
        $this->store->repository->vote($review->id, Store::member('bob'), true, $this->at);

        self::assertNotNull($stale);
        $stale->respond(new Response('Thanks', null, $this->at));
        $this->store->repository->save($stale);

        self::assertSame(1, $this->store->repository->get($review->id)?->helpful());
    }

    public function testTheVotesOfAMember(): void
    {
        $first = $this->review('ada');
        $second = $this->review('cy');
        $this->store->repository->add($first);
        $this->store->repository->add($second);
        $this->store->repository->vote($first->id, Store::member('bob'), true, $this->at);
        $this->store->repository->vote($second->id, Store::member('bob'), false, $this->at->modify('+1 minute'));
        $this->store->repository->vote($second->id, Store::member('dan'), false, $this->at);

        $votes = $this->store->repository->votesBy(Store::member('bob'));
        self::assertCount(2, $votes);
        self::assertSame($first->id->value, $votes[0]['review']->value);
        self::assertTrue($votes[0]['helpful']);
        self::assertEquals($this->at, $votes[0]['at']);
        self::assertFalse($votes[1]['helpful']);

        $forgotten = $this->store->repository->forgetVotes(Store::member('bob'));
        self::assertEqualsCanonicalizing([$first->id->value, $second->id->value], array_map(static fn (ReviewId $id): string => $id->value, $forgotten));
        self::assertSame([], $this->store->repository->votesBy(Store::member('bob')));
        self::assertSame(0, $this->store->repository->get($first->id)?->helpful());
        self::assertSame(1, $this->store->repository->get($second->id)?->unhelpful());
    }

    public function testTheResponsesOfAMemberAreForgotten(): void
    {
        $review = $this->review('ada');
        $review->respond(new Response('Thanks', Store::member('shop'), $this->at));

        $this->store->repository->add($review);
        $other = Review::write(ReviewId::generate(), Store::game('azul'), Store::member('ada'), new ReviewContent(2), Compensation::none(), $this->at);
        $other->respond(new Response('Hello', Store::member('shop'), $this->at));

        $this->store->repository->add($other);

        $subjects = $this->store->repository->forgetResponses(Store::member('shop'));

        self::assertSame(['game:azul', 'game:catan'], array_map(strval(...), $subjects));
        self::assertNull($this->store->repository->get($review->id)?->response());
        self::assertSame([], $this->store->repository->respondedBy(Store::member('shop')));
    }

    public function testARemovedReviewTakesItsRowsWithIt(): void
    {
        $review = $this->review('ada', new ReviewContent(3, ['rules' => 2], null, null, ['quick']));
        $this->store->repository->add($review);
        $this->store->repository->vote($review->id, Store::member('bob'), true, $this->at);

        $kept = $this->review('cy', new ReviewContent(3, ['rules' => 2], null, null, ['quick']));
        $this->store->repository->add($kept);

        $this->store->repository->remove($review->id);

        foreach (['phil_review', 'phil_review_score', 'phil_review_superlative'] as $table) {
            self::assertSame(1, Columns::int($this->store->connection->fetchOne('SELECT COUNT(*) FROM '.$table)), $table);
        }

        self::assertSame(0, Columns::int($this->store->connection->fetchOne('SELECT COUNT(*) FROM phil_review_vote')));
        self::assertNull($this->store->repository->get($review->id));
    }

    public function testTheRatingsAreComputedFromThePublishedReviews(): void
    {
        $prior = new BayesianPrior(2, 3.0, 1);
        $this->store->repository->add($this->review('ada', new ReviewContent(5, ['rules' => 4], null, null, ['quick', 'family'])));
        $this->store->repository->add($this->review('bob', new ReviewContent(3, ['rules' => 3, 'replayability' => 2], null, null, ['quick'])));

        $withdrawn = $this->review('cy', new ReviewContent(1, ['rules' => 1], null, null, ['thinky']));
        $withdrawn->withdraw('spam', $this->at);

        $this->store->repository->add($withdrawn);
        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game(), Store::member('dan'), new ReviewContent(4), Compensation::none(), $this->at, null, Origin::Transferred));

        $summary = $this->store->ratingRepository->recompute(Store::game(), $prior);

        self::assertSame(3, $summary->count);
        self::assertSame(12, $summary->sum);
        self::assertSame(1, $summary->foreign);
        self::assertFalse($summary->allFromSite());
        self::assertEqualsWithDelta((6 + 12) / 5, $summary->weighted, 1e-9);
        self::assertEqualsCanonicalizing(['family' => 1, 'quick' => 2], $summary->superlatives);
        self::assertSame(3.5, $summary->criteria['rules']->mean);
        self::assertSame(2, $summary->criteria['rules']->count);
        self::assertSame(2.0, $summary->criteria['replayability']->mean);
        self::assertSame(1, $summary->criteria['replayability']->count);
        self::assertEquals($summary, $this->store->ratingRepository->summary(Store::game()));
        self::assertEquals($summary, $this->store->ratingRepository->recompute(Store::game(), $prior));
    }

    public function testASubjectWithoutReviewHasEmptyRatings(): void
    {
        $summary = $this->store->ratingRepository->recompute(Store::game(), new BayesianPrior(2, 3.0, 1));

        self::assertSame(0, $summary->count);
        self::assertSame(0, $summary->sum);
        self::assertNull($summary->weighted);
        self::assertSame([], $summary->criteria);
        self::assertSame([], $summary->superlatives);
        self::assertEquals(new RatingSummary(Store::game('azul')), $this->store->ratingRepository->summary(Store::game('azul')));
    }

    public function testSuspendingKeepsTheRatings(): void
    {
        $prior = new BayesianPrior(2, 3.0, 1);
        $this->store->repository->add($this->review('ada'));
        $this->store->ratingRepository->recompute(Store::game(), $prior);
        $this->store->ratingRepository->suspend(Store::game(), true);

        $summary = $this->store->ratingRepository->summary(Store::game());
        self::assertTrue($summary->suspended);
        self::assertSame(1, $summary->count);
        self::assertTrue($this->store->ratingRepository->recompute(Store::game(), $prior)->suspended);

        $this->store->ratingRepository->suspend(Store::game('azul'), true);
        self::assertTrue($this->store->ratingRepository->summary(Store::game('azul'))->suspended);
        self::assertSame(0, $this->store->ratingRepository->summary(Store::game('azul'))->count);

        $this->store->ratingRepository->suspend(Store::game(), false);
        self::assertFalse($this->store->ratingRepository->summary(Store::game())->suspended);
    }

    public function testTheRankingAndTheSubjects(): void
    {
        $prior = new BayesianPrior(0, 3.0, 0);

        foreach (['b' => 4, 'a' => 4, 'c' => 5] as $id => $score) {
            $this->store->repository->add(Review::write(ReviewId::generate(), Store::game($id), Store::member('ada'), new ReviewContent($score), Compensation::none(), $this->at));
            $this->store->ratingRepository->recompute(Store::game($id), $prior);
        }

        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game('a'), Store::member('bob'), new ReviewContent(4), Compensation::none(), $this->at));
        $this->store->ratingRepository->recompute(Store::game('a'), $prior);
        $this->store->ratingRepository->recompute(Store::game('empty'), $prior);
        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game('z'), Store::member('ada'), new ReviewContent(1), Compensation::none(), $this->at));
        $this->store->repository->add(Review::write(ReviewId::generate(), SubjectRef::of('place', 'pub'), Store::member('ada'), new ReviewContent(1), Compensation::none(), $this->at));

        self::assertSame(['c', 'a', 'b'], array_map(static fn (RatingSummary $s): string => $s->subject->id, $this->store->ratingRepository->ranking('game', 10)));
        self::assertSame(['c'], array_map(static fn (RatingSummary $s): string => $s->subject->id, $this->store->ratingRepository->ranking('game', 1)));
        self::assertSame([], $this->store->ratingRepository->ranking('place', 10));
        self::assertSame(['a', 'b', 'c', 'empty', 'z'], array_map(static fn (SubjectRef $s): string => $s->id, iterator_to_array($this->store->ratingRepository->subjects('game'), false)));
        self::assertSame(['pub'], array_map(static fn (SubjectRef $s): string => $s->id, iterator_to_array($this->store->ratingRepository->subjects('place'), false)));
    }

    public function testTheColumnsNarrowWhatTheDriversGive(): void
    {
        self::assertSame(3, Columns::int('3'));
        self::assertSame(3, Columns::int(3));
        self::assertSame(0, Columns::int(null));
        self::assertSame(0, Columns::int('x'));
        self::assertSame('a', Columns::text('a'));
        self::assertNull(Columns::text(3));
        self::assertSame('3', Columns::string(3));
        self::assertSame('', Columns::string(null));
        $columns = new Columns($this->store->connection);
        self::assertNull($columns->moment(null));
        self::assertTrue($columns->bool(1));
        self::assertFalse($columns->bool(0));
        self::assertSame([], $columns->json(null));
        self::assertSame([], $columns->json('"text"'));
        self::assertSame(['a' => 1], $columns->json('{"a":1}'));
    }

    public function testTheTablesHaveEveryColumnKeyAndIndex(): void
    {
        $schema = ReviewTables::configure(Schema::editor()->create());
        $described = [];

        foreach ($schema->getTables() as $table) {
            $name = $table->getObjectName()->toString();
            $described[] = $name.' primary '.implode(',', array_map(static fn (UnqualifiedName $column): string => $column->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

            foreach ($table->getIndexes() as $index) {
                if ('primary' !== $index->getObjectName()->toString()) {
                    $described[] = $name.' index '.$index->getObjectName()->toString().' '.implode(',', array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns()));
                }
            }

            foreach ($table->getUniqueConstraints() as $constraint) {
                $described[] = $name.' unique '.$constraint->getObjectName()?->toString().' '.implode(',', array_map(static fn (UnqualifiedName $column): string => $column->toString(), $constraint->getColumnNames()));
            }

            foreach ($table->getColumns() as $column) {
                $described[] = \sprintf('%s.%s %s nn=%s len=%s fx=%s', $name, $column->getObjectName()->toString(), $column->getTypeName(), var_export($column->getNotnull(), true), var_export($column->getLength(), true), var_export($column->getFixed(), true));
            }
        }

        sort($described);
        $expected = [
            'phil_review primary id',
            'phil_review index idx_phil_review_subject subject_type,subject_id,status,written_at',
            'phil_review index idx_phil_review_author author',
            'phil_review index idx_phil_review_responder response_by',
            'phil_review unique uniq_phil_review_author subject_type,subject_id,author',
            'phil_review.id string nn=true len=36 fx=true',
            'phil_review.subject_type string nn=true len=64 fx=false',
            'phil_review.subject_id string nn=true len=190 fx=false',
            'phil_review.author string nn=false len=190 fx=false',
            'phil_review.overall smallint nn=true len=NULL fx=false',
            'phil_review.title string nn=false len=150 fx=false',
            'phil_review.body text nn=false len=NULL fx=false',
            'phil_review.compensated boolean nn=true len=NULL fx=false',
            'phil_review.compensation_note string nn=false len=200 fx=false',
            'phil_review.origin string nn=true len=16 fx=false',
            'phil_review.verification string nn=false len=16 fx=false',
            'phil_review.status string nn=true len=16 fx=false',
            'phil_review.removal_criterion string nn=false len=64 fx=false',
            'phil_review.written_at datetime_utc_immutable nn=true len=NULL fx=false',
            'phil_review.edited_at datetime_utc_immutable nn=false len=NULL fx=false',
            'phil_review.withdrawn_at datetime_utc_immutable nn=false len=NULL fx=false',
            'phil_review.response text nn=false len=NULL fx=false',
            'phil_review.response_by string nn=false len=190 fx=false',
            'phil_review.response_at datetime_utc_immutable nn=false len=NULL fx=false',
            'phil_review.contest_evidence text nn=false len=NULL fx=false',
            'phil_review.contest_filed_at datetime_utc_immutable nn=false len=NULL fx=false',
            'phil_review.contest_outcome string nn=false len=16 fx=false',
            'phil_review.contest_settled_at datetime_utc_immutable nn=false len=NULL fx=false',
            'phil_review.helpful_count integer nn=true len=NULL fx=false',
            'phil_review.unhelpful_count integer nn=true len=NULL fx=false',
            'phil_review_score primary review_id,criterion',
            'phil_review_score.review_id string nn=true len=36 fx=true',
            'phil_review_score.criterion string nn=true len=64 fx=false',
            'phil_review_score.score smallint nn=true len=NULL fx=false',
            'phil_review_superlative primary review_id,label',
            'phil_review_superlative.review_id string nn=true len=36 fx=true',
            'phil_review_superlative.label string nn=true len=64 fx=false',
            'phil_review_vote primary review_id,voter',
            'phil_review_vote index idx_phil_review_vote_voter voter',
            'phil_review_vote.review_id string nn=true len=36 fx=true',
            'phil_review_vote.voter string nn=true len=190 fx=false',
            'phil_review_vote.helpful smallint nn=true len=NULL fx=false',
            'phil_review_vote.voted_at datetime_utc_immutable nn=true len=NULL fx=false',
            'phil_review_subject primary subject_type,subject_id',
            'phil_review_subject index idx_phil_review_subject_rank subject_type,weighted',
            'phil_review_subject.subject_type string nn=true len=64 fx=false',
            'phil_review_subject.subject_id string nn=true len=190 fx=false',
            'phil_review_subject.review_count integer nn=true len=NULL fx=false',
            'phil_review_subject.rating_sum integer nn=true len=NULL fx=false',
            'phil_review_subject.foreign_count integer nn=true len=NULL fx=false',
            'phil_review_subject.weighted float nn=false len=NULL fx=false',
            'phil_review_subject.criteria json nn=true len=NULL fx=false',
            'phil_review_subject.superlatives json nn=true len=NULL fx=false',
            'phil_review_subject.suspended boolean nn=true len=NULL fx=false',
            'phil_review_subject.version bigint nn=true len=NULL fx=false',
        ];
        sort($expected);

        self::assertSame($expected, $described);
    }

    public function testAReviewIsFoundByItsSubjectAndAuthorAmongOthers(): void
    {
        $mine = $this->review('ada');
        $this->store->repository->add($mine);
        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game('azul'), Store::member('ada'), new ReviewContent(2), Compensation::none(), $this->at));
        $this->store->repository->add($this->review('bob'));

        self::assertSame($mine->id->value, $this->store->repository->of(Store::game(), Store::member('ada'))?->id->value);
        self::assertNull($this->store->repository->of(SubjectRef::of('place', 'catan'), Store::member('ada')));
        self::assertNull($this->store->repository->of(Store::game('nope'), Store::member('ada')));
        self::assertNull($this->store->repository->of(Store::game(), Store::member('cy')));
    }

    public function testThePublishedReviewsAreListedInTheOrderAsked(): void
    {
        $ada = Review::write(ReviewId::generate(), Store::game(), Store::member('ada'), new ReviewContent(3), Compensation::none(), $this->at);
        $bob = Review::write(ReviewId::generate(), Store::game(), Store::member('bob'), new ReviewContent(5), Compensation::none(), $this->at->modify('+1 hour'));
        $cy = Review::write(ReviewId::generate(), Store::game(), Store::member('cy'), new ReviewContent(1), Compensation::none(), $this->at->modify('+2 hours'));
        $hidden = Review::write(ReviewId::generate(), Store::game(), Store::member('dan'), new ReviewContent(4), Compensation::none(), $this->at->modify('+3 hours'));
        $hidden->withdraw('spam', $this->at);

        foreach ([$ada, $bob, $cy, $hidden] as $review) {
            $this->store->repository->add($review);
        }

        $this->store->repository->vote($bob->id, Store::member('eve'), true, $this->at);
        $this->store->repository->vote($bob->id, Store::member('fay'), true, $this->at);
        $this->store->repository->vote($cy->id, Store::member('eve'), true, $this->at);
        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game('azul'), Store::member('ada'), new ReviewContent(2), Compensation::none(), $this->at));

        $names = fn (ReviewOrder $order, int $limit = 10, int $offset = 0): array => array_map(static fn (Review $review): string => $review->author()->value ?? '', $this->store->repository->published(Store::game(), $order, $limit, $offset));

        self::assertSame(['cy', 'bob', 'ada'], $names(ReviewOrder::Newest));
        self::assertSame(['bob', 'cy', 'ada'], $names(ReviewOrder::Helpful));
        self::assertSame(['bob', 'ada', 'cy'], $names(ReviewOrder::Highest));
        self::assertSame(['cy', 'ada', 'bob'], $names(ReviewOrder::Lowest));
        self::assertSame(['bob'], $names(ReviewOrder::Newest, 1, 1));
        self::assertSame(['cy', 'bob'], array_map(static fn (Review $review): string => $review->author()->value ?? '', $this->store->repository->published(Store::game(), ReviewOrder::Newest, 2)));
        self::assertSame(3, $this->store->repository->countPublished(Store::game()));
    }

    public function testANewReviewStartsWithoutVotes(): void
    {
        $review = $this->review('ada');
        $this->store->repository->add($review);

        self::assertSame(
            [0, 0],
            [Columns::int($this->store->connection->fetchOne('SELECT helpful_count FROM phil_review')), Columns::int($this->store->connection->fetchOne('SELECT unhelpful_count FROM phil_review'))],
        );
    }

    public function testSavingAReviewTouchesOnlyItself(): void
    {
        $first = $this->review('ada', new ReviewContent(4, ['rules' => 3], 'First', null, ['quick']));
        $second = $this->review('bob', new ReviewContent(2, ['replayability' => 1], 'Second', null, ['family']));
        $this->store->repository->add($first);
        $this->store->repository->add($second);

        $first->edit(new ReviewContent(5, ['rules' => 5], 'First again', null, ['thinky']), Compensation::none(), $this->at->modify('+1 day'));
        $this->store->repository->save($first);

        $kept = $this->store->repository->get($second->id);
        self::assertSame(2, $kept?->content()->overall);
        self::assertSame('Second', $kept->content()->title);
        self::assertSame(['replayability' => 1], $kept->content()->criteria);
        self::assertSame(['family'], $kept->content()->superlatives);
        $changed = $this->store->repository->get($first->id);
        self::assertSame(['rules' => 5], $changed?->content()->criteria);
        self::assertSame(['thinky'], $changed->content()->superlatives);
    }

    public function testAVoteIsChangedOnItsReviewOnly(): void
    {
        $first = $this->review('ada');
        $second = $this->review('cy');
        $this->store->repository->add($first);
        $this->store->repository->add($second);
        $this->store->repository->vote($first->id, Store::member('bob'), true, $this->at);
        $this->store->repository->vote($second->id, Store::member('bob'), true, $this->at);

        $this->store->repository->vote($first->id, Store::member('bob'), false, $this->at->modify('+1 hour'));

        self::assertFalse($this->store->repository->voteOf($first->id, Store::member('bob')));
        self::assertTrue($this->store->repository->voteOf($second->id, Store::member('bob')));
        self::assertNull($this->store->repository->voteOf($first->id, Store::member('dan')));
        $reread = $this->store->repository->get($first->id);
        self::assertInstanceOf(Review::class, $reread);
        self::assertSame([0, 1], [$reread->helpful(), $reread->unhelpful()]);
        self::assertSame(1, $this->store->repository->get($second->id)?->helpful());
    }

    public function testForgettingTheResponsesOfAMemberClearsThemAndKeepsOthers(): void
    {
        $mine = $this->review('ada');
        $mine->respond(new Response('Thanks', Store::member('shop'), $this->at));

        $theirs = Review::write(ReviewId::generate(), Store::game('azul'), Store::member('ada'), new ReviewContent(2), Compensation::none(), $this->at);
        $theirs->respond(new Response('Hello', Store::member('other'), $this->at));

        $this->store->repository->add($mine);
        $this->store->repository->add($theirs);

        $this->store->repository->forgetResponses(Store::member('shop'));

        self::assertNull($this->store->connection->fetchOne('SELECT response FROM phil_review WHERE id = ?', [$mine->id->value]));
        self::assertNull($this->store->connection->fetchOne('SELECT response_by FROM phil_review WHERE id = ?', [$mine->id->value]));
        self::assertSame('Hello', $this->store->repository->get($theirs->id)?->response()?->text);
        self::assertSame('other', $this->store->repository->get($theirs->id)->response()->responder?->value);
    }

    public function testAnOpenContestHasNoOutcomeYet(): void
    {
        $review = $this->review('ada');
        $this->store->repository->add($review);
        $review->withdraw('spam', $this->at);
        $review->contestWithdrawal(new Contest('Evidence', $this->at));

        $this->store->repository->save($review);

        $contest = $this->store->repository->get($review->id)?->contest();
        self::assertTrue($contest?->isOpen());
        self::assertNull($contest->outcome);
        self::assertNull($contest->settledAt);
    }

    public function testAHalfWrittenResponseOrContestIsNone(): void
    {
        $review = $this->review('ada');
        $this->store->repository->add($review);
        $moment = '2026-11-21 10:00:00';
        $cases = [
            ['response' => 'Thanks', 'response_at' => null],
            ['response' => null, 'response_at' => $moment],
            ['contest_evidence' => 'Evidence', 'contest_filed_at' => null],
            ['contest_evidence' => null, 'contest_filed_at' => $moment],
        ];

        foreach ($cases as $columns) {
            $this->store->connection->update('phil_review', $columns, ['id' => $review->id->value]);
            $kept = $this->store->repository->get($review->id);

            self::assertNull($kept?->response());
            self::assertInstanceOf(Review::class, $kept);
            self::assertNull($kept->contest());
            $this->store->connection->update('phil_review', array_map(static fn (mixed $value): mixed => null, $columns), ['id' => $review->id->value]);
        }
    }

    public function testResponsesWithoutTextAreNoneInTheRespondedList(): void
    {
        $review = $this->review('ada');
        $this->store->repository->add($review);
        $this->store->connection->update('phil_review', ['response_by' => 'shop'], ['id' => $review->id->value]);

        $responded = $this->store->repository->respondedBy(Store::member('shop'));

        self::assertCount(1, $responded);
        self::assertNull($responded[0]->response());
    }

    public function testRatingsOfNoPublishedReviewAreEmptyAndFromTheSite(): void
    {
        $withdrawn = $this->review('ada');
        $withdrawn->withdraw('spam', $this->at);

        $this->store->repository->add($withdrawn);

        $summary = $this->store->ratingRepository->recompute(Store::game(), new BayesianPrior(2, 3.0, 1));

        self::assertSame([0, 0, 0], [$summary->count, $summary->sum, $summary->foreign]);
        self::assertTrue($summary->allFromSite());
    }

    public function testTheRatingsOfASubjectAreComputedAndSuspendedAlone(): void
    {
        $prior = new BayesianPrior(0, 3.0, 0);
        $this->store->repository->add($this->review('ada'));
        $this->store->repository->add($this->review('bob'));
        $this->store->repository->add(Review::write(ReviewId::generate(), SubjectRef::of('place', 'catan'), Store::member('ada'), new ReviewContent(1), Compensation::none(), $this->at));
        $this->store->ratingRepository->recompute(SubjectRef::of('place', 'catan'), $prior);

        $this->store->ratingRepository->recompute(Store::game(), $prior);
        $this->store->ratingRepository->suspend(Store::game(), true);

        $place = $this->store->ratingRepository->summary(SubjectRef::of('place', 'catan'));
        self::assertSame([1, 1, false], [$place->count, $place->sum, $place->suspended]);
        self::assertSame([2, 6, true], [$this->store->ratingRepository->summary(Store::game())->count, $this->store->ratingRepository->summary(Store::game())->sum, $this->store->ratingRepository->summary(Store::game())->suspended]);
    }

    public function testASuspendedSubjectWithoutReviewStartsAtZero(): void
    {
        $this->store->ratingRepository->suspend(Store::game('azul'), true);

        $summary = $this->store->ratingRepository->summary(Store::game('azul'));

        self::assertSame([0, 0, 0, null, [], []], [$summary->count, $summary->sum, $summary->foreign, $summary->weighted, $summary->criteria, $summary->superlatives]);
    }

    public function testOnlyWellFormedCriteriaAreRead(): void
    {
        $this->store->ratingRepository->suspend(Store::game('azul'), true);
        $this->store->connection->update('phil_review_subject', ['criteria' => '{"a":{"mean":4},"b":"x","c":{"mean":"n"},"0":{"mean":5,"count":1},"d":{"mean":3,"count":2}}', 'superlatives' => '{"quick":2,"0":3}'], ['subject_id' => 'azul']);

        $summary = $this->store->ratingRepository->summary(Store::game('azul'));

        self::assertEquals(['a' => new CriterionRating('a', 4.0, 0), 'd' => new CriterionRating('d', 3.0, 2)], $summary->criteria);
        self::assertSame(['quick' => 2], $summary->superlatives);
    }

    public function testTheRatingsAreReadFromDriversThatGiveEveryValueAsText(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'driverOptions' => [PDO::ATTR_STRINGIFY_FETCHES => true]]);
        Store::create($connection);
        $store = new Store($connection);
        $store->repository->add($this->review('ada', new ReviewContent(4)));
        $store->repository->add($this->review('bob', new ReviewContent(5)));

        $summary = $store->ratingRepository->recompute(Store::game(), new BayesianPrior(2, 3.0, 1));

        self::assertSame([2, 9, 0], [$summary->count, $summary->sum, $summary->foreign]);
        self::assertEqualsWithDelta((6 + 9) / 4, $summary->weighted, 1e-9);
        self::assertFalse($summary->suspended);
    }

    /**
     * @return list<string>
     */
    private function tables(Schema $schema): array
    {
        $names = array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables());
        sort($names);

        return $names;
    }

    private function review(string $author, ?ReviewContent $content = null, ?Compensation $compensation = null, ?Verification $verification = null): Review
    {
        return Review::write(ReviewId::generate(), Store::game(), Store::member($author), $content ?? new ReviewContent(3), $compensation ?? Compensation::none(), $this->at, $verification);
    }
}
