<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;

/**
 * Where the loans are kept, and the lists the desk, the reminders and the member's page read.
 */
interface LoanRepository
{
    public function get(string $id): ?Loan;

    /**
     * Keeps the loan, and the copy it claims, in one write.
     */
    public function save(Loan $loan): void;

    /**
     * The loans that keep a copy — reserved or active —, the earliest first.
     *
     * @return list<Loan>
     */
    public function holding(Copy $copy): array;

    /**
     * The loan of a copy that is out now, if any.
     */
    public function activeOf(Copy $copy): ?Loan;

    /**
     * A member's loans, the latest first, at most so many; the reserved and active ones only when `$open`.
     *
     * @return list<Loan>
     */
    public function ofMember(string $member, int $limit, bool $open = false): array;

    /**
     * How many loans a member holds: reserved or active.
     */
    public function countOpen(string $member): int;

    /**
     * Whether a member has a copy out past its due date.
     */
    public function hasOverdue(string $member, DateTimeImmutable $now): bool;

    /**
     * The active loans due before a moment, the earliest due first, at most so many: those to remind, and those overdue.
     *
     * @return list<Loan>
     */
    public function dueBefore(DateTimeImmutable $moment, int $limit): array;

    /**
     * Forgets a member on all their loans.
     *
     * @return int the loans changed
     */
    public function forgetMember(string $member): int;

    /**
     * Forgets the member of the loans closed — returned, cancelled or lost — before a moment.
     *
     * @return int the loans changed
     */
    public function forgetClosedBefore(DateTimeImmutable $moment): int;
}
