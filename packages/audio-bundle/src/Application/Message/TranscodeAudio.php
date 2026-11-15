<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Message;

/**
 * Produce one rendition of an uploaded audio file, whole or only an excerpt of it. The message carries identifiers and
 * instants only: the handler fetches the file itself.
 */
final readonly class TranscodeAudio
{
    /**
     * @param int|null $excerptStart    in milliseconds, with its duration for an excerpt
     * @param int|null $excerptDuration in milliseconds
     */
    public function __construct(
        public string $mediaId,
        public string $rendition,
        public ?int $excerptStart = null,
        public ?int $excerptDuration = null,
    ) {
    }
}
