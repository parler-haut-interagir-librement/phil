<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Phil\TimedText\Timecode;
use Phil\Transcription\Exception\InvalidTranscription;

/**
 * What was said between two instants, in milliseconds: its text on one line, who said it when the service tells
 * speakers apart, and how sure the service was, from 0 to 1.
 */
final readonly class Segment
{
    public string $text;

    public ?string $speaker;

    /**
     * @throws InvalidTranscription when an instant is out of range, the end before the start, the text blank or the
     *                              confidence outside 0 to 1
     */
    public function __construct(
        public int $start,
        public int $end,
        string $text,
        ?string $speaker = null,
        public ?float $confidence = null,
    ) {
        if ($start < 0 || $end < $start || $end > Timecode::MAX) {
            throw new InvalidTranscription(\sprintf('A segment runs from %d ms to %d ms: from 0 ms, forward, until %d ms at most.', $start, $end, Timecode::MAX));
        }

        $this->text = $this->line($text);

        if ('' === $this->text) {
            throw new InvalidTranscription('A segment holds text.');
        }

        $speaker = null === $speaker ? '' : $this->line($speaker);
        $this->speaker = '' === $speaker ? null : $speaker;

        if (null !== $confidence && ($confidence < 0.0 || $confidence > 1.0)) {
            throw new InvalidTranscription('A confidence is from 0 to 1.');
        }
    }

    private function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
