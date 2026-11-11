<?php

declare(strict_types=1);

namespace Phil\Listings;

use DateTimeImmutable;
use Phil\Listings\Exception\ForbiddenTransition;
use Phil\Listings\Exception\InvalidListing;

/**
 * One listing — a classified ad, a job offer, a search for partners — and its life as pure transitions.
 *
 * Each transition takes the policy of the listing's kind and an instant, and returns a new listing; none reads a
 * clock nor stores anything. Who may call which transition — the author, a moderator, the scheduler — is the
 * application's authorization.
 *
 * A listing is visible while it is published and before its expiry; {@see self::at()} turns a published listing
 * past its expiry into an expired one, for a store that keeps statuses current.
 */
final readonly class Listing
{
    /**
     * @param list<MemberId> $reporters
     */
    private function __construct(
        public ListingId $id,
        public ListingKind $kind,
        public MemberId $author,
        public Title $title,
        public Criteria $criteria,
        public Location $location,
        public ?int $minimumAge,
        public ListingStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiresAt,
        public int $extensions,
        public ?DateTimeImmutable $remindedAt,
        public array $reporters,
        public ?ModerationReason $moderationReason,
    ) {
    }

    /**
     * A new listing, seen by its author only. Its minimum age is the older of the one given and the kind's.
     *
     * @throws InvalidListing
     */
    public static function draft(ListingId $id, KindPolicy $policy, MemberId $author, Title $title, Criteria $criteria, Location $location, ?int $minimumAge, DateTimeImmutable $at): self
    {
        return new self($id, $policy->kind, $author, $title, $criteria, $location, self::minimumAge($policy, $minimumAge), ListingStatus::Draft, $at, null, null, 0, null, [], null);
    }

    /**
     * Rebuilds a stored listing.
     *
     * @param list<MemberId> $reporters
     *
     * @throws InvalidListing
     */
    public static function restore(
        ListingId $id,
        ListingKind $kind,
        MemberId $author,
        Title $title,
        Criteria $criteria,
        Location $location,
        ?int $minimumAge,
        ListingStatus $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $expiresAt,
        int $extensions,
        ?DateTimeImmutable $remindedAt,
        array $reporters,
        ?ModerationReason $moderationReason,
    ): self {
        if ($extensions < 0 || \count($reporters) > KindPolicy::MAX_REPORTS_TO_HIDE) {
            throw InvalidListing::because(\sprintf('A stored listing has no negative extension count and at most %d reporters.', KindPolicy::MAX_REPORTS_TO_HIDE));
        }

        return new self($id, $kind, $author, $title, $criteria, $location, $minimumAge, $status, $createdAt, $publishedAt, $expiresAt, $extensions, $remindedAt, $reporters, $moderationReason);
    }

    /**
     * Sends a draft, or a rejected listing, on: to the moderators when its kind is moderated before publication,
     * straight to publication otherwise.
     *
     * @throws ForbiddenTransition
     */
    public function submit(KindPolicy $policy, DateTimeImmutable $at): self
    {
        $this->expect($policy, 'submitted', ListingStatus::Draft, ListingStatus::Rejected);

        return Moderation::BeforePublication === $policy->moderation
            ? $this->with(ListingStatus::PendingReview, null)
            : $this->published($policy, $at);
    }

    /**
     * A moderator approves a pending listing, which is published, or restores a hidden one, whose reports are
     * cleared. A listing published before keeps its expiry while it lies ahead.
     *
     * @throws ForbiddenTransition
     */
    public function approve(KindPolicy $policy, DateTimeImmutable $at): self
    {
        $this->expect($policy, 'approved', ListingStatus::PendingReview, ListingStatus::Hidden);

        return $this->published($policy, $at);
    }

    /**
     * @throws ForbiddenTransition
     */
    public function reject(KindPolicy $policy, ModerationReason $reason): self
    {
        $this->expect($policy, 'rejected', ListingStatus::PendingReview);

        return $this->with(ListingStatus::Rejected, $reason);
    }

    /**
     * A moderator removes a listing for good.
     *
     * @throws ForbiddenTransition
     */
    public function remove(KindPolicy $policy, ModerationReason $reason): self
    {
        $this->expect($policy, 'removed', ListingStatus::PendingReview, ListingStatus::Published, ListingStatus::Hidden, ListingStatus::Expired);

        return $this->with(ListingStatus::Removed, $reason);
    }

    /**
     * A member reports a visible listing. A member's report counts once; the author cannot report their own listing.
     * The report that reaches the kind's threshold hides the listing until a moderator decides.
     *
     * @throws ForbiddenTransition
     */
    public function report(KindPolicy $policy, MemberId $reporter, DateTimeImmutable $at): self
    {
        $this->expect($policy, 'reported', ListingStatus::Published);

        if (!$this->isVisibleAt($at) || $reporter->equals($this->author)) {
            throw ForbiddenTransition::because('Only a visible listing can be reported, and never by its author.');
        }

        foreach ($this->reporters as $already) {
            if ($already->equals($reporter)) {
                return $this;
            }
        }

        $reporters = [...$this->reporters, $reporter];
        $status = \count($reporters) >= $policy->reportsToHide ? ListingStatus::Hidden : ListingStatus::Published;

        return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, $status, $this->createdAt, $this->publishedAt, $this->expiresAt, $this->extensions, $this->remindedAt, $reporters, $this->moderationReason);
    }

    /**
     * The author rewrites the listing. A published listing of a kind moderated before publication goes back to
     * the moderators; any other keeps its status.
     *
     * @throws ForbiddenTransition
     * @throws InvalidListing
     */
    public function edit(KindPolicy $policy, Title $title, Criteria $criteria, Location $location, ?int $minimumAge): self
    {
        $this->expect($policy, 'edited', ListingStatus::Draft, ListingStatus::PendingReview, ListingStatus::Rejected, ListingStatus::Published, ListingStatus::Expired);

        $status = ListingStatus::Published === $this->status && Moderation::BeforePublication === $policy->moderation
            ? ListingStatus::PendingReview
            : $this->status;

        return new self($this->id, $this->kind, $this->author, $title, $criteria, $location, self::minimumAge($policy, $minimumAge), $status, $this->createdAt, $this->publishedAt, $this->expiresAt, $this->extensions, $this->remindedAt, $this->reporters, $this->moderationReason);
    }

    /**
     * The author extends a published or expired listing by the kind's extension, counted from its expiry or from
     * now, whichever is later, as many times as the kind allows. The reminder is due again before the new expiry.
     *
     * @throws ForbiddenTransition
     */
    public function extend(KindPolicy $policy, DateTimeImmutable $at): self
    {
        $this->expect($policy, 'extended', ListingStatus::Published, ListingStatus::Expired);

        if ($this->extensions >= $policy->maxExtensions) {
            throw ForbiddenTransition::because(\sprintf('A listing of the kind "%s" is extended at most %d times.', $this->kind->value, $policy->maxExtensions));
        }

        $from = max($this->expiresAt ?? $at, $at);

        return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, ListingStatus::Published, $this->createdAt, $this->publishedAt, $policy->extendedFrom($from), $this->extensions + 1, null, $this->reporters, $this->moderationReason);
    }

    /**
     * The author closes the listing: filled, found, no longer wanted.
     *
     * @throws ForbiddenTransition
     */
    public function close(KindPolicy $policy): self
    {
        $this->expect($policy, 'closed', ListingStatus::Draft, ListingStatus::PendingReview, ListingStatus::Rejected, ListingStatus::Published, ListingStatus::Hidden, ListingStatus::Expired);

        return $this->with(ListingStatus::Closed, $this->moderationReason);
    }

    /**
     * Applies the transition only time causes: a published listing whose expiry has come is expired.
     */
    public function at(DateTimeImmutable $at): self
    {
        if (ListingStatus::Published !== $this->status || $this->isVisibleAt($at)) {
            return $this;
        }

        return $this->with(ListingStatus::Expired, $this->moderationReason);
    }

    public function isVisibleAt(DateTimeImmutable $at): bool
    {
        return ListingStatus::Published === $this->status && $this->expiresAt instanceof DateTimeImmutable && $at < $this->expiresAt;
    }

    /**
     * Whether the author is due a reminder to extend the listing: it is visible, may still be extended, its expiry
     * is within the kind's reminder days, and no reminder was sent since its last extension.
     */
    public function needsReminderAt(KindPolicy $policy, DateTimeImmutable $at): bool
    {
        return $this->isVisibleAt($at)
            && !$this->remindedAt instanceof DateTimeImmutable
            && $this->extensions < $policy->maxExtensions
            && $this->expiresAt instanceof DateTimeImmutable
            && $at >= $policy->reminderFrom($this->expiresAt);
    }

    /**
     * @throws ForbiddenTransition
     */
    public function markReminded(DateTimeImmutable $at): self
    {
        if (ListingStatus::Published !== $this->status) {
            throw ForbiddenTransition::because('Only a published listing has a reminder to send.');
        }

        return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, $this->status, $this->createdAt, $this->publishedAt, $this->expiresAt, $this->extensions, $at, $this->reporters, $this->moderationReason);
    }

    /**
     * Whether a member of this age may respond; an unknown age never may when the listing has a minimum age.
     */
    public function admitsAge(?int $age): bool
    {
        return null === $this->minimumAge || (null !== $age && $age >= $this->minimumAge);
    }

    /**
     * The location as one viewer may see it: whole for the author and for a respondent the author accepted, the
     * area alone for anyone else.
     */
    public function locationFor(MemberId $viewer, bool $acceptedRespondent): Location
    {
        return $acceptedRespondent || $viewer->equals($this->author) ? $this->location : $this->location->approximate();
    }

    private static function minimumAge(KindPolicy $policy, ?int $minimumAge): ?int
    {
        if (null !== $minimumAge && ($minimumAge < 0 || $minimumAge > KindPolicy::MAX_AGE)) {
            throw InvalidListing::because(\sprintf('A minimum age is from 0 to %d; %d is not.', KindPolicy::MAX_AGE, $minimumAge));
        }

        $ages = array_filter([$policy->minimumAge, $minimumAge], static fn (?int $age): bool => null !== $age);

        return [] === $ages ? null : max($ages);
    }

    private function expect(KindPolicy $policy, string $transition, ListingStatus ...$from): void
    {
        if (!$policy->kind->equals($this->kind)) {
            throw InvalidListing::because(\sprintf('The listing is of the kind "%s", not "%s".', $this->kind->value, $policy->kind->value));
        }

        if (!\in_array($this->status, $from, true)) {
            throw ForbiddenTransition::because(\sprintf('A listing that is %s cannot be %s.', $this->status->value, $transition));
        }
    }

    private function published(KindPolicy $policy, DateTimeImmutable $at): self
    {
        if ($this->expiresAt instanceof DateTimeImmutable && $this->expiresAt > $at) {
            return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, ListingStatus::Published, $this->createdAt, $this->publishedAt, $this->expiresAt, $this->extensions, $this->remindedAt, [], null);
        }

        return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, ListingStatus::Published, $this->createdAt, $this->publishedAt ?? $at, $policy->expiryFrom($at), $this->extensions, null, [], null);
    }

    private function with(ListingStatus $status, ?ModerationReason $reason): self
    {
        return new self($this->id, $this->kind, $this->author, $this->title, $this->criteria, $this->location, $this->minimumAge, $status, $this->createdAt, $this->publishedAt, $this->expiresAt, $this->extensions, $this->remindedAt, $this->reporters, $reason);
    }
}
