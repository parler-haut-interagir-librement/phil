<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Message;

/**
 * Compute the waveform of an uploaded audio file. The message carries its identifier only.
 */
final readonly class ComputeWaveform
{
    public function __construct(
        public string $mediaId,
    ) {
    }
}
