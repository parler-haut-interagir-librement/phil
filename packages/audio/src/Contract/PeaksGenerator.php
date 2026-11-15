<?php

declare(strict_types=1);

namespace Phil\Audio\Contract;

use Phil\Audio\Exception\AudioException;
use Phil\Audio\Peaks;

/**
 * Computes the waveform of an audio file.
 */
interface PeaksGenerator
{
    /**
     * @param string $path            the path of a local file
     * @param int    $samplesPerPixel the samples of each run, at the sample rate the generator decodes at
     *
     * @throws AudioException when the file cannot be decoded, no tool is configured, or a limit is passed
     */
    public function peaks(string $path, int $samplesPerPixel = 800): Peaks;
}
