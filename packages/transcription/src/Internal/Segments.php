<?php

declare(strict_types=1);

namespace Phil\Transcription\Internal;

use JsonException;
use Phil\TimedText\Timecode;
use Phil\Transcription\Exception\LimitExceeded;
use Phil\Transcription\Exception\TranscriptionFailed;
use Phil\Transcription\Segment;
use Phil\Transcription\TranscriptionLimits;

/**
 * What the readers of responses share: the JSON decoded within its bytes, and segments gathered within their number and
 * the bytes of their text, a segment the service could not time or left blank skipped.
 *
 * @internal
 */
final class Segments
{
    /** @var list<Segment> */
    private array $segments = [];

    public function __construct(
        private readonly TranscriptionLimits $limits,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws LimitExceeded       when the response is longer than allowed
     * @throws TranscriptionFailed when it is no JSON object
     */
    public function decode(string $json): array
    {
        if (\strlen($json) > $this->limits->maxResponseBytes) {
            throw new LimitExceeded(\sprintf('The response holds more than %d bytes.', $this->limits->maxResponseBytes));
        }

        try {
            $document = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new TranscriptionFailed('The response is no JSON: '.$jsonException->getMessage(), 0, $jsonException);
        }

        if (!\is_array($document)) {
            throw new TranscriptionFailed('The response is a JSON object.');
        }

        return $document;
    }

    /**
     * @throws LimitExceeded when the segments or their text pass the limits
     */
    public function add(mixed $start, mixed $end, mixed $text, ?string $speaker = null, mixed $confidence = null): void
    {
        $start = $this->milliseconds($start);
        $end = $this->milliseconds($end);

        if (null === $start || null === $end || $end < $start || !\is_string($text) || '' === trim((string) preg_replace('/\s+/u', ' ', $text))) {
            return;
        }

        if (\strlen($text) > $this->limits->maxTextBytes) {
            throw new LimitExceeded(\sprintf('A segment holds more than %d bytes of text.', $this->limits->maxTextBytes));
        }

        if (\count($this->segments) === $this->limits->maxSegments) {
            throw new LimitExceeded(\sprintf('The transcript holds more than %d segments.', $this->limits->maxSegments));
        }

        $confidence = \is_int($confidence) || \is_float($confidence) ? (float) $confidence : null;
        $this->segments[] = new Segment($start, $end, $text, $speaker, null !== $confidence && $confidence >= 0.0 && $confidence <= 1.0 ? $confidence : null);
    }

    /**
     * @return list<Segment>
     */
    public function all(): array
    {
        return $this->segments;
    }

    /**
     * An instant the service gives in seconds, as milliseconds; null when it is no number, negative, or past the last
     * instant of timed text.
     */
    private function milliseconds(mixed $seconds): ?int
    {
        if (!\is_int($seconds) && !\is_float($seconds)) {
            return null;
        }

        $milliseconds = round($seconds * 1000);

        return $milliseconds >= 0 && $milliseconds <= Timecode::MAX ? (int) $milliseconds : null;
    }
}
