<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Support;

use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Peaks;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Exception\AudioNotFound;

/**
 * The storage ports of the jobs in memory: files copied to a directory, and a store that keeps what it is given.
 */
final class MemoryAudio implements AudioFiles, RenditionStore
{
    /** @var array<string, string> the contents of each file, by identifier */
    public array $uploads = [];

    /** @var list<string> */
    public array $released = [];

    /** @var array<string, string> what the store kept, by key */
    public array $kept = [];

    /** @var array<string, Peaks> */
    public array $waveforms = [];

    public function __construct(
        private readonly string $directory,
    ) {
    }

    #[Override]
    public function localCopy(string $mediaId): string
    {
        $contents = $this->uploads[$mediaId] ?? throw new AudioNotFound('No file '.$mediaId);
        $path = $this->directory.'/copy-'.$mediaId;
        file_put_contents($path, $contents);

        return $path;
    }

    #[Override]
    public function release(string $path): void
    {
        $this->released[] = $path;
        unlink($path);
    }

    #[Override]
    public function save(string $mediaId, string $rendition, AudioFormat $format, string $path, bool $excerpt = false): void
    {
        $this->kept[$mediaId.'/'.$rendition.($excerpt ? '-excerpt' : '').'.'.$format->extension()] = (string) file_get_contents($path);
    }

    #[Override]
    public function savePeaks(string $mediaId, Peaks $peaks): void
    {
        $this->waveforms[$mediaId] = $peaks;
    }

    #[Override]
    public function peaks(string $mediaId): ?Peaks
    {
        return $this->waveforms[$mediaId] ?? null;
    }
}
