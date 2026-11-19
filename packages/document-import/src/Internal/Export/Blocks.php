<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

use Phil\DocumentImport\Contract\MediaSource;
use Phil\DocumentImport\Internal\Export\Contract\Writer;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Report;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The blocks of a UJO document given to a writer in order: paragraphs, headings, lists, quotations, code, tables,
 * images fetched from the application, delimiters, page breaks, tables of contents, warnings, links and files; the
 * footnotes written where they are referenced, those never referenced at the end. A block a word processing document
 * has no place for, and an image that cannot be fetched or written, are reported.
 *
 * @internal
 */
final readonly class Blocks
{
    /** The image types a word processing document holds. */
    private const array PICTURES = ['image/png', 'image/jpeg', 'image/gif'];

    public function __construct(
        private ?MediaSource $media,
        private SvgSanitizer $svg,
        private Report $report,
        private bool $svgPictures,
    ) {
    }

    /**
     * The runs of each footnote of a document, by its identifier.
     *
     * @return array<string, list<Run>>
     */
    public static function notes(Document $document): array
    {
        $notes = [];

        foreach ($document->blocks as $block) {
            if ('footnote' === $block->type) {
                $notes[self::text($block->data, 'id')] = Runs::of(self::text($block->data, 'text'));
            }
        }

        return $notes;
    }

    public function write(Document $document, Writer $writer): void
    {
        $referenced = [];

        foreach ($document->blocks as $block) {
            $this->block($block, $writer);
            $referenced = [...$referenced, ...$this->references($block)];
        }

        foreach ($document->blocks as $block) {
            if ('footnote' === $block->type && !\in_array(self::text($block->data, 'id'), $referenced, true)) {
                $writer->paragraph(Runs::of(self::text($block->data, 'text')), null, 'caption');
            }
        }
    }

    private function block(Block $block, Writer $writer): void
    {
        $data = $block->data;
        $tune = $block->tunes['alignment'] ?? null;
        $alignment = \is_array($tune) ? $tune['alignment'] ?? null : null;
        $alignment = \is_string($alignment) && 'left' !== $alignment ? $alignment : null;

        match ($block->type) {
            'paragraph' => $writer->paragraph(Runs::of(self::text($data, 'text')), $alignment),
            'header' => $writer->heading(max(1, min(6, \is_int($data['level'] ?? null) ? $data['level'] : 2)), Runs::of(self::text($data, 'text')), $alignment),
            'list' => $writer->list(self::items($data['items'] ?? [], 'checklist' === ($data['style'] ?? null), 0), 'ordered' === ($data['style'] ?? null), $this->counter($data), $this->start($data)),
            'quote' => $this->quote($data, $writer),
            'code' => $writer->code(self::text($data, 'code')),
            'table' => $writer->table($this->rows($data['content'] ?? []), true === ($data['withHeadings'] ?? false)),
            'image' => $this->image($data, $writer),
            'delimiter' => $writer->delimiter(),
            'pageBreak' => $writer->pageBreak(),
            'toc' => $writer->toc(self::text($data, 'title')),
            'warning' => $this->warning($data, $writer),
            'linkTool' => $this->link(self::text($data, 'link'), self::text(\is_array($data['meta'] ?? null) ? $data['meta'] : [], 'title'), $writer),
            'attaches' => $this->link(self::text(\is_array($data['file'] ?? null) ? $data['file'] : [], 'url'), self::text($data, 'title'), $writer),
            'embed' => $this->link(self::text($data, 'source'), self::text($data, 'caption'), $writer),
            'footnote' => null,
            default => $this->report->add(Omitted::Block, $block->type),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function quote(array $data, Writer $writer): void
    {
        $caption = self::text($data, 'caption');
        $runs = Runs::of(self::text($data, 'text'));

        if ('' !== trim(strip_tags($caption))) {
            $runs = [...$runs, new Run('', [], null, true), new Run('— '), ...Runs::of($caption)];
        }

        $writer->paragraph($runs, 'center' === ($data['alignment'] ?? null) ? 'center' : null, 'quote');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function warning(array $data, Writer $writer): void
    {
        $title = Runs::of(self::text($data, 'title'));

        if ([] !== $title) {
            $writer->paragraph(array_map(static fn (Run $run): Run => new Run($run->text, [...$run->marks, Mark::Bold], $run->link, $run->break, $run->note), $title), null, 'quote');
        }

        $writer->paragraph(Runs::of(self::text($data, 'message')), null, 'quote');
    }

    private function link(string $url, string $title, Writer $writer): void
    {
        if (1 !== preg_match('/^(https?:\/\/|mailto:)/i', $url)) {
            $this->report->add(Omitted::Block, 'link');

            return;
        }

        $writer->paragraph([new Run('' === trim($title) ? $url : trim(strip_tags($title)), [], $url)]);
    }

    /**
     * An image fetched from the application: written when it is a PNG, JPEG or GIF image — an SVG image sanitized
     * first where the format holds one —, its address as a link otherwise.
     *
     * @param array<array-key, mixed> $data
     */
    private function image(array $data, Writer $writer): void
    {
        $url = self::text(\is_array($data['file'] ?? null) ? $data['file'] : [], 'url');
        $alt = self::text($data, 'alt');
        $caption = self::text($data, 'caption');
        $bytes = '' === $url ? null : $this->media?->fetch($url);
        $type = null === $bytes ? null : Media::mediaTypeOf($bytes);

        if (null !== $bytes && 'image/svg+xml' === $type && $this->svgPictures) {
            $sanitized = $this->svg->sanitize($bytes);
            $bytes = $sanitized->isRefused() ? null : $sanitized->svg;
        }

        if (null !== $bytes && (\in_array($type, self::PICTURES, true) || ('image/svg+xml' === $type && $this->svgPictures))) {
            $writer->image(new Picture($bytes, $type, '' === $alt ? Runs::text($caption) : $alt));
        } else {
            $this->report->add(Omitted::Image, null === $bytes ? 'not fetched' : (string) $type);

            if (1 === preg_match('/^https?:\/\//i', $url)) {
                $writer->paragraph([new Run('' === $alt ? $url : $alt, [], $url)]);
            }
        }

        if ('' !== trim(strip_tags($caption))) {
            $writer->paragraph(Runs::of($caption), 'center', 'caption');
        }
    }

    /**
     * @return list<ListItem>
     */
    private static function items(mixed $items, bool $checklist, int $level): array
    {
        $list = [];

        foreach (\is_array($items) && $level < 9 ? $items : [] as $item) {
            if (\is_string($item)) {
                $list[] = new ListItem(Runs::of($item), $checklist ? false : null);

                continue;
            }

            if (!\is_array($item)) {
                continue;
            }

            $meta = \is_array($item['meta'] ?? null) ? $item['meta'] : [];
            $list[] = new ListItem(Runs::of(self::text($item, 'content')), $checklist ? true === ($meta['checked'] ?? false) : null, self::items($item['items'] ?? [], $checklist, $level + 1));
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function counter(array $data): ?string
    {
        $meta = \is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return \is_string($meta['counterType'] ?? null) ? $meta['counterType'] : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function start(array $data): ?int
    {
        $meta = \is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return \is_int($meta['start'] ?? null) ? $meta['start'] : null;
    }

    /**
     * @return list<list<list<Run>>>
     */
    private function rows(mixed $content): array
    {
        $rows = [];

        foreach (\is_array($content) ? $content : [] as $row) {
            $cells = [];

            foreach (\is_array($row) ? $row : [] as $cell) {
                $cells[] = Runs::of(\is_array($cell) ? self::text($cell, 'text') : (\is_string($cell) ? $cell : ''));
            }

            if ([] !== $cells) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * The footnotes a block refers to.
     *
     * @return list<string>
     */
    private function references(Block $block): array
    {
        $texts = '';
        $data = $block->data;
        array_walk_recursive($data, static function (mixed $value) use (&$texts): void {
            $texts .= \is_string($value) ? $value : '';
        });
        preg_match_all('/data-footnote="([^"]+)"/', $texts, $matches);

        return $matches[1];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_string($value) ? $value : '';
    }
}
