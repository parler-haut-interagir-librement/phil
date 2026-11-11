<?php

declare(strict_types=1);

namespace Phil\Listings;

use DateTimeImmutable;
use Phil\Listings\Exception\ForbiddenTransition;

/**
 * One member's response to a listing, and its life as pure transitions.
 *
 * A response carries no message: sending it is the respondent's consent to be put in touch, accepting it is the
 * author's, and only an accepted response lets the application open a conversation between them.
 */
final readonly class Response
{
    private function __construct(
        public ResponseId $id,
        public ListingId $listing,
        public MemberId $respondent,
        public ResponseStatus $status,
        public DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $decidedAt,
    ) {
    }

    public static function pending(ResponseId $id, ListingId $listing, MemberId $respondent, DateTimeImmutable $at): self
    {
        return new self($id, $listing, $respondent, ResponseStatus::Pending, $at, null);
    }

    /**
     * Rebuilds a stored response.
     */
    public static function restore(ResponseId $id, ListingId $listing, MemberId $respondent, ResponseStatus $status, DateTimeImmutable $sentAt, ?DateTimeImmutable $decidedAt): self
    {
        return new self($id, $listing, $respondent, $status, $sentAt, $decidedAt);
    }

    /**
     * @throws ForbiddenTransition
     */
    public function accept(DateTimeImmutable $at): self
    {
        return $this->decided(ResponseStatus::Accepted, $at, ResponseStatus::Pending);
    }

    /**
     * @throws ForbiddenTransition
     */
    public function decline(DateTimeImmutable $at): self
    {
        return $this->decided(ResponseStatus::Declined, $at, ResponseStatus::Pending);
    }

    /**
     * The respondent takes the response back, pending or accepted: their consent ends with it.
     *
     * @throws ForbiddenTransition
     */
    public function withdraw(DateTimeImmutable $at): self
    {
        return $this->decided(ResponseStatus::Withdrawn, $at, ResponseStatus::Pending, ResponseStatus::Accepted);
    }

    /**
     * Whether the response still counts against its listing's open responses: pending or accepted.
     */
    public function isOpen(): bool
    {
        return ResponseStatus::Pending === $this->status || ResponseStatus::Accepted === $this->status;
    }

    /**
     * Whether both members consent to be put in touch.
     */
    public function permitsContact(): bool
    {
        return ResponseStatus::Accepted === $this->status;
    }

    private function decided(ResponseStatus $status, DateTimeImmutable $at, ResponseStatus ...$from): self
    {
        if (!\in_array($this->status, $from, true)) {
            throw ForbiddenTransition::because(\sprintf('A response that is %s cannot become %s.', $this->status->value, $status->value));
        }

        return new self($this->id, $this->listing, $this->respondent, $status, $this->sentAt, $at);
    }
}
