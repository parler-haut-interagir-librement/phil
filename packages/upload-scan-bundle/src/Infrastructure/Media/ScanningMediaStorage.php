<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Media;

use Override;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\UploadScanBundle\Application\UploadScanner;

/**
 * The storage of `phil/media-bundle` behind the antivirus: every original written — an upload, a replacement, an image
 * of an imported document — is inspected first, under the context `media`, and refused with `InvalidMedia` unless the
 * policy keeps it. What the library renders from an original is not scanned again.
 */
final readonly class ScanningMediaStorage implements MediaStorage
{
    public function __construct(
        private MediaStorage $inner,
        private UploadScanner $scanner,
    ) {
    }

    #[Override]
    public function write(string $key, string $contents): void
    {
        if (str_starts_with($key, 'originals/')) {
            $inspection = $this->scanner->inspectContents($contents, basename($key), 'media');

            if ($inspection->infected()) {
                throw InvalidMedia::because('The file contains a threat and was refused.');
            }

            if (!$inspection->accepted()) {
                throw InvalidMedia::because('The file could not be checked for threats. Please try again later.');
            }
        }

        $this->inner->write($key, $contents);
    }

    #[Override]
    public function read(string $key): ?string
    {
        return $this->inner->read($key);
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->inner->deletePrefix($prefix);
    }
}
