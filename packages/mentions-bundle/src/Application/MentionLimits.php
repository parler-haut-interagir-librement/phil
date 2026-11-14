<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;

/**
 * How much a text may hold: the members it mentions at once — 20 by default, 1 000 at most — and its length in
 * characters before it is read — 100 000 by default, 10 000 000 at most. A text over a limit is refused, so that one
 * message cannot notify a whole community, and reading stays bounded.
 */
final readonly class MentionLimits
{
    public const int PER_TEXT = 20;

    public const int TEXT_LENGTH = 100_000;

    /**
     * @throws InvalidMention when a limit is out of its range
     */
    public function __construct(
        public int $perText = self::PER_TEXT,
        public int $textLength = self::TEXT_LENGTH,
    ) {
        if ($perText < 1 || $perText > 1000) {
            throw InvalidMention::because('A text mentions from 1 to 1000 members at most.');
        }

        if ($textLength < 1 || $textLength > 10_000_000) {
            throw InvalidMention::because('A text read for mentions holds from 1 to 10000000 characters at most.');
        }
    }

    /**
     * @throws MentionsRefused when the text mentions more members than allowed
     */
    public function checkCount(int $count): void
    {
        if ($count > $this->perText) {
            throw MentionsRefused::tooMany($count, $this->perText);
        }
    }

    /**
     * @throws MentionsRefused when the text is longer than allowed
     */
    public function checkLength(string $text): void
    {
        $length = mb_strlen($text);

        if ($length > $this->textLength) {
            throw MentionsRefused::tooLong($length, $this->textLength);
        }
    }
}
