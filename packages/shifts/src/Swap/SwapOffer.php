<?php

declare(strict_types=1);

namespace Phil\Shifts\Swap;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Identifier;

/**
 * A place put on the swap board by the volunteer who holds it, with a note of 500 characters at most. Another
 * volunteer takes it; the place passes at once, or once a coordinator approves when the shift asks for it. A
 * coordinator who refuses puts the offer back on the board. An offer is immutable: each operation returns the next one.
 *
 * The swap board reads the roster at each step: an offer whose volunteer no longer holds the place is stale, and
 * nobody takes it.
 */
final readonly class SwapOffer
{
    public DateTimeImmutable $createdAt;

    public ?DateTimeImmutable $decidedAt;

    /**
     * @throws InvalidShift when a value is outside its bounds
     */
    public function __construct(
        public string $id,
        public string $shiftId,
        public string $offeredBy,
        DateTimeImmutable $createdAt,
        public string $note = '',
        public SwapStatus $status = SwapStatus::Open,
        public ?string $takenBy = null,
        public ?string $decidedBy = null,
        ?DateTimeImmutable $decidedAt = null,
    ) {
        Identifier::key($id, 'swap offer');
        Identifier::key($shiftId, 'shift');
        Identifier::volunteer($offeredBy);
        Identifier::text($note, 'note', 500, false);

        if (null !== $takenBy) {
            Identifier::volunteer($takenBy);
        }

        if (null !== $decidedBy) {
            Identifier::volunteer($decidedBy);
        }

        if (SwapStatus::Cancelled !== $status && (SwapStatus::Open === $status) !== (null === $takenBy)) {
            throw InvalidShift::because('An open offer is taken by nobody, a waiting or completed one by a volunteer.');
        }

        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));
        $this->decidedAt = $decidedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * A volunteer who holds a place of an open shift offers it.
     *
     * @throws ShiftRefused when the shift is closed or the volunteer holds no place on it
     */
    public static function offer(string $id, Roster $roster, string $volunteer, string $note, DateTimeImmutable $now): self
    {
        if (!$roster->shift->isOpenAt($now)) {
            throw ShiftRefused::because(ShiftRefused::CLOSED);
        }

        if (PlaceStatus::Confirmed !== $roster->statusOf($volunteer)) {
            throw ShiftRefused::because(ShiftRefused::NOT_CONFIRMED);
        }

        return new self($id, $roster->shift->id, $volunteer, $now, $note);
    }

    /**
     * Another volunteer takes the offer: it completes when the shift does not ask for a coordinator, else it waits.
     *
     * @param list<string> $roles the roles of the volunteer who takes it
     *
     * @throws ShiftRefused when the offer is not open or stale, the roster is another shift's, the shift is closed or
     *                      requires a role, or the volunteer offered it or holds a place already
     */
    public function take(Roster $roster, string $volunteer, array $roles, DateTimeImmutable $now): self
    {
        Identifier::volunteer($volunteer);

        if (SwapStatus::Open !== $this->status) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_OPEN);
        }

        $this->assertCurrent($roster, $now);

        if ($volunteer === $this->offeredBy) {
            throw ShiftRefused::because(ShiftRefused::SWAP_OWN_OFFER);
        }

        if (!$roster->shift->admits($roles)) {
            throw ShiftRefused::because(ShiftRefused::ROLE_REQUIRED);
        }

        if (PlaceStatus::Confirmed === $roster->statusOf($volunteer)) {
            throw ShiftRefused::because(ShiftRefused::ALREADY_ON_ROSTER);
        }

        $status = $roster->shift->swapApproval ? SwapStatus::Pending : SwapStatus::Completed;
        $decided = SwapStatus::Completed === $status ? $now : null;

        return new self($this->id, $this->shiftId, $this->offeredBy, $this->createdAt, $this->note, $status, $volunteer, null, $decided);
    }

    /**
     * A coordinator approves a taken offer.
     *
     * @throws ShiftRefused when the offer waits for nobody, is stale, or the shift is closed
     */
    public function approve(Roster $roster, string $coordinator, DateTimeImmutable $now): self
    {
        $this->assertPending($roster, $now);

        return new self($this->id, $this->shiftId, $this->offeredBy, $this->createdAt, $this->note, SwapStatus::Completed, $this->takenBy, Identifier::volunteer($coordinator), $now);
    }

    /**
     * A coordinator refuses a taken offer, which goes back on the board for another volunteer.
     *
     * @throws ShiftRefused when the offer waits for nobody
     */
    public function refuse(string $coordinator, DateTimeImmutable $now): self
    {
        Identifier::volunteer($coordinator);

        if (SwapStatus::Pending !== $this->status) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_PENDING);
        }

        return new self($this->id, $this->shiftId, $this->offeredBy, $this->createdAt, $this->note, SwapStatus::Open, null, $coordinator, $now);
    }

    /**
     * The volunteer who offered the place withdraws the offer, open or waiting.
     *
     * @throws ShiftRefused when the offer is no longer active or is another volunteer's
     */
    public function cancel(string $volunteer, DateTimeImmutable $now): self
    {
        if (!$this->status->isActive()) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_OPEN);
        }

        if ($volunteer !== $this->offeredBy) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_YOURS);
        }

        return new self($this->id, $this->shiftId, $this->offeredBy, $this->createdAt, $this->note, SwapStatus::Cancelled, $this->takenBy, $volunteer, $now);
    }

    /**
     * The roster once a completed offer passed its place.
     *
     * @throws ShiftRefused when the offer is not completed, is stale, or the shift is closed
     */
    public function apply(Roster $roster, DateTimeImmutable $now): Roster
    {
        if (SwapStatus::Completed !== $this->status || null === $this->takenBy) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_PENDING);
        }

        $this->assertCurrent($roster, $now);

        return $roster->transfer($this->offeredBy, $this->takenBy, $now);
    }

    /**
     * Whether the volunteer who offered the place no longer holds it.
     */
    public function isStale(Roster $roster): bool
    {
        return $roster->shift->id !== $this->shiftId || PlaceStatus::Confirmed !== $roster->statusOf($this->offeredBy);
    }

    private function assertPending(Roster $roster, DateTimeImmutable $now): void
    {
        if (SwapStatus::Pending !== $this->status) {
            throw ShiftRefused::because(ShiftRefused::SWAP_NOT_PENDING);
        }

        $this->assertCurrent($roster, $now);
    }

    private function assertCurrent(Roster $roster, DateTimeImmutable $now): void
    {
        if ($roster->shift->id !== $this->shiftId) {
            throw ShiftRefused::because(ShiftRefused::SWAP_OTHER_SHIFT);
        }

        if (!$roster->shift->isOpenAt($now)) {
            throw ShiftRefused::because(ShiftRefused::CLOSED);
        }

        if ($this->isStale($roster)) {
            throw ShiftRefused::because(ShiftRefused::SWAP_STALE);
        }
    }
}
