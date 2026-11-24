<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * What was sent: the members each activity was delivered to, so that a retried batch never sends twice, and, by member
 * and subject, the instant of the last notification that went out on every channel, until the member sees the subject.
 */
interface DeliveryRepository
{
    /**
     * Claims the delivery of an activity to each member, atomically per member: the members already claimed, by an
     * earlier attempt or by another worker, are left out of what is returned.
     *
     * @param list<string> $members
     *
     * @return list<string> the members claimed now, in the order given
     */
    public function claim(string $activity, array $members, DateTimeImmutable $at): array;

    /**
     * Gives the claims back, for the next attempt, after a delivery that failed.
     *
     * @param list<string> $members
     */
    public function release(string $activity, array $members): void;

    /**
     * The members who were notified about the subject on every channel at or after the instant, and have not seen it
     * since.
     *
     * @param list<string> $members
     *
     * @return list<string>
     */
    public function unseen(SubjectReference $subject, array $members, DateTimeImmutable $since): array;

    /**
     * @param list<string> $members
     */
    public function markNotified(SubjectReference $subject, array $members, DateTimeImmutable $at): void;

    public function seen(string $member, SubjectReference $subject): void;

    /**
     * Deletes up to `$limit` claims made before the instant.
     *
     * @return int how many
     */
    public function purgeClaimsBefore(DateTimeImmutable $before, int $limit): int;

    /**
     * Deletes up to `$limit` marks of notifications sent before the instant.
     *
     * @return int how many
     */
    public function purgeMarksBefore(DateTimeImmutable $before, int $limit): int;

    public function eraseMember(string $member): void;
}
