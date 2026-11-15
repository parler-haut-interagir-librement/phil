<?php

declare(strict_types=1);

namespace Phil\Audio;

/**
 * The formats an audio file is served in, each with the media type a `<source>` declares and its file extension.
 */
enum AudioFormat: string
{
    case Mp3 = 'mp3';
    case Aac = 'aac';
    case Opus = 'opus';
    case Flac = 'flac';
    case Wav = 'wav';

    /**
     * The format of a media type, its case ignored: `audio/mpeg`, `audio/mp4`, `audio/ogg` with no codec or the Opus
     * codec, `audio/flac`, `audio/wav` and their usual aliases; null for any other.
     */
    public static function fromMediaType(string $mediaType): ?self
    {
        $parts = explode(';', strtolower($mediaType));
        $type = trim($parts[0]);
        $codecs = null;

        foreach (\array_slice($parts, 1) as $parameter) {
            $pair = explode('=', $parameter, 2);

            if ('codecs' === trim($pair[0])) {
                $codecs = trim($pair[1] ?? '', " \t\"");
            }
        }

        return match ($type) {
            'audio/mpeg', 'audio/mp3' => self::Mp3,
            'audio/mp4', 'audio/aac', 'audio/x-m4a' => self::Aac,
            'audio/ogg', 'audio/opus' => null === $codecs || 'opus' === $codecs ? self::Opus : null,
            'audio/flac', 'audio/x-flac' => self::Flac,
            'audio/wav', 'audio/x-wav', 'audio/wave' => self::Wav,
            default => null,
        };
    }

    public function mediaType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Aac => 'audio/mp4',
            self::Opus => 'audio/ogg; codecs=opus',
            self::Flac => 'audio/flac',
            self::Wav => 'audio/wav',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Mp3 => 'mp3',
            self::Aac => 'm4a',
            self::Opus => 'opus',
            self::Flac => 'flac',
            self::Wav => 'wav',
        };
    }

    /**
     * Whether the format keeps every sample, and so takes no bitrate.
     */
    public function isLossless(): bool
    {
        return self::Flac === $this || self::Wav === $this;
    }
}
