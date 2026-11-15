<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Phil\Transcription\Exception\InvalidTranscription;
use Stringable;

/**
 * The language a recording is declared in, a BCP 47 tag of a language, a script and a region — `fr-CA`, `en`,
 * `zh-Hant-TW`, `es-419` —, written in its usual case: the language in lowercase, the script capitalised, the region in
 * uppercase. A transcription service is told the language rather than left to guess it.
 */
final readonly class Language implements Stringable
{
    public string $tag;

    /**
     * @throws InvalidTranscription when the text is no such tag
     */
    public function __construct(string $tag)
    {
        if (1 !== preg_match('/^([A-Za-z]{2,3})(?:[-_]([A-Za-z]{4}))?(?:[-_]([A-Za-z]{2}|\d{3}))?$/D', $tag, $match)) {
            throw new InvalidTranscription(\sprintf('"%s" is no language tag of a language, a script and a region.', $tag));
        }

        $this->tag = implode('-', array_filter([
            strtolower($match[1]),
            ucfirst(strtolower($match[2] ?? '')),
            strtoupper($match[3] ?? ''),
        ], static fn (string $part): bool => '' !== $part));
    }

    /**
     * The language alone, `fr` for `fr-CA`: what the Whisper models take.
     */
    public function primary(): string
    {
        return explode('-', $this->tag, 2)[0];
    }

    public function __toString(): string
    {
        return $this->tag;
    }
}
