<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Output;

use DateTimeImmutable;

/**
 * The Dublin Core metadata of a PEF file besides its title and language, which come from the book.
 */
final readonly class PefMetadata
{
    /**
     * @param string|null            $identifier the identifier of the publication, such as an ISBN or a URN; derived from
     *                                           the content of the book without it, so that the same book gets the same one
     * @param string                 $creator    the author
     * @param string                 $publisher  the publisher
     * @param DateTimeImmutable|null $date       the date of the publication
     */
    public function __construct(
        public ?string $identifier = null,
        public string $creator = '',
        public string $publisher = '',
        public ?DateTimeImmutable $date = null,
    ) {
    }
}
