<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Memory;

use DateTimeImmutable;
use Override;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Contract\MinorStore;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Minor;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The minors in an array, for tests and for a single process. They are lost with the object, or on `reset()`.
 */
final class InMemoryMinorStore implements MinorStore, ResetInterface
{
    /** @var array<string, Minor> */
    private array $minors = [];

    #[Override]
    public function get(MemberId $member): ?Minor
    {
        return $this->minors[$member->value] ?? null;
    }

    #[Override]
    public function save(Minor $minor): void
    {
        $this->minors[$minor->member->value] = $minor;
    }

    #[Override]
    public function remove(MemberId $member): void
    {
        unset($this->minors[$member->value]);
    }

    #[Override]
    public function dueForHandover(CalendarDate $date, int $limit): array
    {
        $due = array_filter(
            $this->minors,
            static fn (Minor $minor): bool => !$minor->handoverNoticedAt instanceof DateTimeImmutable && !$minor->consentAgeOn->isAfter($date),
        );
        usort($due, static fn (Minor $a, Minor $b): int => [$a->consentAgeOn->toString(), $a->member->value] <=> [$b->consentAgeOn->toString(), $b->member->value]);

        return \array_slice($due, 0, $limit);
    }

    #[Override]
    public function reset(): void
    {
        $this->minors = [];
    }
}
