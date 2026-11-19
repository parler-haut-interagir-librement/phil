<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaStore;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Report;
use Phil\DocumentImport\StyleMap;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The blocks of an imported document, written in reading order: consecutive list items of one list gathered into a
 * nested list block, consecutive code lines into one code block, notes numbered as they are referenced and written
 * at the end, images given to the media store, SVG images sanitized first, two different images of the same name
 * told apart. An image met inside a table cell, a list item or a note, which hold only text, becomes an image block
 * after the table, the list or the paragraph that holds the note.
 *
 * @internal
 */
final class Builder
{
    /** The characters an image's `alt` holds at most. */
    private const int ALT_LENGTH = 1000;

    /** How many images of one name are told apart by a number. */
    private const int COPIES = 1000;

    public readonly Report $report;

    /** @var array<string, Media> */
    public private(set) array $media = [];

    /** @var list<Block> */
    private array $blocks = [];

    /** @var list<array{int, string}> */
    private array $items = [];

    private ?string $list = null;

    /** @var array<string, mixed> */
    private array $listData = [];

    /** @var list<string> */
    private array $code = [];

    /** @var array{fn: list<string>, en: list<string>} */
    private array $notes = ['fn' => [], 'en' => []];

    /** @var list<array{string, string, bool}> the address and text alternative of each image waiting for its block, and whether it waits for the list */
    private array $deferred = [];

    public function __construct(
        private readonly MediaStore $store,
        private readonly SvgSanitizer $svg,
        public readonly StyleMap $styles = new StyleMap(),
    ) {
        $this->report = new Report();
    }

    /**
     * A paragraph as its style makes it: a heading of a level, a quotation, a line of code or a paragraph; nothing,
     * reported with the name of its style, when the application's map leaves that style out.
     */
    public function block(?Role $role, string $html, ?string $alignment = null, string $style = ''): void
    {
        match ($role) {
            null => $this->report->add(Omitted::Style, $style),
            Role::Paragraph => $this->paragraph($html, $alignment),
            Role::Quote => $this->add('quote', ['text' => $html]),
            Role::Code => $this->code(htmlspecialchars_decode(strip_tags(str_replace('<br>', "\n", $html)), \ENT_QUOTES)),
            default => $this->add('header', ['text' => $html, 'level' => $role->value], $alignment),
        };
    }

    public function paragraph(string $html, ?string $alignment = null): void
    {
        $this->add('paragraph', ['text' => $html], $alignment);
    }

    /**
     * A line of code: it joins the code block written just before, if any.
     */
    private function code(string $line): void
    {
        if ([] === $this->code) {
            $this->flush();
        }

        $this->code[] = $line;
    }

    /**
     * An image taken out of the document: given to the media store when a browser shows it, reported otherwise; met
     * where only text goes — a table cell, a list item, a note —, written as an image block after the block that
     * holds it.
     */
    public function image(Media $media, bool $deferred = false): void
    {
        $url = $this->store($media);

        if (null !== $url) {
            $this->linkedImage($url, $media->description, $deferred);
        }
    }

    /**
     * The address of an image given to the media store when a browser shows it; `null`, and reported, otherwise. An
     * SVG image is sanitized first: what the sanitizer removes is reported, and an image it refuses whole is not
     * stored.
     */
    public function store(Media $media): ?string
    {
        if ('image/svg+xml' === $media->mediaType) {
            $media = $this->sanitized($media);
        }

        if (!$media->isWebImage()) {
            $this->report->add(Omitted::Image, $media->mediaType);

            return null;
        }

        $url = $this->store->store($media);

        for ($copy = 2; isset($this->media[$url]) && $this->media[$url]->contents !== $media->contents && $copy <= self::COPIES; ++$copy) {
            $numbered = $media->numbered($copy);
            $url = $this->store->store($numbered);
        }

        $media = $numbered ?? $media;

        if (isset($this->media[$url]) && $this->media[$url]->contents !== $media->contents) {
            $this->report->add(Omitted::Image, 'same name');

            return null;
        }

        $this->media[$url] ??= $media;

        return $url;
    }

    /**
     * An SVG image with only what the sanitizer keeps; when the sanitizer refuses it whole, an empty medium, which is
     * no image.
     */
    private function sanitized(Media $media): Media
    {
        $sanitized = $this->svg->sanitize($media->contents);

        foreach ($sanitized->isRefused() ? [] : $sanitized->removals as $removal) {
            $this->report->add(Omitted::SvgContent, $removal->reason->value);
        }

        return new Media($media->path, $sanitized->svg, $media->description);
    }

