<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;

/**
 * Where the acceptances are kept: added, never changed; removed only by the erasure of a member's data.
 */
interface AcceptanceRepository
{
    public function append(Acceptance $acceptance): void;

    /**
     * Among some members, those who accepted a version of a policy numbered at least `$minimum`, at or after `$since`
     * when it is given.
     *
     * @param list<string> $members 500 at most
     *
     * @return list<string>
     */
    public function compliant(string $policy, array $members, int $minimum, ?DateTimeImmutable $since = null): array;

    /**
     * The acceptances of a member, the oldest first.
     *
     * @return list<Acceptance>
     */
    public function ofMember(string $member): array;

    /**
     * Removes the acceptances of a member, and gives how many there were.
     */
    public function erase(string $member): int;
}
