<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\Media;

use Phil\Fonts\FontFile;
use Phil\FontsBundle\Application\FontFiles;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;

/**
 * The fonts uploaded to the media library of phil/media-bundle, read for their metadata: to show a font's family and
 * licence beside the file, or to refuse one whose licence forbids embedding.
 */
final readonly class MediaFonts
{
    public function __construct(
        private MediaLibrary $library,
        private FontFiles $files,
    ) {
    }

    public function read(Media $media): FontFile
    {
        return $this->files->readBytes($this->library->original($media), $media->file()->filename);
    }
}
