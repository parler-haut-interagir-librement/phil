<?php

declare(strict_types=1);

namespace Phil\Audio\Ffmpeg;

use Phil\Audio\Exception\InvalidAudio;

/**
 * The bounds of one run of ffmpeg or ffprobe: a file larger or longer is refused before any tool runs, a run longer is
 * stopped, and an output larger is cut and refused. The defaults cover a six-hour recording.
 */
final readonly class FfmpegLimits
{
    /**
     * @param float $timeout        the seconds one run may take
     * @param int   $maxInputBytes  the bytes of a file read
     * @param int   $maxOutputBytes the bytes of a file written
     * @param int   $maxDuration    the milliseconds of a file read
     * @param int   $maxReportBytes the bytes a tool may print, the report of ffprobe included
     *
     * @throws InvalidAudio when one is not positive
     */
    public function __construct(
        public float $timeout = 900.0,
        public int $maxInputBytes = 2_147_483_648,
        public int $maxOutputBytes = 1_073_741_824,
        public int $maxDuration = 21_600_000,
        public int $maxReportBytes = 1_048_576,
    ) {
        if ($timeout <= 0 || min($maxInputBytes, $maxOutputBytes, $maxDuration, $maxReportBytes) < 1) {
            throw new InvalidAudio('Every limit of ffmpeg is positive.');
        }
    }
}
