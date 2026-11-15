<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;
use Phil\TimedText\Timecode;

/**
 * A part of an audio file, from its start, for its duration, both in milliseconds: what a listener hears of a track
 * before buying it.
 */
final readonly class Excerpt
{
    /**
     * @throws InvalidAudio when the start is negative, the duration below 1 ms, or the end past `Timecode::MAX`
     */
    public function __construct(
        public int $start,
        public int $duration,
    ) {
        if ($start < 0 || $duration < 1 || $duration > Timecode::MAX - $start) {
            throw new InvalidAudio(\sprintf('An excerpt starts at 0 ms or later and lasts 1 ms or more, until %d ms at most.', Timecode::MAX));
        }
    }

    public function end(): int
    {
        return $this->start + $this->duration;
    }

    /**
     * The excerpt cut to a file of this duration; null when it starts at or after the file's end.
     */
    public function within(int $duration): ?self
    {
        if ($this->start >= $duration) {
            return null;
        }

        return $this->end() <= $duration ? $this : new self($this->start, $duration - $this->start);
    }
}
