<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Image\Image;

/**
 * One download card: what it unlocks, its code, a line under the title — an edition, a format —, and an image of a
 * code to scan, such as a QR code of the address with the code in it.
 */
final readonly class DownloadCard
{
    public function __construct(
        public string $title,
        public string $code,
        public ?string $subtitle = null,
        public ?Image $codeImage = null,
    ) {
        if ('' === trim($title) || '' === trim($code)) {
            throw InvalidDocument::because('A download card has a title and a code.');
        }
    }
}
