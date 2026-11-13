<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application;

use Phil\Epub\Book;
use Phil\Epub\EpubWriter;
use Phil\EpubBundle\Application\Port\EpubStorage;

/**
 * Writes books as EPUB 3 files: from a `Book`, or from a source and an identifier, to bytes or to the storage.
 */
final readonly class Epubs
{
    public function __construct(
        private EpubStorage $storage,
        private BookSources $sources = new BookSources(),
        private EpubWriter $writer = new EpubWriter(),
    ) {
    }

    /**
     * The bytes of the EPUB file of a book.
     */
    public function write(Book $book): string
    {
        return $this->writer->toString($book);
    }

    /**
     * Writes a book and keeps it under a relative path; returns where the storage keeps it.
     */
    public function store(Book $book, string $path): string
    {
        return $this->storage->store($path, $this->write($book));
    }

    /**
     * The book a source builds from an identifier.
     */
    public function build(string $source, string $id): Book
    {
        return $this->sources->get($source)->build($id);
    }

    public function sources(): BookSources
    {
        return $this->sources;
    }
}
