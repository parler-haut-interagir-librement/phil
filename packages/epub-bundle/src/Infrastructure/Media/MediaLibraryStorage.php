<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Media;

use Override;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Application\Port\EpubStorage;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\MediaException;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Keeps the books in the media library of phil/media-bundle, in a collection when one is configured: each book is a
 * new medium named after the last segment of its path, uploaded from a temporary file removed afterwards, and the
 * medium's identifier is where it is kept.
 */
final readonly class MediaLibraryStorage implements EpubStorage
{
    public function __construct(
        private MediaLibrary $library,
        private MediaRepository $media,
        private ?string $collection = null,
        private ?string $temporaryDirectory = null,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    #[Override]
    public function store(string $path, string $bytes): string
    {
        $collection = null === $this->collection ? null : ($this->media->collection($this->collection)
            ?? throw StorageFailed::because(\sprintf('The media library has no collection "%s" to keep the books in.', $this->collection)));
        $file = $this->filesystem->tempnam($this->temporaryDirectory ?? sys_get_temp_dir(), 'epub');

        try {
            $this->filesystem->dumpFile($file, $bytes);

            return $this->library->upload($file, basename(str_replace('\\', '/', $path)), $collection)->id;
        } catch (MediaException $mediaException) {
            throw StorageFailed::because(\sprintf('The book "%s" cannot be kept in the media library: %s', $path, $mediaException->getMessage()), $mediaException);
        } finally {
            $this->filesystem->remove($file);
        }
    }
}
