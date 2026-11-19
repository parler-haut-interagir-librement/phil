<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export\Contract;

use Phil\DocumentImport\Internal\Export\ListItem;
use Phil\DocumentImport\Internal\Export\Picture;
use Phil\DocumentImport\Internal\Export\Run;

/**
 * What writes a word processing document, block after block, then gives its files.
 *
 * @internal
 */
interface Writer
{
    /**
     * @param list<Run> $runs
     * @param string    $style `paragraph`, `quote`, `caption` or `title`
     */
    public function paragraph(array $runs, ?string $alignment = null, string $style = 'paragraph'): void;

    /**
     * @param list<Run> $runs
     */
    public function heading(int $level, array $runs, ?string $alignment = null): void;

    /**
     * @param list<ListItem> $items
     */
    public function list(array $items, bool $ordered, ?string $counter, ?int $start): void;

    public function code(string $code): void;

    /**
     * @param list<list<list<Run>>> $rows
     */
    public function table(array $rows, bool $headings): void;

    public function image(Picture $picture): void;

    public function pageBreak(): void;

    public function delimiter(): void;

    public function toc(string $title): void;

    /**
     * The files of the document, by their path in the package, in package order.
     *
     * @param array<string, string> $metadata
     *
     * @return array<string, string>
     */
    public function files(array $metadata): array;
}
