<?php

declare(strict_types=1);

namespace Phil\Outbox;

use DateTimeImmutable;
use Phil\Outbox\Exception\InvalidSetting;

/**
 * How long the relay waits before trying a record again: the first delay after the first failure, doubled after each
 * failure that follows, never above the longest delay. After `$maxAttempts` failed attempts, the record is dead.
 */
final readonly class Backoff
{
    /** The delay after a first failure, in seconds, unless the application chooses otherwise: one minute. */
    public const int FIRST_DELAY = 60;

    /** The longest delay, in seconds, unless the application chooses otherwise: one day. */
    public const int LONGEST_DELAY = 86_400;

    /** The attempts after which a record is dead, unless the application chooses otherwise. */
    public const int MAX_ATTEMPTS = 10;

    /**
     * @param int $firstDelay   the delay after a first failure, in seconds, 1 or more
     * @param int $longestDelay the longest delay, in seconds, not under the first
     * @param int $maxAttempts  the failed attempts after which a record is dead, 1 or more
     *
     * @throws InvalidSetting when a delay or the number of attempts is out of bounds
     */
    public function __construct(
        public int $firstDelay = self::FIRST_DELAY,
        public int $longestDelay = self::LONGEST_DELAY,
        public int $maxAttempts = self::MAX_ATTEMPTS,
    ) {
        if ($firstDelay < 1 || $longestDelay < $firstDelay) {
            throw InvalidSetting::because(\sprintf('A record waits at least 1 second and the longest delay is not under the first; %d and %d are not.', $firstDelay, $longestDelay));
        }

        if ($maxAttempts < 1) {
            throw InvalidSetting::because(\sprintf('A record is tried at least once; %d attempts are not.', $maxAttempts));
        }
    }

    /**
     * The delay, in seconds, after the given number of failed attempts.
     */
    public function delay(int $attempts): int
    {
        $delay = $this->firstDelay;

        for ($attempt = 1; $attempt < $attempts && $delay < $this->longestDelay; ++$attempt) {
            $delay = min(2 * $delay, $this->longestDelay);
        }

        return $delay;
    }

    /**
     * When a record is tried again, after the given number of failed attempts.
     */
    public function retryAt(int $attempts, DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(\sprintf('+%d seconds', $this->delay($attempts)));
    }

    /**
     * Whether a record that failed the given number of attempts is dead.
     */
    public function exhausted(int $attempts): bool
    {
        return $attempts >= $this->maxAttempts;
    }
}
