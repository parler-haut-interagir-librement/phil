<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\Status\Verification;
use Phil\ReviewsBundle\Domain\Texts;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * A member's review of a subject: the scores, the text and the superlatives they gave, the compensation they received
 * if any, the mark of a verified purchase or attendance, its state of moderation, the public response of the merchant
 * or the organiser, the contest of a withdrawal, and the votes of helpfulness it received.
 *
 * A review withdrawn names the published criterion it broke, or none while the moderation looks at it. An anonymised
 * review has no author, title or text left: its scores still count.
 */
final class Review
{
    private function __construct(
        public readonly ReviewId $id,
        public readonly SubjectRef $subject,
        private ?MemberId $author,
        private ReviewContent $content,
        private Compensation $compensation,
        public readonly Origin $origin,
        public readonly ?Verification $verification,
        public readonly DateTimeImmutable $writtenAt,
        private ?DateTimeImmutable $editedAt = null,
        private ReviewStatus $status = ReviewStatus::Published,
        private ?string $removalCriterion = null,
        private ?DateTimeImmutable $withdrawnAt = null,
        private ?Response $response = null,
        private ?Contest $contest = null,
        private readonly int $helpful = 0,
        private readonly int $unhelpful = 0,
    ) {
    }

    /**
     * A review, published as it is written.
     */
    public static function write(
        ReviewId $id,
        SubjectRef $subject,
        MemberId $author,
        ReviewContent $content,
        Compensation $compensation,
        DateTimeImmutable $at,
        ?Verification $verification = null,
        Origin $origin = Origin::Site,
    ): self {
        return new self($id, $subject, $author, $content, $compensation, $origin, $verification, $at);
    }

    /**
     * A review as its store keeps it.
     */
    public static function reconstitute(
        ReviewId $id,
        SubjectRef $subject,
        ?MemberId $author,
        ReviewContent $content,
        Compensation $compensation,
        Origin $origin,
        ?Verification $verification,
        DateTimeImmutable $writtenAt,
        ?DateTimeImmutable $editedAt,
        ReviewStatus $status,
        ?string $removalCriterion,
        ?DateTimeImmutable $withdrawnAt,
        ?Response $response,
        ?Contest $contest,
        int $helpful,
        int $unhelpful,
    ): self {
        return new self(
            $id,
            $subject,
            $author,
            $content,
            $compensation,
            $origin,
            $verification,
            $writtenAt,
            $editedAt,
            $status,
            null === $removalCriterion ? null : Texts::code($removalCriterion, 'a removal criterion'),
            $withdrawnAt,
            $response,
            $contest,
            max(0, $helpful),
            max(0, $unhelpful),
        );
    }

    public function author(): ?MemberId
    {
        return $this->author;
    }

    public function content(): ReviewContent
    {
        return $this->content;
    }

    public function compensation(): Compensation
    {
        return $this->compensation;
    }

    public function editedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function status(): ReviewStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return ReviewStatus::Published === $this->status;
    }

    /**
     * Whether the review is withdrawn without a criterion yet: held while the moderation looks at it.
     */
    public function isHeld(): bool
    {
        return ReviewStatus::Withdrawn === $this->status && null === $this->removalCriterion;
    }

    public function removalCriterion(): ?string
    {
        return $this->removalCriterion;
    }

    public function withdrawnAt(): ?DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function response(): ?Response
    {
        return $this->response;
    }

    public function contest(): ?Contest
    {
        return $this->contest;
    }

    public function helpful(): int
    {
        return $this->helpful;
    }

    public function unhelpful(): int
    {
        return $this->unhelpful;
    }

    public function isBy(MemberId $member): bool
    {
        return $this->author instanceof MemberId && $this->author->equals($member);
    }

    /**
     * The author changes what they wrote, a published review only.
     *
     * @throws ReviewRefused when the review is withdrawn or anonymised
     */
    public function edit(ReviewContent $content, Compensation $compensation, DateTimeImmutable $at): void
    {
        if (!$this->isPublished() || !$this->author instanceof MemberId) {
            throw ReviewRefused::because('Only a published review of yours can be changed.');
        }

        $this->content = $content;
        $this->compensation = $compensation;
        $this->editedAt = $at;
    }

    /**
     * Withdraws the review, under the published criterion it broke, or none while the moderation looks at it. A new
     * withdrawal opens a new contest.
     */
    public function withdraw(?string $criterion, DateTimeImmutable $at): void
    {
        if ($this->isPublished()) {
            $this->withdrawnAt = $at;
            $this->contest = null;
        }

        $this->status = ReviewStatus::Withdrawn;
        $this->removalCriterion = null === $criterion ? $this->removalCriterion : Texts::code($criterion, 'a removal criterion');
    }

    /**
     * Publishes the review again, its withdrawal undone; the contest, if any, is kept with its outcome.
     */
    public function publish(): void
    {
        $this->status = ReviewStatus::Published;
        $this->removalCriterion = null;
        $this->withdrawnAt = null;
    }

    public function respond(Response $response): void
    {
        $this->response = $response;
    }

    public function removeResponse(): void
    {
        $this->response = null;
    }

    /**
     * The author contests the withdrawal, once per withdrawal.
     *
     * @throws ReviewRefused when the review is not withdrawn for a criterion, has no author, or was contested already
     */
    public function contestWithdrawal(Contest $contest): void
    {
        if (ReviewStatus::Withdrawn !== $this->status || null === $this->removalCriterion || !$this->author instanceof MemberId) {
            throw ReviewRefused::because('Only a review of yours withdrawn under a criterion can be contested.');
        }

        if ($this->contest instanceof Contest) {
            throw ReviewRefused::because('This withdrawal was contested already.');
        }

        $this->contest = $contest;
    }

    /**
     * Ends the open contest: upheld, the review is published again.
     *
     * @throws ReviewRefused when no contest is open
     */
    public function settleContest(ContestOutcome $outcome, DateTimeImmutable $at): void
    {
        if (!$this->contest instanceof Contest || !$this->contest->isOpen()) {
            throw ReviewRefused::because('No contest of this review is open.');
        }

        $this->contest = $this->contest->settle($outcome, $at);

        if (ContestOutcome::Upheld === $outcome) {
            $this->publish();
        }
    }

    /**
     * Removes what identifies the author: their identifier, title, text, contest and the words of their compensation;
     * the scores, the superlatives and the mark of the compensation stay.
     */
    public function anonymise(): void
    {
        $this->author = null;
        $this->content = new ReviewContent($this->content->overall, $this->content->criteria, null, null, $this->content->superlatives);
        $this->compensation = $this->compensation->withoutNote();
        $this->contest = null;
    }
}
