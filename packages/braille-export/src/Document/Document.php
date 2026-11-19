<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * A document to put into braille: its blocks in reading order, the language of its text and its title.
 */
final readonly class Document
{
    /**
     * @param list<Block> $blocks
     * @param string      $language the BCP 47 tag of the text, such as `fr` or `en-CA`, which chooses the braille code
     * @param string      $title    the title, written into the metadata of a PEF file; not laid out
     */
    public function __construct(
        public array $blocks,
        public string $language = 'en',
        public string $title = '',
    ) {
    }
}
