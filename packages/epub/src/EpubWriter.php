<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Exception\WriteFailed;
use Phil\Epub\Internal\Assembler;
use ZipArchive;

/**
 * Writes a book as an EPUB 3 file: a ZIP archive whose first entry is the `mimetype` file, stored without compression,
 * followed by the container, the package document, the navigation document, the content documents and the other files,
 * compressed. Every entry carries the book's modification time, so the same book gives the same bytes.
 *
 * `toString()` writes through a temporary file, in `$temporaryDirectory` or the system's temporary directory.
 */
final readonly class EpubWriter
{
    public function __construct(private ?string $temporaryDirectory = null)
    {
    }

    /**
     * @throws InvalidBook
     * @throws WriteFailed
     */
    public function write(Book $book, string $path): void
    {
        $files = new Assembler()->assemble($book);
        $zip = new ZipArchive();

        if (!is_writable(\dirname($path)) || true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw WriteFailed::because(\sprintf('The archive "%s" cannot be created.', $path));
        }

        $time = $book->metadata->modified->getTimestamp();

        foreach ($files as $name => $bytes) {
            $zip->addFromString($name, $bytes);
            $zip->setCompressionName($name, 'mimetype' === $name ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
            $zip->setMtimeName($name, $time);
        }

        if (!$zip->close()) {
            throw WriteFailed::because(\sprintf('The archive "%s" cannot be written.', $path));
        }
    }

    /**
     * The bytes of the EPUB file of a book, written through a temporary file.
     *
     * @throws InvalidBook
     * @throws WriteFailed
     */
    public function toString(Book $book): string
    {
        $path = tempnam($this->temporaryDirectory ?? sys_get_temp_dir(), 'epub');

        if (false === $path) {
            throw WriteFailed::because('No temporary file can be created.');
        }

        try {
            $this->write($book, $path);
            $bytes = file_get_contents($path);

            if (false === $bytes) {
                throw WriteFailed::because(\sprintf('The archive "%s" cannot be read back.', $path));
            }

            return $bytes;
        } finally {
            unlink($path);
        }
    }
}
