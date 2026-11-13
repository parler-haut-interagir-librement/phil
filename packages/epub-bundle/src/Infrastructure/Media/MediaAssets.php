<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Media;

use Phil\Epub\Asset;
use Phil\Epub\Cover;
use Phil\Epub\Exception\InvalidBook;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;

/**
 * The files of the media library of phil/media-bundle as assets of a book: images, the cover with the medium's
 * alternative text, fonts. Each is read whole from the medium's current file; its path in the book is
 * `images/<id>-<file name>` or `fonts/<id>-<file name>` unless one is given.
 */
final readonly class MediaAssets
{
    public function __construct(
        private MediaLibrary $library,
    ) {
    }

    public function image(Media $media, ?string $path = null): Asset
    {
        return Asset::image($path ?? 'images/'.$this->name($media), $this->library->original($media));
    }

    /**
     * The cover of a book: the image, and the medium's alternative text, which a cover needs.
     */
    public function cover(Media $media, ?string $path = null): Cover
    {
        $alt = $media->alt() ?? throw InvalidBook::because(\sprintf('The medium %s has no alternative text, which the cover of a book needs.', $media->id));

        return new Cover($this->image($media, $path), $alt);
    }

    public function font(Media $media, bool $obfuscate = false, ?string $path = null): Asset
    {
        return Asset::font($path ?? 'fonts/'.$this->name($media), $this->library->original($media), $obfuscate);
    }

    private function name(Media $media): string
    {
        return $media->id.'-'.$media->file()->safeFilename();
    }
}
