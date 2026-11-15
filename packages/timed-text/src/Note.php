<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * A WebVTT comment, a `NOTE` block, anchored to an instant: it is written before the first cue that starts at or
 * after that instant, or after every cue when the anchor is null. Shifting a track moves its anchors with its cues.
 */
final readonly class Note
{
    /**
     * @throws InvalidCue when the text holds an empty line or `-->`, which would end the block, or the anchor is out
     *                    of range
     */
    public function __construct(
        public string $text,
        public ?int $at = null,
    ) {
        if (!Cue::isWritableText($text)) {
            throw new InvalidCue('A note holds no carriage return, no empty line and no "-->".');
        }

        if (null !== $at) {
            Timecode::check($at);
        }
    }

    public function shifted(int $milliseconds): self
    {
        return null === $this->at ? $this : new self($this->text, max(0, $this->at + $milliseconds));
    }
}
