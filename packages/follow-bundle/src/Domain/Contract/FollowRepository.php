<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

use Phil\FollowBundle\Domain\Model\Follow;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The follows, one per member and subject. Every list is a page read after a cursor — the last member or subject of
 * the page before —, in byte order, so that no read grows with the number of follows.
 */
interface FollowRepository
{
    public function find(string $member, SubjectReference $subject): ?Follow;

    /**
     * Inserts the follow, or replaces the level and preferences of the member's follow of that subject.
     */
    public function save(Follow $follow): void;

    /**
     * @return bool whether there was a follow to remove
     */
    public function remove(string $member, SubjectReference $subject): bool;

    /**
     * The follows of a subject that count as following — every level but `ignoring` —, by member.
     *
     * @return list<Follow>
     */
    public function followersOf(SubjectReference $subject, ?string $after, int $limit): array;

    public function countFollowers(SubjectReference $subject): int;

    /**
     * A member's own follows, every level, by subject type then identifier; the cursor is a subject key, `type:id`.
     *
     * @return list<Follow>
     */
    public function ofMember(string $member, ?string $after, int $limit): array;

    /**
     * Every follow, of any level, that the members of one page hold on the subjects of a chain — a subject and its
     * parents —: the page is the first `$limit` members after the cursor holding one, in byte order, so that a member's
     * follows of the chain are never split between two pages. Two queries, whatever the number of followers.
     *
     * @param list<SubjectReference> $chain
     *
     * @return list<Follow>
     */
    public function candidates(array $chain, ?string $after, int $limit): array;

    /**
     * Deletes the member's follows and the follows of the member's own subject.
     */
    public function eraseMember(string $member, SubjectReference $memberSubject): void;
}