    /**
     * An image the document only links to. Its description is its text alternative, cut to the 1000 characters an
     * image's `alt` holds; an image without one has no `alt`.
     */
    public function linkedImage(string $url, string $description, bool $deferred = false): void
    {
        if ($deferred) {
            $this->deferred[] = [$url, $description, false];

            return;
        }

        $this->add('image', $this->imageData($url, $description));
    }

    /**
     * @return array<string, mixed>
     */
    private function imageData(string $url, string $description): array
    {
        $alt = mb_substr($description, 0, self::ALT_LENGTH);

        return ['file' => ['url' => $url], ...'' === $alt ? [] : ['alt' => $alt]];
    }

    /**
     * @param non-empty-list<list<string>> $rows
     */
    public function table(array $rows, bool $headings): void
    {
        $width = max(array_map(count(...), $rows));

        foreach ($rows as $index => $row) {
            $rows[$index] = array_pad($row, $width, '');
        }

        $this->add('table', ['withHeadings' => $headings, 'content' => $rows]);
    }

    /**
     * An item of a list at a depth, 0 for the outer level: it joins the list written just before when it carries the
     * same key or is nested, and starts a new list otherwise.
     */
    public function listItem(string $key, int $level, string $html, bool $ordered, ?string $counter = null, ?int $start = null): void
    {
        if ($key !== $this->list && (0 === $level || null === $this->list)) {
            $this->flush();
            $this->list = $key;
            $meta = array_filter(['start' => $start, 'counterType' => $counter], static fn (int|string|null $value): bool => null !== $value);
            $this->listData = ['style' => $ordered ? 'ordered' : 'unordered', 'meta' => $meta];
        }

        $this->items[] = [$level, $html];

        foreach (array_keys($this->deferred) as $index) {
            $this->deferred[$index][2] = true;
        }
    }

    public function pageBreak(): void
    {
        $this->add('pageBreak', []);
    }

    public function toc(string $title): void
    {
        $this->add('toc', ['title' => $title]);
    }

    /**
     * A footnote or an endnote met in the text: its content kept for the end of the document, and the reference
     * written in its place, numbered in its own sequence.
     */
    public function note(string $html, bool $endnote): string
    {
        $kind = $endnote ? 'en' : 'fn';
        $this->notes[$kind][] = $html;
        $number = \count($this->notes[$kind]);
        $id = $kind.'-'.$number;

        return '<sup data-footnote="'.$id.'"><a href="#footnote-'.$id.'">'.$number.'</a></sup>';
    }

    public function document(): Document
    {
        $this->flush();
        $this->deferred(false);

        foreach ($this->notes as $kind => $notes) {
            foreach ($notes as $index => $html) {
                $this->append('footnote', ['id' => $kind.'-'.($index + 1), 'text' => $html]);
            }
        }

        return new Document($this->blocks);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function add(string $type, array $data, ?string $alignment = null): void
    {
        $this->flush();
        $this->append($type, $data, null === $alignment || 'left' === $alignment ? [] : ['alignment' => ['alignment' => $alignment]]);
        $this->deferred(false);
    }

    /**
     * The images waiting for the block just written — or for the list just written —, as image blocks.
     */
    private function deferred(bool $list): void
    {
        $waiting = [];

        foreach ($this->deferred as [$url, $description, $forList]) {
            if ($list && !$forList) {
                $waiting[] = [$url, $description, $forList];

                continue;
            }

            $this->append('image', $this->imageData($url, $description));
        }

        $this->deferred = $waiting;
    }

    /**
     * The list or the code block being gathered, written.
     */
    private function flush(): void
    {
        if (null !== $this->list) {
            $at = 0;
            $this->append('list', [...$this->listData, 'items' => self::nest($this->items, $at, 0)]);
            $this->list = null;
            $this->items = [];
            $this->deferred(true);
        }

        if ([] !== $this->code) {
            $this->append('code', ['code' => implode("\n", $this->code)]);
            $this->code = [];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $tunes
     */
    private function append(string $type, array $data, array $tunes = []): void
    {
        $this->blocks[] = new Block('b'.(\count($this->blocks) + 1), $type, $data, $tunes);
    }

    /**
     * The items from a position on that sit at a depth or deeper, those deeper nested under the item before them.
     *
     * @param list<array{int, string}> $items
     *
     * @return list<array{content: string, meta: array{}, items: list<mixed>}>
     */
    private static function nest(array $items, int &$at, int $level): array
    {
        $list = [];

        while (isset($items[$at]) && $items[$at][0] >= $level) {
            $last = array_key_last($list);

            if (null !== $last && $items[$at][0] > $level) {
                $list[$last]['items'] = self::nest($items, $at, $level + 1);

                continue;
            }

            $list[] = ['content' => $items[$at][1], 'meta' => [], 'items' => []];
            ++$at;
        }

        return $list;
    }
}
