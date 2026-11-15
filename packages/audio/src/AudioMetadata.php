<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;

/**
 * What a tool read of an audio file: its duration, its container and codec, its bitrate, sample rate and channels, and
 * its tags — title, artist, album —, each name in lowercase. A value the file does not give is null.
 */
final readonly class AudioMetadata
{
    /**
     * @param int|null              $duration   in milliseconds
     * @param int|null              $bitrate    in bits per second
     * @param int|null              $sampleRate in hertz
     * @param array<string, string> $tags       by name in lowercase
     *
     * @throws InvalidAudio when a number is negative, or zero for the sample rate and the channels
     */
    public function __construct(
        public ?int $duration,
        public string $container,
        public ?string $codec = null,
        public ?int $bitrate = null,
        public ?int $sampleRate = null,
        public ?int $channels = null,
        public array $tags = [],
    ) {
        if ((null !== $duration && $duration < 0) || (null !== $bitrate && $bitrate < 0) || (null !== $sampleRate && $sampleRate < 1) || (null !== $channels && $channels < 1)) {
            throw new InvalidAudio('The duration and the bitrate are not negative; the sample rate and the channels are positive.');
        }
    }

    /**
     * The value of a tag, its name in any case; null when the file does not give it.
     */
    public function tag(string $name): ?string
    {
        return $this->tags[strtolower($name)] ?? null;
    }
}
