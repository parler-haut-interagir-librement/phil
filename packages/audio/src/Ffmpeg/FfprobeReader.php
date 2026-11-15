<?php

declare(strict_types=1);

namespace Phil\Audio\Ffmpeg;

use JsonException;
use Phil\Audio\AudioMetadata;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;

/**
 * Reads the JSON report of ffprobe (`-print_format json -show_format -show_streams`) into `AudioMetadata`: the first
 * audio stream, the container, and the tags of both, those of the container first. A tag whose value is not text or is
 * longer than `MAX_TAG_BYTES` is left out, and so are the tags past `MAX_TAGS`; JSON is valid UTF-8 or refused.
 */
final readonly class FfprobeReader
{
    public const int MAX_TAGS = 64;

    public const int MAX_TAG_BYTES = 1_024;

    public function __construct(
        private int $maxBytes = 1_048_576,
    ) {
    }

    /**
     * @throws InvalidAudio  when the report is not JSON, or describes no audio stream
     * @throws LimitExceeded when it is longer than allowed
     */
    public function read(string $json): AudioMetadata
    {
        if (\strlen($json) > $this->maxBytes) {
            throw new LimitExceeded(\sprintf('The report of ffprobe holds more than %d bytes.', $this->maxBytes));
        }

        try {
            $report = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidAudio('The report of ffprobe is no JSON: '.$jsonException->getMessage(), 0, $jsonException);
        }

        $format = \is_array($report) && \is_array($report['format'] ?? null) ? $report['format'] : [];
        $streams = \is_array($report) && \is_array($report['streams'] ?? null) ? $report['streams'] : [];
        $stream = array_find($streams, static fn (mixed $stream): bool => \is_array($stream) && 'audio' === ($stream['codec_type'] ?? null));

        if (!\is_array($stream)) {
            throw new InvalidAudio('The file holds no audio stream.');
        }

        $duration = $this->number($format['duration'] ?? null) ?? $this->number($stream['duration'] ?? null);
        $bitrate = $this->number($format['bit_rate'] ?? null) ?? $this->number($stream['bit_rate'] ?? null);
        $sampleRate = $this->number($stream['sample_rate'] ?? null);
        $channels = $stream['channels'] ?? null;

        return new AudioMetadata(
            null === $duration ? null : (int) round($duration * 1000),
            $this->text($format['format_name'] ?? null) ?? 'unknown',
            $this->text($stream['codec_name'] ?? null),
            null === $bitrate ? null : (int) $bitrate,
            null === $sampleRate || $sampleRate < 1 ? null : (int) $sampleRate,
            \is_int($channels) && $channels > 0 ? $channels : null,
            $this->tags($format['tags'] ?? null, $stream['tags'] ?? null),
        );
    }

    /**
     * A number ffprobe writes as text, `"215.040000"`, or as an integer; null for anything else, a negative, or a value
     * of 10^15 or more, past which a float loses its units.
     */
    private function number(mixed $value): ?float
    {
        if (\is_string($value) && 1 === preg_match('/^\d{1,15}(?:\.\d{1,9})?$/D', $value)) {
            return (float) $value;
        }

        return \is_int($value) && $value >= 0 && $value < 1_000_000_000_000_000 ? (float) $value : null;
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value && \strlen($value) <= self::MAX_TAG_BYTES ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function tags(mixed ...$sets): array
    {
        $tags = [];

        foreach ($sets as $set) {
            foreach (\is_array($set) ? $set : [] as $name => $value) {
                $name = strtolower((string) $name);
                $value = $this->text($value);

                if (null === $value || isset($tags[$name]) || self::MAX_TAGS === \count($tags) || null === $this->text($name)) {
                    continue;
                }

                $tags[$name] = $value;
            }
        }

        return $tags;
    }
}
