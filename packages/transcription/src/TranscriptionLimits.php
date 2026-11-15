<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Phil\Transcription\Exception\InvalidTranscription;

/**
 * The bounds of one transcription: a recording larger is refused before it is sent, a response larger is cut and
 * refused, and so is one with more segments or longer text. The defaults cover a recording of a few hours.
 */
final readonly class TranscriptionLimits
{
    /**
     * @param int   $maxAudioBytes    the bytes of a recording sent
     * @param int   $maxResponseBytes the bytes of a response read
     * @param int   $maxSegments      the segments of a transcript
     * @param int   $maxTextBytes     the bytes of the text of one segment
     * @param float $timeout          the seconds a request may last
     *
     * @throws InvalidTranscription when one is not positive
     */
    public function __construct(
        public int $maxAudioBytes = 1_073_741_824,
        public int $maxResponseBytes = 16_777_216,
        public int $maxSegments = 50_000,
        public int $maxTextBytes = 8_192,
        public float $timeout = 3_600.0,
    ) {
        if (min($maxAudioBytes, $maxResponseBytes, $maxSegments, $maxTextBytes) < 1 || $timeout <= 0) {
            throw new InvalidTranscription('Every limit of a transcription is positive.');
        }
    }
}
