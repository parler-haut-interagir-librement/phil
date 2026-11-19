<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

/**
 * A field of an RTF document, as its instruction makes it: a link to an address, a table of contents whose result is
 * left out, or another field whose result is plain text.
 *
 * @internal
 */
final class RtfField
{
    public ?string $link = null;

    public bool $toc = false;

    /**
     * The instruction of the field read: a `HYPERLINK` to an address links the result, a `TOC` makes a table of
     * contents; whether it links to a bookmark of the document, which the import cannot follow.
     */
    public function read(string $instruction): bool
    {
        $this->toc = 1 === preg_match('/^\s*TOC\b/', $instruction);

        if (1 === preg_match('/^\s*HYPERLINK\s+\\\\l\s/', $instruction)) {
            return true;
        }

        if (1 === preg_match('/^\s*HYPERLINK\s+"([^"]+)"/', $instruction, $match)) {
            $this->link = trim($match[1]);
        }

        return false;
    }
}
