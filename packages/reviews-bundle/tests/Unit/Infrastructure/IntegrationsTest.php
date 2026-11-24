<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\ReviewsBundle\Application\ErasureMode;
use Phil\ReviewsBundle\Application\IdentifierDirectory;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Moderation\ModerationDecisionListener;
use Phil\ReviewsBundle\Infrastructure\Moderation\ReviewTarget;
use Phil\ReviewsBundle\Infrastructure\Privacy\ReviewPrivacy;
use Phil\ReviewsBundle\Infrastructure\StructuredData\AggregateRatings;
use Phil\ReviewsBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewPrivacy::class)]
#[CoversClass(ReviewTarget::class)]
#[CoversClass(ModerationDecisionListener::class)]
#[CoversClass(AggregateRatings::class)]
final class IntegrationsTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testTheExportGivesTheReviewsVotesAndResponsesOfAMember(): void
    {
        $this->store->responders->members = ['ada'];
        $mine = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4, ['rules' => 3], 'Title', 'Body', ['quick']), Compensation::received('A copy'));
        $other = $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(2));
        $this->store->reviews->vote($other->id, Store::member('ada'), true);
        $this->store->reviews->respond($other->id, Store::member('ada'), 'Thanks');

        $privacy = new ReviewPrivacy($this->store->repository, $this->store->ratings);

        $export = $privacy->export(DataSubject::fromString('ada'));

        self::assertSame('reviews', $privacy->module()->value);
        self::assertSame([
            'id' => $mine->id->value,
            'subject' => ['type' => 'game', 'id' => 'catan'],
            'overall' => 4,
            'criteria' => ['rules' => 3],
            'title' => 'Title',
            'body' => 'Body',
            'superlatives' => ['quick'],
            'compensated' => true,
            'compensation' => 'A copy',
            'verification' => null,
            'status' => 'published',
            'removal_criterion' => null,
            'written_at' => '2026-11-21T10:00:00+00:00',
            'edited_at' => null,
            'contest' => null,
        ], $this->firstReview($export));
        self::assertSame([['review' => $other->id->value, 'helpful' => true, 'voted_at' => '2026-11-21T10:00:00+00:00']], $export['votes']);
        self::assertSame([['review' => $other->id->value, 'text' => 'Thanks', 'responded_at' => '2026-11-21T10:00:00+00:00']], $export['responses']);
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));
    }

    public function testAResponseWithoutTextIsExportedAsEmpty(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(2));
        $this->store->connection->update('phil_review', ['response_by' => 'ada'], ['id' => $review->id->value]);

        $export = new ReviewPrivacy($this->store->repository, $this->store->ratings)->export(DataSubject::fromString('ada'));

        self::assertSame([['review' => $review->id->value, 'text' => null, 'responded_at' => null]], $export['responses'] ?? null);
    }

    public function testTheExportGivesTheContestOfAWithdrawal(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
        $this->store->reviews->withdraw($review->id, 'spam');
        $this->store->reviews->contest($review->id, Store::member('ada'), 'Evidence');

        $export = new ReviewPrivacy($this->store->repository, $this->store->ratings)->export(DataSubject::fromString('ada'));

        $first = $this->firstReview($export);
        self::assertSame(['evidence' => 'Evidence', 'filed_at' => '2026-11-21T10:00:00+00:00', 'outcome' => null], $first['contest']);
        self::assertSame('spam', $first['removal_criterion']);
        self::assertSame('withdrawn', $first['status']);
    }

    public function testTheErasureAnonymisesTheReviewsAndComputesTheRatingsAgain(): void
    {
        $this->store->responders->members = ['ada'];
        $mine = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4, [], 'Title', 'Body'), Compensation::received('A copy'));
        $other = $this->store->reviews->write(Store::game('azul'), Store::member('bob'), new ReviewContent(2));
        $this->store->reviews->vote($other->id, Store::member('ada'), true);
        $this->store->reviews->respond($other->id, Store::member('ada'), 'Thanks');
        $this->store->connection->executeStatement('UPDATE phil_review_subject SET rating_sum = 99');

        $report = new ReviewPrivacy($this->store->repository, $this->store->ratings)->erase(DataSubject::fromString('ada'));

        $kept = $this->store->repository->get($mine->id);
        self::assertNull($kept?->author());
        self::assertNull($kept?->content()->body);
        self::assertNull($kept?->compensation()->note);
        self::assertSame(4, $kept?->content()->overall);
        self::assertSame(0, $this->store->repository->get($other->id)?->helpful());
        self::assertNull($this->store->repository->get($other->id)->response());
        self::assertSame(4, $this->store->ratings->summary(Store::game())->sum);
        self::assertSame(2, $this->store->ratings->summary(Store::game('azul'))->sum);
        self::assertSame([
            'phil_review' => ErasureEffect::Anonymised,
            'phil_review_score' => ErasureEffect::Anonymised,
            'phil_review_superlative' => ErasureEffect::Anonymised,
            'phil_review_vote' => ErasureEffect::Deleted,
        ], $this->effects($report));
        self::assertSame([], new ReviewPrivacy($this->store->repository, $this->store->ratings)->export(DataSubject::fromString('ada')));
    }

    public function testTheErasureRemovesTheReviewsWhenConfiguredSo(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));
        $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(2));

        $privacy = new ReviewPrivacy($this->store->repository, $this->store->ratings, ErasureMode::Remove);

        $report = $privacy->erase(DataSubject::fromString('ada'));

        self::assertSame(1, $this->store->reviews->count(Store::game()));
        self::assertSame(2, $this->store->ratings->summary(Store::game())->sum);
        self::assertSame(ErasureEffect::Deleted, $this->effects($report)['phil_review']);
        self::assertSame(ErasureEffect::Deleted, $this->effects($report)['phil_review_score']);
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('ada')));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('ada@example.org')));
    }

    public function testTheErasureOfAReviewOfATypeNoLongerDeclaredStillErases(): void
    {
        $this->store->repository->add(Review::write(ReviewId::generate(), SubjectRef::of('book', 'dune'), Store::member('ada'), new ReviewContent(4, [], null, 'Body'), Compensation::none(), new DateTimeImmutable()));

        new ReviewPrivacy($this->store->repository, $this->store->ratings)->erase(DataSubject::fromString('ada'));

        self::assertSame([], $this->store->repository->by(Store::member('ada')));
    }

    public function testTheModerationDescribesAndAppliesItsDecisions(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(2, [], 'Bad', 'Broken box'));
        $target = new ReviewTarget($this->store->reviews, new IdentifierDirectory());

        self::assertSame('review', $target->type());
        $view = $target->describe($review->id->value);
        self::assertSame('Review of game catan', $view?->title);
        self::assertSame('Broken box', $view->excerpt);
        self::assertNull($view->url);
        self::assertSame('ada', $view->author?->value);
        self::assertNull($target->describe('nope'));
        self::assertNull($target->describe(ReviewId::generate()->value));

        $target->apply($review->id->value, DecisionAction::Dismiss);
        $target->apply($review->id->value, DecisionAction::Warn);
        self::assertTrue($this->kept($review->id)->isPublished());

        $target->apply($review->id->value, DecisionAction::Hide);
        self::assertTrue($this->kept($review->id)->isHeld());
        self::assertSame(0, $this->store->ratings->summary(Store::game())->count);

        $this->store->reviews->withdraw($review->id, 'spam');
        $target->apply($review->id->value, DecisionAction::Remove);
        self::assertSame('spam', $this->kept($review->id)->removalCriterion());

        $target->apply($review->id->value, DecisionAction::Restore);
        self::assertTrue($this->kept($review->id)->isPublished());
        $target->apply($review->id->value, DecisionAction::Remove);
        $target->apply($review->id->value, DecisionAction::Approve);
        self::assertTrue($this->kept($review->id)->isPublished());

        $target->apply('nope', DecisionAction::Remove);
        $target->apply(ReviewId::generate()->value, DecisionAction::Remove);
    }

    public function testTheModerationDescribesAReviewWithoutText(): void
    {
        $titled = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(2, [], 'Only a title'));
        $bare = $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(5));
        $bare->anonymise();

        $this->store->repository->save($bare);
        $target = new ReviewTarget($this->store->reviews, new IdentifierDirectory());

        self::assertSame('Only a title', $target->describe($titled->id->value)?->excerpt);
        self::assertSame('Overall score: 5', $target->describe($bare->id->value)?->excerpt);
        self::assertNull($target->describe($bare->id->value)->author);
    }

    public function testADecisionWithdrawsUnderTheCriterionItNames(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
        $logger = new RecordingLogger();
        $listener = new ModerationDecisionListener($this->store->reviews, new RemovalCriteria(), $logger);
        $this->store->reviews->hold($review->id);

        $listener(new DecisionTaken($this->decision($review->id->value, DecisionAction::Remove, 'off-topic: about the courier')));

        self::assertSame('off-topic', $this->kept($review->id)->removalCriterion());
        self::assertSame([], $logger->records);
    }

    public function testADecisionWithoutAPublishedCriterionPublishesTheReviewAgain(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
        $logger = new RecordingLogger();
        $listener = new ModerationDecisionListener($this->store->reviews, new RemovalCriteria(), $logger);
        $this->store->reviews->hold($review->id);

        $decision = $this->decision($review->id->value, DecisionAction::Hide, 'Too negative');
        $listener(new DecisionTaken($decision));

        self::assertTrue($this->kept($review->id)->isPublished());
        self::assertSame(1, $this->store->ratings->summary(Store::game())->count);
        self::assertSame([[
            'level' => 'warning',
            'message' => 'A decision of the moderation withdrew a review without naming a published criterion: the review is published again.',
            'context' => ['review' => $review->id->value, 'decision' => $decision->id],
        ]], $logger->records);
    }

    public function testADecisionWithoutACriterionNeedsNoLogger(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
        $this->store->reviews->hold($review->id);

        new ModerationDecisionListener($this->store->reviews, new RemovalCriteria())(new DecisionTaken($this->decision($review->id->value, DecisionAction::Hide, 'Too negative')));

        self::assertTrue($this->kept($review->id)->isPublished());
    }

    public function testTheListenerLeavesOtherDecisionsAlone(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
        $logger = new RecordingLogger();
        $listener = new ModerationDecisionListener($this->store->reviews, new RemovalCriteria(), $logger);
        $this->store->reviews->hold($review->id);

        $listener(new DecisionTaken($this->decision($review->id->value, DecisionAction::Hide, null, 'comment')));
        $listener(new DecisionTaken($this->decision($review->id->value, DecisionAction::Dismiss, null)));
        $listener(new DecisionTaken($this->decision($review->id->value, DecisionAction::Warn, null)));
        $listener(new DecisionTaken($this->decision('nope', DecisionAction::Remove, null)));
        $listener(new DecisionTaken($this->decision(ReviewId::generate()->value, DecisionAction::Remove, null)));
        self::assertTrue($this->kept($review->id)->isHeld());

        $this->store->reviews->publish($review->id);
        $listener(new DecisionTaken($this->decision($review->id->value, DecisionAction::Remove, null)));
        self::assertTrue($this->kept($review->id)->isPublished());
        self::assertSame([], $logger->records);
        self::assertSame(ReviewStatus::Published, $this->kept($review->id)->status());
    }

    public function testTheAggregateRatingOfReviewsAllCollectedOnTheSite(): void
    {
        $ratings = new AggregateRatings($this->store->ratings, $this->store->types);

        self::assertNull($ratings->of(Store::game()));
        $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(5));
        self::assertNull($ratings->of(Store::game()), 'Below the minimum of two reviews.');
        $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(2));

        $node = new AggregateRatings($this->store->ratings, $this->store->types)->of(Store::game());
        self::assertNotNull($node);
        self::assertTrue($node->isA('AggregateRating'));
        self::assertSame(['ratingValue' => 3.5, 'bestRating' => 5, 'worstRating' => 1, 'ratingCount' => 2], $node->properties());
        self::assertNull($ratings->of(SubjectRef::of('book', 'dune')));
        self::assertEquals($node, $ratings->node($this->store->ratings->summary(Store::game())));
    }

    public function testNoAggregateRatingWhenAReviewWasNotCollectedOnTheSite(): void
    {
        $ratings = new AggregateRatings($this->store->ratings, $this->store->types);
        $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(5));
        $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(4));
        $this->store->repository->add(Review::write(ReviewId::generate(), Store::game(), Store::member('cy'), new ReviewContent(4), Compensation::none(), new DateTimeImmutable(), null, Origin::Transferred));
        $this->store->ratings->recompute(Store::game());

        self::assertNull($ratings->of(Store::game()));
    }

    public function testTheRatingValueIsRoundedToTwoDecimals(): void
    {
        $types = new SubjectTypes(new SubjectType('game', minimum: 1));
        $ratings = new AggregateRatings($this->store->ratings, $types);
        $this->store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(5));
        $this->store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(4));
        $this->store->reviews->write(Store::game(), Store::member('cy'), new ReviewContent(4));

        self::assertSame(4.33, $ratings->of(Store::game())?->get('ratingValue'));
    }

    private function kept(ReviewId $id): Review
    {
        $review = $this->store->reviews->get($id);
        self::assertNotNull($review);

        return $review;
    }

    /**
     * @param array<string, mixed> $export
     *
     * @return array<array-key, mixed>
     */
    private function firstReview(array $export): array
    {
        $reviews = $export['reviews'] ?? null;
        self::assertIsArray($reviews);
        $first = $reviews[0] ?? null;
        self::assertIsArray($first);

        return $first;
    }

    private function decision(string $review, DecisionAction $action, ?string $reason, string $type = 'review'): Decision
    {
        return new Decision(bin2hex(random_bytes(16)), TargetReference::of($type, $review), $action, null, 'moderator', $reason, null, null, new DateTimeImmutable());
    }

    /**
     * @return array<string, ErasureEffect>
     */
    private function effects(ErasureReport $report): array
    {
        return $report->effects;
    }
}
