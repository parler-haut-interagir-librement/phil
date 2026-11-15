<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Contract;

use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * Where the decisions are kept: the history of each target and of each member, and the public journal.
 */
interface DecisionRepository
{
    public function add(Decision $decision): void;

    /**
     * @return list<Decision> the latest first
     *
     * @phpstan-impure
     */
    public function on(TargetReference $target): array;

    /**
     * The decisions on content of the member, the latest first.
     *
     * @return list<Decision>
     *
     * @phpstan-impure
     */
    public function about(MemberId $author): array;

    /**
     * The decisions the member took as a moderator, the latest first.
     *
     * @return list<Decision>
     *
     * @phpstan-impure
     */
    public function takenBy(MemberId $moderator): array;

    /**
     * The public decisions, the latest first.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<Decision>
     *
     * @phpstan-impure
     */
    public function public(int $limit, int $offset = 0): array;

    /**
     * @phpstan-impure
     */
    public function countPublic(): int;

    /**
     * Removes the member from the decisions, as their author and as their moderator; the decisions stay, with their
     * role.
     *
     * @return int how many were changed
     */
    public function forget(MemberId $member): int;
}
