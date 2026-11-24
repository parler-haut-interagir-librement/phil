<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\ReviewsBundle\Application\ErasureMode;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Doctrine\ReviewTables;

/**
 * The reviews' part of an access or erasure request, the data subject being the member's identifier. The export gives
 * the reviews the person wrote, their votes and the responses they wrote. The erasure anonymises or removes their
 * reviews, as the configuration says, removes their votes and their responses, and computes again the ratings of every
 * subject it touched. A data subject that cannot be a member holds nothing here.
 */
final readonly class ReviewPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'reviews';

    public function __construct(
        private ReviewRepository $reviews,
        private Ratings $ratings,
        private ErasureMode $mode = ErasureMode::Anonymise,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);

        if (!$member instanceof MemberId) {
            return [];
        }

        $reviews = $this->reviews->by($member);
        $votes = $this->reviews->votesBy($member);
        $responses = $this->reviews->respondedBy($member);

        if ([] === $reviews && [] === $votes && [] === $responses) {
            return [];
        }

        return [
            'reviews' => array_map($this->review(...), $reviews),
            'votes' => array_map(fn (array $vote): array => ['review' => $vote['review']->value, 'helpful' => $vote['helpful'], 'voted_at' => $this->moment($vote['at'])], $votes),
            'responses' => array_map(fn (Review $review): array => [
                'review' => $review->id->value,
                'text' => $review->response()?->text,
                'responded_at' => $this->moment($review->response()?->at),
            ], $responses),
        ];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);

        if ($member instanceof MemberId) {
            $touched = [];

            foreach ($this->reviews->by($member) as $review) {
                $touched[(string) $review->subject] = $review->subject;

                if (ErasureMode::Remove === $this->mode) {
                    $this->reviews->remove($review->id);
                } else {
                    $review->anonymise();
                    $this->reviews->save($review);
                }
            }

            $this->reviews->forgetVotes($member);

            foreach ($this->reviews->forgetResponses($member) as $answered) {
                $touched[(string) $answered] = $answered;
            }

            foreach ($touched as $touchedSubject) {
                $this->recompute($touchedSubject);
            }
        }

        $reviews = ErasureMode::Remove === $this->mode ? ErasureEffect::Deleted : ErasureEffect::Anonymised;

        return ErasureReport::of(self::MODULE, [
            ReviewTables::REVIEW => $reviews,
            ReviewTables::SCORE => $reviews,
            ReviewTables::SUPERLATIVE => $reviews,
            ReviewTables::VOTE => ErasureEffect::Deleted,
        ]);
    }

    private function recompute(SubjectRef $subject): void
    {
        try {
            $this->ratings->recompute($subject);
        } catch (InvalidReview) {
            // A type no longer declared has no ratings to compute; its reviews are erased all the same.
        }
    }

    private function member(DataSubject $subject): ?MemberId
    {
        try {
            return MemberId::fromString($subject->value);
        } catch (InvalidReview) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function review(Review $review): array
    {
        $content = $review->content();
        $contest = $review->contest();

        return [
            'id' => $review->id->value,
            'subject' => ['type' => $review->subject->type, 'id' => $review->subject->id],
            'overall' => $content->overall,
            'criteria' => $content->criteria,
            'title' => $content->title,
            'body' => $content->body,
            'superlatives' => $content->superlatives,
            'compensated' => $review->compensation()->received,
            'compensation' => $review->compensation()->note,
            'verification' => $review->verification?->value,
            'status' => $review->status()->value,
            'removal_criterion' => $review->removalCriterion(),
            'written_at' => $this->moment($review->writtenAt),
            'edited_at' => $this->moment($review->editedAt()),
            'contest' => $contest instanceof Contest ? [
                'evidence' => $contest->evidence,
                'filed_at' => $this->moment($contest->filedAt),
                'outcome' => $contest->outcome?->value,
            ] : null,
        ];
    }

    private function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(DateTimeInterface::ATOM);
    }
}
