<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidLimits;

/**
 * The bounds of the work on one document, whatever it holds: a hostile file is refused before it costs more than they
 * allow. The defaults read the transcript of a whole day.
 */
final readonly class Limits
{
    /**
     * @param int $maxBytes     the bytes of the document
     * @param int $maxCues      the cues, segments or chapters read
     * @param int $maxTextBytes the bytes of the text of one cue, chapter title, note or style
     * @param int $maxIssues    the problems an inspection lists before it gives up
     *
     * @throws InvalidLimits when one is below 1
     */
    public function __construct(
        public int $maxBytes = 16_777_216,
        public int $maxCues = 100_000,
        public int $maxTextBytes = 65_536,
        public int $maxIssues = 1_000,
    ) {
        if (min($maxBytes, $maxCues, $maxTextBytes, $maxIssues) < 1) {
            throw new InvalidLimits('Every limit is at least 1.');
        }
    }
}
