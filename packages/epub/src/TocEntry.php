<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * An entry of the table of contents: a label, the address it leads to, relative to the package directory (such as
 * `chapter-2.xhtml#rules`), and its own entries.
 */
final readonly class TocEntry
{
    /**
     * @param list<TocEntry> $children
     *
     * @throws InvalidBook
     */
    public function __construct(public string $label, public string $href, public array $children = [])
    {
        if ('' === trim($label)) {
            throw InvalidBook::because(\sprintf('A table of contents entry needs a label (%s).', $href));
        }
    }
}
