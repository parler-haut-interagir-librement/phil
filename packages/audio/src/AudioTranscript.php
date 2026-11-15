<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;
use Phil\TimedText\Track;
use Phil\Url\Url;

/**
 * The transcript of a track in one language: its cues, a phil/timed-text `Track`, the label a listener picks it by,
 * and the address of its file when one is offered for download.
 */
final readonly class AudioTranscript
{
    /**
     * @param string $language a BCP 47 language tag, `fr-CA`
     *
     * @throws InvalidAudio when the language is no tag or the label is empty
     */
    public function __construct(
        public Track $track,
        public string $language,
        public string $label,
        public ?Url $url = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $language)) {
            throw new InvalidAudio(\sprintf('"%s" is no language tag.', $language));
        }

        if ('' === trim($label)) {
            throw new InvalidAudio('A transcript has a label.');
        }
    }
}
