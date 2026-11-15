<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Contract;

use Phil\Audio\AudioFormat;
use Phil\Audio\Peaks;

/**
 * Where the renditions and the waveform of an audio file are kept once computed.
 */
interface RenditionStore
{
    /**
     * Keeps the file of a rendition, read from its local path, whole or only its excerpt.
     */
    public function save(string $mediaId, string $rendition, AudioFormat $format, string $path, bool $excerpt = false): void;

    public function savePeaks(string $mediaId, Peaks $peaks): void;

    /**
     * The waveform kept for an audio file; null before it is computed.
     */
    public function peaks(string $mediaId): ?Peaks;
}
