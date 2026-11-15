<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Infrastructure\MediaBundle;

use Override;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Exception\AudioNotFound;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use RuntimeException;

/**
 * The audio files uploaded through phil/media-bundle: a job reads the original of a medium, copied to a temporary file
 * named after nothing the uploader chose, created for this copy alone and readable by its owner only, and deleted on
 * release or when the copy fails.
 *
 * The original is streamed into the file from `MediaLibrary::readOriginal()` (phil/media-bundle 0.1.1 and later), so
 * that a long recording never sits whole in memory. An original missing from the storage is a file gone; a storage or a
 * disk that fails is an error the job may retry. The service keeps no state between jobs.
 */
final readonly class MediaLibraryAudioFiles implements AudioFiles
{
    public function __construct(
        private MediaRepository $media,
        private MediaLibrary $library,
        private ?string $temporaryDirectory = null,
    ) {
    }

    #[Override]
    public function localCopy(string $mediaId): string
    {
        $media = $this->media->get($mediaId) ?? throw new AudioNotFound(\sprintf('No medium has the identifier "%s".', $mediaId));
        $path = $this->prefix().sodium_bin2hex(random_bytes(8));
        // Mode "x" refuses a name that already exists, so that no other file or link is written through.
        $target = @fopen($path, 'x');

        if (false === $target) {
            throw new RuntimeException(\sprintf('No temporary file can be created in "%s".', \dirname($path)));
        }

        $copied = false;

        try {
            chmod($path, 0o600);
            $written = $this->copy($media, $target);

            if (false === $written || !fflush($target)) {
                throw new RuntimeException(\sprintf('The copy of %s could not be written.', $media->id));
            }

            $copied = true;
        } catch (InvalidMedia $invalidMedia) {
            throw new AudioNotFound($invalidMedia->getMessage(), 0, $invalidMedia);
        } finally {
            fclose($target);

            if (!$copied) {
                unlink($path);
            }
        }

        return $path;
    }

    #[Override]
    public function release(string $path): void
    {
        if (str_starts_with($path, $this->prefix()) && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Writes the original into the open file of the copy.
     *
     * @param resource $target
     *
     * @return false|int the bytes written, false when the writing failed
     */
    private function copy(Media $media, mixed $target): false|int
    {
        $source = $this->library->readOriginal($media);

        try {
            return stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
        }
    }

    private function prefix(): string
    {
        return rtrim($this->temporaryDirectory ?? sys_get_temp_dir(), '/').'/phil-audio-source-';
    }
}
