<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\LoanStatus;

final class InMemoryLoans implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

    /** @var list<string> the loans saved, in order */
    public array $saved = [];

    /** @var list<string> the members whose loans were read, in order */
    public array $read = [];

    /** @var list<int> the limits those reads gave, in order */
    public array $limits = [];

    /** @var list<DateTimeImmutable> the moments the loans due before were asked for, in order */
    public array $horizons = [];

    #[Override]
    public function get(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    #[Override]
    public function save(Loan $loan): void
    {
        $this->loans[$loan->id] = $loan;
        $this->saved[] = $loan->id;
    }

    #[Override]
    public function holding(Copy $copy): array
    {
        $loans = $this->filter(static fn (Loan $loan): bool => $copy === $loan->copy() && $loan->status()->holdsCopy());
        usort($loans, static fn (Loan $a, Loan $b): int => $a->startsAt()->getTimestamp() <=> $b->startsAt()->getTimestamp());

        return $loans;
    }

    #[Override]
    public function activeOf(Copy $copy): ?Loan
    {
        return array_find($this->loans, static fn (Loan $loan): bool => $copy === $loan->copy() && LoanStatus::Active === $loan->status());
    }

    #[Override]
    public function ofMember(string $member, int $limit, bool $open = false): array
    {
        $this->read[] = $member;
        $this->limits[] = $limit;
        $loans = array_reverse($this->filter(static fn (Loan $loan): bool => $member === $loan->member() && (!$open || $loan->status()->holdsCopy())));

        return \array_slice($loans, 0, $limit);
    }

    #[Override]
    public function countOpen(string $member): int
    {
        return \count($this->ofMember($member, 1000, true));
    }

    #[Override]
    public function hasOverdue(string $member, DateTimeImmutable $now): bool
    {
        return [] !== $this->filter(static fn (Loan $loan): bool => $member === $loan->member() && $loan->isOverdue($now));
    }

    #[Override]
    public function dueBefore(DateTimeImmutable $moment, int $limit): array
    {
        $this->horizons[] = $moment;
        $loans = $this->filter(static fn (Loan $loan): bool => LoanStatus::Active === $loan->status() && $loan->dueAt()->getTimestamp() <= $moment->getTimestamp());
        usort($loans, static fn (Loan $a, Loan $b): int => $a->dueAt()->getTimestamp() <=> $b->dueAt()->getTimestamp());

        return \array_slice($loans, 0, $limit);
    }

    #[Override]
    public function forgetMember(string $member): int
    {
        $loans = $this->filter(static fn (Loan $loan): bool => $member === $loan->member());
        array_walk($loans, static fn (Loan $loan) => $loan->forgetMember());

        return \count($loans);
    }

    #[Override]
    public function forgetClosedBefore(DateTimeImmutable $moment): int
    {
        $loans = $this->filter(static fn (Loan $loan): bool => null !== $loan->member() && !$loan->status()->holdsCopy() && ($loan->closedAt()?->getTimestamp() ?? \PHP_INT_MAX) < $moment->getTimestamp());
        array_walk($loans, static fn (Loan $loan) => $loan->forgetMember());

        return \count($loans);
    }

    /**
     * @param callable(Loan): bool $filter
     *
     * @return list<Loan>
     */
    private function filter(callable $filter): array
    {
        return array_values(array_filter($this->loans, $filter));
    }
}
