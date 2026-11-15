<?php

declare(strict_types=1);

namespace Phil\Audio;

use Override;
use Phil\Audio\Contract\MetadataReader;
use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Exception\AudioToolUnavailable;

/**
 * No audio tool: an application that serves only the files its creators upload, in the formats they chose. Every call
 * says so with `AudioToolUnavailable`.
 */
final readonly class NullAudio implements Transcoder, MetadataReader, PeaksGenerator
{
    #[Override]
    public function transcode(string $source, string $target, Rendition $rendition, ?Excerpt $excerpt = null): void
    {
        throw $this->unavailable();
    }

    #[Override]
    public function read(string $path): AudioMetadata
    {
        throw $this->unavailable();
    }

    #[Override]
    public function peaks(string $path, int $samplesPerPixel = 800): Peaks
    {
        throw $this->unavailable();
    }

    private function unavailable(): AudioToolUnavailable
    {
        return new AudioToolUnavailable('No audio tool is configured: the files are served as they were uploaded.');
    }
}
