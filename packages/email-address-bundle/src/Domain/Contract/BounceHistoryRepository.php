<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain\Contract;

use DateTimeImmutable;

/**
 * What the bounce rules remember between notices: the notices already decided, and the soft bounces of each address
 * within the window.
 */
interface BounceHistoryRepository
{
    /**
     * Claims a notice: `true` the first time its key is claimed, `false` while the claim before it lasts.
     *
     * @param DateTimeImmutable $until the end of the claim, after which the key may be claimed again
     */
    public function claim(string $key, DateTimeImmutable $until): bool;

    /**
     * Records a soft bounce of a subject and forgets those older than the window.
     *
     * @param DateTimeImmutable $at    when the bounce was received
     * @param DateTimeImmutable $since the start of the window
     *
     * @return int the soft bounces of the subject within the window, this one included
     */
    public function recordSoftBounce(string $subject, DateTimeImmutable $at, DateTimeImmutable $since): int;

    /**
     * Forgets the soft bounces of a subject: its address was suspended or unsubscribed.
     */
    public function forgetSoftBounces(string $subject): void;
}
