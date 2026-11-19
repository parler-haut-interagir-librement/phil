<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The result of an import: the UJO document, checked and in canonical form; the metadata of the file — title,
 * creator, subject, description, keywords, language, dates, or the front matter of a Markdown file —; the images
 * taken out of it, by the address their blocks show; and the report of what was left out, which can be rendered
 * with the document.
 */
final readonly class Imported
{
    /**
     * @param array<array-key, mixed> $metadata
     * @param array<string, Media>    $media
     */
    public function __construct(
        public Format $format,
        public Document $document,
        public array $metadata,
        public array $media,
        public Report $report,
    ) {
    }

    /**
     * The document with the report of what was left out as a last `warning` block, so that a reader sees it with
     * the text; the document as it is when nothing was left out.
     */
    public function documentWithReport(string $title = 'What was not imported'): Document
    {
        if ($this->report->isEmpty()) {
            return $this->document;
        }

        $ids = array_map(static fn (Block $block): string => $block->id, $this->document->blocks);
        $id = 'import-report';

        for ($copy = 2; \in_array($id, $ids, true); ++$copy) {
            $id = 'import-report-'.$copy;
        }

        return $this->document->withBlocks([...$this->document->blocks, $this->report->toBlock($title, $id)]);
    }
}
