<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application;

use Closure;
use DateTimeImmutable;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\LibraryBundle\Application\Exception\LoanRefused;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Domain\Contract\CopyRepository;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Event\CopyLent;
use Phil\LibraryBundle\Domain\Event\CopyReturned;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\ValueObject\Member;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Interval;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The loans of the copies, on `phil/reservation`: each copy a resource of one unit, each loan a booking of it, so that
 * a copy lent or reserved over a span is not lent again over it. A loan is lent at the desk or reserved ahead and
 * checked out, renewed, returned with the condition the copy is in, cancelled or lost; a member with a copy overdue, or
 * holding as many loans as allowed, borrows no more.
 *
 * When a copy is an item of a stockroom, handing it over moves the item out to the loan, its return brings it back, its
 * loss records it lost. Each loan claims its copy, so that two loans of one copy made at once are not both kept. With a
 * transaction, the move of the item and the loan are written together or not at all; without one, the stock moves
 * first, and a stockroom that refuses stops the loan.
 */
final readonly class Loans
{
    public function __construct(
        private CopyRepository $copies,
        private LoanRepository $loans,
        private StockLink $stock,
        private LoanPolicy $policy,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private ?LoanTransaction $transaction = null,
    ) {
    }

    /**
     * Lends a copy now, until the due date given or the policy's.
     *
     * @throws NotFound           when no copy carries the barcode
     * @throws InvalidLibraryData when the member is not a stable identifier or the due date has come
     * @throws LoanRefused        when the copy or the member may not borrow
     */
    public function lend(string $barcode, string $member, ?DateTimeImmutable $due = null): Loan
    {
        return $this->transactional(function () use ($barcode, $member, $due): Loan {
            $now = $this->clock->now();
            $copy = $this->copies->byBarcode($barcode) ?? throw NotFound::copy($barcode);
            $this->assertMemberMay(Member::check($member), $now);
            $due ??= $this->policy->dueFrom($now);

            if ($due->getTimestamp() <= $now->getTimestamp()) {
                throw InvalidLibraryData::because('A loan is due after it starts.');
            }

            $this->assertFree($copy, new Interval($now, $due), $now);
            $loan = Loan::lend($copy, $member, $due, $now);
            $this->handOver($loan);

            return $loan;
        });
    }

    /**
     * Reserves a copy for a span to come.
     *
     * @throws NotFound           when the copy does not exist
     * @throws InvalidLibraryData when the member is not a stable identifier or the span has begun
     * @throws LoanRefused        when the copy or the member may not borrow then
     */
    public function reserve(string $copy, string $member, DateTimeImmutable $start, DateTimeImmutable $due): Loan
    {
        $now = $this->clock->now();
        $found = $this->copies->get($copy) ?? throw NotFound::copy($copy);
        $this->assertMemberMay(Member::check($member), $now);
        $span = new Interval($start, $due);
        $loan = Loan::reserve($found, $member, $span, $now);
        $this->assertFree($found, $span, $now);
        $found->claim();
        $this->loans->save($loan);

        return $loan;
    }

    /**
     * Hands over a reserved copy.
     *
     * @throws NotFound           when the loan does not exist
     * @throws InvalidLibraryData when it is not reserved, or its due date has come
     * @throws LoanRefused        when the copy is withdrawn, or still out with another member
     */
    public function checkOut(string $loan): Loan
    {
        return $this->transactional(function () use ($loan): Loan {
            $now = $this->clock->now();
            $found = $this->get($loan);

            if (LoanStatus::Reserved !== $found->status()) {
                throw InvalidLibraryData::because(\sprintf('A loan %s is not checked out.', $found->status()->value));
            }

            $this->assertFree($found->copy(), new Interval($now, $found->dueAt()), $now, $found->id);
            $found->checkOut($now);
            $this->handOver($found);

            return $found;
        });
    }

    /**
     * Takes back the copy that is out, recording the condition it is in and a note on it when they are given.
     *
     * @throws NotFound           when no copy carries the barcode, or it is not out
     * @throws InvalidLibraryData when the note is not one
     */
    public function giveBack(string $barcode, ?CopyCondition $condition = null, ?string $note = null): Loan
    {
        return $this->transactional(function () use ($barcode, $condition, $note): Loan {
            $now = $this->clock->now();
            $copy = $this->copies->byBarcode($barcode) ?? throw NotFound::copy($barcode);
            $loan = $this->loans->activeOf($copy) ?? throw NotFound::notOut($barcode);

            if ($condition instanceof CopyCondition) {
                $copy->assess($condition, $note);
            }

            $item = $copy->stockroomItem();

            if (null !== $item) {
                $this->stock->back($item, $loan->id);
            }

            $loan->giveBack($now);
            $this->loans->save($loan);
            $this->events?->dispatch(new CopyReturned($loan->id, $copy->id, $copy->condition()));

            return $loan;
        });
    }

    /**
     * Pushes the due date of a loan back by a loan's length, from its due date.
     *
     * @throws NotFound           when the loan does not exist
     * @throws InvalidLibraryData when it is not active
     * @throws LoanRefused        when it was renewed as many times as allowed, or the copy is reserved after it
     */
    public function renew(string $loan): Loan
    {
        $now = $this->clock->now();
        $found = $this->get($loan);

        if (LoanStatus::Active !== $found->status()) {
            throw InvalidLibraryData::because(\sprintf('A loan %s is not renewed.', $found->status()->value));
        }

        if ($found->renewals() >= $this->policy->maxRenewals) {
            throw LoanRefused::because('renewals', ['max' => $this->policy->maxRenewals]);
        }

        $due = $this->policy->dueFrom($found->dueAt());
        $this->assertFree($found->copy(), new Interval($found->startsAt(), $due), $now, $found->id);
        $found->renew($due);
        $found->copy()->claim();
        $this->loans->save($found);

        return $found;
    }

    /**
     * Cancels a reservation.
     *
     * @throws NotFound           when the loan does not exist
     * @throws InvalidLibraryData when it is not reserved
     */
    public function cancel(string $loan): Loan
    {
        $found = $this->get($loan);
        $found->cancel($this->clock->now());

        $this->loans->save($found);

        return $found;
    }

    /**
     * Records a copy out as lost: the loan closes, the copy is withdrawn with the note given.
     *
     * @throws NotFound           when the loan does not exist
     * @throws InvalidLibraryData when it is not active, or the note is not one
     */
    public function lose(string $loan, ?string $note = null): Loan
    {
        return $this->transactional(function () use ($loan, $note): Loan {
            $found = $this->get($loan);
            $copy = $found->copy();
            $found->lose($this->clock->now());
            $copy->assess(CopyCondition::Withdrawn, $note);
            $item = $copy->stockroomItem();

            if (null !== $item) {
                $this->stock->lost($item, $found->id);
            }

            $this->loans->save($found);

            return $found;
        });
    }

    /**
     * Whether a copy may be lent over a span: not withdrawn, and kept by no other loan.
     */
    public function isFree(Copy $copy, Interval $span): bool
    {
        return $copy->mayLend() && 1 === $this->policy->bookable()->free($span, $this->bookings($copy, $this->clock->now()));
    }

    /**
     * The loans that keep a copy now or later, the earliest first.
     *
     * @return list<Loan>
     */
    public function holding(Copy $copy): array
    {
        return $this->loans->holding($copy);
    }

    /**
     * A member's loans, the latest first.
     *
     * @return list<Loan>
     *
     * @throws InvalidLibraryData when the member is not a stable identifier
     */
    public function ofMember(string $member, int $limit = 100, bool $open = false): array
    {
        return $this->loans->ofMember(Member::check($member), max(1, min($limit, 1000)), $open);
    }

    /**
     * Forgets the member of the loans closed longer ago than the policy keeps them; none when it keeps them forever.
     *
     * @return int the loans changed
     */
    public function forgetHistory(): int
    {
        if (null === $this->policy->historyDays) {
            return 0;
        }

        return $this->loans->forgetClosedBefore($this->clock->now()->modify(\sprintf('-%d days', $this->policy->historyDays)));
    }

    /**
     * @throws NotFound when the loan does not exist
     */
    public function get(string $loan): Loan
    {
        return $this->loans->get($loan) ?? throw NotFound::loan($loan);
    }

    /**
     * @param Closure(): Loan $work
     */
    private function transactional(Closure $work): Loan
    {
        return $this->transaction instanceof LoanTransaction ? $this->transaction->run($work) : $work();
    }

    /**
     * Moves the stockroom item, claims the copy, keeps the loan and says so.
     */
    private function handOver(Loan $loan): void
    {
        $copy = $loan->copy();
        $item = $copy->stockroomItem();

        if (null !== $item) {
            $this->stock->out($item, $loan->id);
        }

        $copy->claim();
        $this->loans->save($loan);
        $this->events?->dispatch(new CopyLent($loan->id, $copy->id));
    }

    /**
     * @throws LoanRefused when the member has a copy overdue and the policy blocks them, or holds as many loans as allowed
     */
    private function assertMemberMay(string $member, DateTimeImmutable $now): void
    {
        if ($this->policy->blockWhenOverdue && $this->loans->hasOverdue($member, $now)) {
            throw LoanRefused::because('overdue');
        }

        if ($this->loans->countOpen($member) >= $this->policy->maxOpenLoans) {
            throw LoanRefused::because('limit', ['max' => $this->policy->maxOpenLoans]);
        }
    }

    /**
     * @throws LoanRefused when the copy is withdrawn, another loan keeps it over the span, or the span breaks a rule
     */
    private function assertFree(Copy $copy, Interval $span, DateTimeImmutable $now, ?string $replacing = null): void
    {
        if (!$copy->mayLend()) {
            throw LoanRefused::because('withdrawn');
        }

        $decision = $this->policy->bookable()->check(new BookingRequest($span, $now, 1, $replacing), $this->bookings($copy, $now));

        foreach ([ViolationCode::TooFarAhead->value => 'too_far_ahead', ViolationCode::TooLong->value => 'too_long', ViolationCode::Full->value => 'taken'] as $code => $reason) {
            $violation = array_find($decision->violations, static fn (Violation $violation): bool => $code === $violation->code->value);

            if (null !== $violation) {
                throw LoanRefused::because($reason, 'taken' === $reason ? [] : $violation->parameters);
            }
        }
    }

    /**
     * @return list<Booking>
     */
    private function bookings(Copy $copy, DateTimeImmutable $now): array
    {
        return array_map(static fn (Loan $loan): Booking => $loan->booking($now), $this->loans->holding($copy));
    }
}
