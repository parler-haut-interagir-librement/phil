<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\ValueObject\Member;
use Phil\Reservation\Booking;
use Phil\Reservation\Interval;
use Symfony\Component\Uid\Uuid;

/**
 * A copy lent to a member for a span: reserved ahead, or lent at once at the desk; active until it comes back, is
 * renewed, or is lost. The member is the application's stable identifier — never an e-mail address — and is forgotten
 * when the member's data is erased or the history is cleared, the loan staying as a count.
 *
 * Moments are kept in UTC by their columns' type. `lastReminder` is the offset, in minutes before the due date, of the
 * last reminder sent; `notices` counts the notices of a loan overdue, so that each goes once.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_library_loan_copy', columns: ['copy', 'status', 'due_at'])]
#[ORM\Index(name: 'phil_library_loan_member', columns: ['member', 'status'])]
#[ORM\Index(name: 'phil_library_loan_due', columns: ['status', 'due_at'])]
final class Loan
{
    public const string TABLE = 'phil_library_loan';

    #[ORM\Column(name: 'checked_out_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $checkedOutAt = null;

    #[ORM\Column(name: 'closed_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'renewals', type: 'smallint')]
    private int $renewals = 0;

    #[ORM\Column(name: 'last_reminder', type: 'integer', nullable: true)]
    private ?int $lastReminder = null;

    #[ORM\Column(name: 'notices', type: 'smallint')]
    private int $notices = 0;

    #[ORM\Column(name: 'last_notice_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $lastNoticeAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Copy::class)]
        #[ORM\JoinColumn(name: 'copy', nullable: false, onDelete: 'CASCADE')]
        private Copy $copy,
        #[ORM\Column(name: 'member', length: 128, nullable: true)]
        private ?string $member,
        #[ORM\Column(name: 'status', length: 16, enumType: LoanStatus::class)]
        private LoanStatus $status,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'due_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $dueAt,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * The copy kept for the member over a span to come, until it is checked out at the desk.
     *
     * @throws InvalidLibraryData when the member is not a stable identifier, or the span has begun
     */
    public static function reserve(Copy $copy, string $member, Interval $span, DateTimeImmutable $now): self
    {
        if ($span->from <= $now->getTimestamp()) {
            throw InvalidLibraryData::because('A reservation is for a span to come; a loan that starts now is lent.');
        }

        return new self(Uuid::v7()->toRfc4122(), $copy, Member::check($member), LoanStatus::Reserved, self::utc($span->start), self::utc($span->end), self::utc($now));
    }

    /**
     * The copy lent now, until a due date.
     *
     * @throws InvalidLibraryData when the member is not a stable identifier, or the due date has come
     */
    public static function lend(Copy $copy, string $member, DateTimeImmutable $due, DateTimeImmutable $now): self
    {
        if ($due->getTimestamp() <= $now->getTimestamp()) {
            throw InvalidLibraryData::because('A loan is due after it starts.');
        }

        $loan = new self(Uuid::v7()->toRfc4122(), $copy, Member::check($member), LoanStatus::Active, self::utc($now), self::utc($due), self::utc($now));
        $loan->checkedOutAt = self::utc($now);

        return $loan;
    }

    public function copy(): Copy
    {
        return $this->copy;
    }

    /**
     * The member it is for, or null once forgotten.
     */
    public function member(): ?string
    {
        return $this->member;
    }

    public function status(): LoanStatus
    {
        return $this->status;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function checkedOutAt(): ?DateTimeImmutable
    {
        return $this->checkedOutAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function renewals(): int
    {
        return $this->renewals;
    }

    public function lastReminder(): ?int
    {
        return $this->lastReminder;
    }

    public function notices(): int
    {
        return $this->notices;
    }

    public function lastNoticeAt(): ?DateTimeImmutable
    {
        return $this->lastNoticeAt;
    }

    /**
     * Whether the copy is still out after its due date.
     */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return LoanStatus::Active === $this->status && $now->getTimestamp() >= $this->dueAt->getTimestamp();
    }

    /**
     * Its span, from its start to its due date.
     */
    public function span(): Interval
    {
        return new Interval($this->startsAt, $this->dueAt);
    }

    /**
     * What `phil/reservation` checks another loan of the copy against: its span, which a loan overdue stretches to a
     * minute from now, since the copy is not back.
     */
    public function booking(DateTimeImmutable $now): Booking
    {
        $end = $this->isOverdue($now) ? self::utc($now)->modify('+1 minute') : $this->dueAt;

        return new Booking($this->id, new Interval($this->startsAt, $end));
    }

    /**
     * A reservation whose copy is handed over: active from now, its due date kept.
     *
     * @throws InvalidLibraryData when it is not reserved, or its due date has come
     */
    public function checkOut(DateTimeImmutable $now): void
    {
        $this->assertStatus(LoanStatus::Reserved, 'checked out');

        if ($this->dueAt->getTimestamp() <= $now->getTimestamp()) {
            throw InvalidLibraryData::because('A reservation whose due date has come is not checked out: cancel it and lend the copy.');
        }

        $this->status = LoanStatus::Active;
        $this->startsAt = $now->getTimestamp() < $this->startsAt->getTimestamp() ? self::utc($now) : $this->startsAt;
        $this->checkedOutAt = self::utc($now);
    }

    /**
     * @throws InvalidLibraryData when it is not active
     */
    public function giveBack(DateTimeImmutable $now): void
    {
        $this->assertStatus(LoanStatus::Active, 'returned');
        $this->status = LoanStatus::Returned;
        $this->closedAt = self::utc($now);
    }

    /**
     * @throws InvalidLibraryData when it is not reserved
     */
    public function cancel(DateTimeImmutable $now): void
    {
        $this->assertStatus(LoanStatus::Reserved, 'cancelled');
        $this->status = LoanStatus::Cancelled;
        $this->closedAt = self::utc($now);
    }

    /**
     * @throws InvalidLibraryData when it is not active
     */
    public function lose(DateTimeImmutable $now): void
    {
        $this->assertStatus(LoanStatus::Active, 'lost');
        $this->status = LoanStatus::Lost;
        $this->closedAt = self::utc($now);
    }

    /**
     * Pushes its due date back: the reminders and notices start over.
     *
     * @throws InvalidLibraryData when it is not active, or the new due date is not later
     */
    public function renew(DateTimeImmutable $due): void
    {
        $this->assertStatus(LoanStatus::Active, 'renewed');

        if ($due->getTimestamp() <= $this->dueAt->getTimestamp()) {
            throw InvalidLibraryData::because('A loan renewed is due later than it was.');
        }

        $this->dueAt = self::utc($due);
        ++$this->renewals;
        $this->lastReminder = null;
        $this->notices = 0;
        $this->lastNoticeAt = null;
    }

    /**
     * Records the reminder sent before the due date, by its offset in minutes.
     */
    public function remind(int $offset): void
    {
        $this->lastReminder = $offset;
    }

    /**
     * Records a notice of the loan overdue.
     */
    public function notice(DateTimeImmutable $now): void
    {
        ++$this->notices;
        $this->lastNoticeAt = self::utc($now);
    }

    /**
     * Forgets the member: the loan stays, nobody's.
     */
    public function forgetMember(): void
    {
        $this->member = null;
    }

    /**
     * @throws InvalidLibraryData when it is not in the state the change needs
     */
    private function assertStatus(LoanStatus $status, string $change): void
    {
        if ($status !== $this->status) {
            throw InvalidLibraryData::because(\sprintf('A loan %s is not %s.', $this->status->value, $change));
        }
    }

    private static function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }
}
