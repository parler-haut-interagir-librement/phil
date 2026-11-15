<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Url\Url;

/**
 * One file of a track a player may fetch: its address, its format, and whether it holds only the excerpt.
 */
final readonly class AudioSource
{
    public function __construct(
        public Url $url,
        public AudioFormat $format,
        public bool $excerpt = false,
    ) {
    }

    public function mediaType(): string
    {
        return $this->format->mediaType();
    }
}
