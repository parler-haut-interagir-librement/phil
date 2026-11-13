<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Paths;

/**
 * One phrase of a media overlay: the element of the chapter it highlights, by its identifier, and the part of an audio
 * file of the book that reads it, from `$begin` to `$end` in milliseconds.
 */
final readonly class Clip
{
    /**
     * @throws InvalidBook
     */
    public function __construct(
        public string $fragment,
        public string $audio,
        public int $begin,
        public int $end,
    ) {
        if ('' === $fragment || 1 === preg_match('/\s/', $fragment)) {
            throw InvalidBook::because(\sprintf('A clip highlights an element by its identifier, without spaces; "%s" given.', $fragment));
        }

        Paths::check($audio);

        if ($begin < 0 || $end <= $begin) {
            throw InvalidBook::because(\sprintf('A clip ends after it begins, from 0 on; %d to %d ms given for "#%s".', $begin, $end, $fragment));
        }
    }

    /**
     * How long the clip plays, in milliseconds.
     */
    public function duration(): int
    {
        return $this->end - $this->begin;
    }
}
