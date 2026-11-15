<?php

declare(strict_types=1);

namespace Phil\Transcription\Reader;

use Phil\Transcription\Exception\LimitExceeded;
use Phil\Transcription\Exception\TranscriptionFailed;
use Phil\Transcription\Internal\Segments;
use Phil\Transcription\Language;
use Phil\Transcription\Transcript;
use Phil\Transcription\TranscriptionLimits;

/**
 * Reads the `verbose_json` response of a Whisper server — whisper.cpp's and the hosted API's share it —: its
 * `segments`, each with `start` and `end` in seconds and its `text`. The language declared in the request is kept,
 * whatever the server detected.
 */
final readonly class VerboseJsonReader
{
    public function __construct(
        private TranscriptionLimits $limits = new TranscriptionLimits(),
    ) {
    }

    /**
     * @throws TranscriptionFailed when the response is no JSON object with a list of segments
     * @throws LimitExceeded       when it passes the limits
     */
    public function read(string $json, Language $language, ?string $engine = null): Transcript
    {
        $segments = new Segments($this->limits);
        $document = $segments->decode($json);
        $list = $document['segments'] ?? null;

        if (!\is_array($list) || !array_is_list($list)) {
            throw new TranscriptionFailed('The response holds no list of segments.');
        }

        foreach ($list as $segment) {
            if (\is_array($segment)) {
                $segments->add($segment['start'] ?? null, $segment['end'] ?? null, $segment['text'] ?? null);
            }
        }

        return new Transcript($language, $segments->all(), engine: $engine);
    }
}
