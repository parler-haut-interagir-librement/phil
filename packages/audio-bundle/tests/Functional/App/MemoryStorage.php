<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Functional\App;

use LogicException;
use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Peaks;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Exception\AudioNotFound;

/**
 * The storage of the application, which the configuration names: it knows no file.
 */
final class MemoryStorage implements AudioFiles, RenditionStore
{
    #[Override]
    public function localCopy(string $mediaId): string
    {
        throw new AudioNotFound($mediaId);
    }

    #[Override]
    public function release(string $path): void
    {
    }

    #[Override]
    public function save(string $mediaId, string $rendition, AudioFormat $format, string $path, bool $excerpt = false): void
    {
        throw new LogicException('Nothing is saved.');
    }

    #[Override]
    public function savePeaks(string $mediaId, Peaks $peaks): void
    {
        throw new LogicException('Nothing is saved.');
    }

    #[Override]
    public function peaks(string $mediaId): ?Peaks
    {
        return null;
    }
}
