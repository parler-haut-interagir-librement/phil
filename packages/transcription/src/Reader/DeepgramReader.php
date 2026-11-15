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
 * Reads the response of Deepgram's pre-recorded API asked for utterances: `results.utterances`, each with `start` and
 * `end` in seconds, its `transcript`, its `confidence` and, when speakers are told apart, its `speaker`, a number from
 * 0, named after the label pattern — `Speaker 1` by default.
 */
final readonly class DeepgramReader
{
    /**
     * @param string $speakerLabel the name of a speaker, `%d` their number from 1
     */
    public function __construct(
        private TranscriptionLimits $limits = new TranscriptionLimits(),
        private string $speakerLabel = 'Speaker %d',
    ) {
    }

    /**
     * @throws TranscriptionFailed when the response is no JSON object with a list of utterances
     * @throws LimitExceeded       when it passes the limits
     */
    public function read(string $json, Language $language, ?string $engine = null): Transcript
    {
        $segments = new Segments($this->limits);
        $document = $segments->decode($json);
        $results = $document['results'] ?? null;
        $list = \is_array($results) ? ($results['utterances'] ?? null) : null;

        if (!\is_array($list) || !array_is_list($list)) {
            throw new TranscriptionFailed('The response holds no list of utterances.');
        }

        foreach ($list as $utterance) {
            if (\is_array($utterance)) {
                $speaker = $utterance['speaker'] ?? null;
                $segments->add(
                    $utterance['start'] ?? null,
                    $utterance['end'] ?? null,
                    $utterance['transcript'] ?? null,
                    \is_int($speaker) && $speaker >= 0 && $speaker < 1_000 ? \sprintf($this->speakerLabel, $speaker + 1) : null,
                    $utterance['confidence'] ?? null,
                );
            }
        }

        return new Transcript($language, $segments->all(), engine: $engine);
    }
}
