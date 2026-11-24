<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;

/**
 * The mutual relationships, one per pair of members, kept apart from the follows: neither stopping to follow nor
 * ignoring ends one.
 */
interface MutualRepository
{
    /**
     * @return bool false when the two members were already in a relationship
     */
    public function add(MemberPair $pair, DateTimeImmutable $since): bool;

    public function remove(MemberPair $pair): bool;

    public function exists(MemberPair $pair): bool;

    /**
     * The other members of a member's relationships, in byte order, after the cursor.
     *
     * @return list<string>
     */
    public function of(string $member, ?string $after, int $limit): array;

    /**
     * @return list<array{member: string, since: DateTimeImmutable}>
     */
    public function exportOf(string $member): array;

    public function eraseMember(string $member): void;
}
