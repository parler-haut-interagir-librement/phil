<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application;

use InvalidArgumentException;

/**
 * The bounds of what the bundle accepts: the length of a message and of a subject, in characters, the size of a group,
 * and how many messages or conversations a page reads.
 */
final readonly class ConversationLimits
{
    public const int BODY_LENGTH = 10_000;

    public const int SUBJECT_LENGTH = 200;

    public const int GROUP_SIZE = 50;

    public const int PAGE_SIZE = 50;

    /**
     * @param positive-int $bodyLength
     * @param positive-int $subjectLength
     * @param int<2, max>  $groupSize
     * @param positive-int $pageSize
     */
    public function __construct(
        public int $bodyLength = self::BODY_LENGTH,
        public int $subjectLength = self::SUBJECT_LENGTH,
        public int $groupSize = self::GROUP_SIZE,
        public int $pageSize = self::PAGE_SIZE,
    ) {
        if ($bodyLength > 1_000_000 || $subjectLength > 1000 || $groupSize > 1000 || $pageSize > 200) {
            throw new InvalidArgumentException('The limits of the conversations stay within 1 000 000 characters a message, 1 000 a subject, 1 000 participants a group and 200 lines a page.');
        }
    }

    /**
     * The size of a page asked for, kept within 1 and the configured size.
     *
     * @return positive-int
     */
    public function page(?int $asked): int
    {
        return max(1, min($asked ?? $this->pageSize, $this->pageSize));
    }
}
