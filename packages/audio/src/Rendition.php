<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;

/**
 * One version of an audio file to produce: its format, and the bitrate, channels and sample rate the encoder is asked
 * for, each left to the encoder when null.
 */
final readonly class Rendition
{
    /** The sample rates a rendition may ask for, in hertz. */
    public const array SAMPLE_RATES = [8_000, 11_025, 12_000, 16_000, 22_050, 24_000, 32_000, 44_100, 48_000];

    /** The sample rates the Opus encoder accepts. */
    public const array OPUS_SAMPLE_RATES = [8_000, 12_000, 16_000, 24_000, 48_000];

    /**
     * @param int|null $bitrate    in kilobits per second, from 8 to 512; none for a lossless format
     * @param int|null $channels   1 for mono, 2 for stereo
     * @param int|null $sampleRate in hertz, one of `SAMPLE_RATES`, and of `OPUS_SAMPLE_RATES` for Opus
     *
     * @throws InvalidAudio when a value is outside these
     */
    public function __construct(
        public AudioFormat $format,
        public ?int $bitrate = null,
        public ?int $channels = null,
        public ?int $sampleRate = null,
    ) {
        if (null !== $bitrate && $format->isLossless()) {
            throw new InvalidAudio(\sprintf('The lossless format "%s" takes no bitrate.', $format->value));
        }

        if (null !== $bitrate && ($bitrate < 8 || $bitrate > 512)) {
            throw new InvalidAudio(\sprintf('A bitrate is from 8 to 512 kbit/s, not %d.', $bitrate));
        }

        if (null !== $channels && 1 !== $channels && 2 !== $channels) {
            throw new InvalidAudio(\sprintf('A rendition has 1 or 2 channels, not %d.', $channels));
        }

        if (null !== $sampleRate && !\in_array($sampleRate, AudioFormat::Opus === $format ? self::OPUS_SAMPLE_RATES : self::SAMPLE_RATES, true)) {
            throw new InvalidAudio(\sprintf('%d Hz is not a sample rate of the format "%s".', $sampleRate, $format->value));
        }
    }
}
