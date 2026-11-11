<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Html\SafeUrl;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Bounded;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Schema\TextOrShape;

/**
 * The official Editor.js tools, `@editorjs/*`, with the forms of their data written by older versions and by the
 * forks that kept their name: legacy lists and checklists, nested checklists, the simple image, alignment saved in the
 * data, a code block's language.
 */
final class StandardTypes
{
    /** The counters of an ordered list. */
    public const array COUNTERS = ['numeric', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha'];

    /**
     * @return list<BlockType>
     */
    public static function all(?EmbedServices $services = null): array
    {
        return [
            self::paragraph(),
            self::header(),
            self::list(),
            self::quote(),
            self::warning(),
            self::delimiter(),
            self::code(),
            self::table(),
            self::image(),
            self::linkTool(),
            self::attaches(),
            self::embed($services ?? new EmbedServices()),
            self::personality(),
        ];
    }

    /**
     * `{"text": html}`; an `alignment` in the data, from the alignment forks, moves to the `alignment` tune.
     */
    public static function paragraph(): BlockType
    {
        return new BlockType('paragraph', new Shape([
            'text' => Property::required(new Html()),
        ]), upgrade: self::moveToTunes(...));
    }

    /**
     * `{"text": html, "level": 1 to 6}`, the text filled in to publish; an `alignment` or an `anchor` in the data
     * moves to its tune.
     */
    public static function header(): BlockType
    {
        return new BlockType('header', new Shape([
            'text' => Property::required(new Html())->filled(),
            'level' => Property::defaults(new Integer(1, 6), 2),
        ]), upgrade: self::moveToTunes(...));
    }

    /**
     * The list of `@editorjs/list` 2: `{"style": "ordered" | "unordered" | "checklist", "meta": {"start",
     * "counterType"}, "items": [{"content", "meta": {"checked"}, "items": [...]}]}`. Also read: the list 1 with items
     * as texts, the `checklist` tool, and the nested checklist whose items carry `checked`. To publish, a list holds an
     * item at least, and each item is filled in.
     */
    public static function list(): BlockType
    {
        return new BlockType('list', new Shape([
            'style' => Property::required(new Choice(['unordered', 'ordered', 'checklist'])),
            'meta' => Property::defaults(new Shape([
                'start' => Property::optional(new Integer(0)),
                'counterType' => Property::optional(new Choice(self::COUNTERS)),
            ]), []),
            'items' => Property::required(new Bounded(new ListOf(self::listItem()), 1)),
        ]), ['checklist'], upgrade: self::upgradeList(...));
    }

    /**
     * `{"text": html, "caption": html, "alignment": "left" | "center"}`, the text filled in to publish.
     */
    public static function quote(): BlockType
    {
        return new BlockType('quote', new Shape([
            'text' => Property::required(new Html())->filled(),
            'caption' => Property::defaults(new Html(), ''),
            'alignment' => Property::defaults(new Choice(['left', 'center']), 'left'),
        ]));
    }

    /**
     * `{"title": html, "message": html}`.
     */
    public static function warning(): BlockType
    {
        return new BlockType('warning', new Shape([
            'title' => Property::defaults(new Html(), ''),
            'message' => Property::defaults(new Html(), ''),
        ]));
    }

    /**
     * `{}`, or the styled delimiter's `{"style": "star" | "dash" | "line", "lineWidth", "lineThickness"}`.
     */
    public static function delimiter(): BlockType
    {
        return new BlockType('delimiter', new Shape([
            'style' => Property::optional(new Choice(['star', 'dash', 'line'])),
            'lineWidth' => Property::optional(new Integer(1, 100)),
            'lineThickness' => Property::optional(new Integer(1, 20)),
        ]));
    }

    /**
     * `{"code": text, "language": name}`; the language comes from the forks that save it, and the line numbers of
     * CodeFlask are left to the look. The code is filled in to publish.
     */
    public static function code(): BlockType
    {
        return new BlockType('code', new Shape([
            'code' => Property::required(new Plain())->filled(),
            'language' => Property::optional(new Plain(32, '/^[A-Za-z0-9_+#.-]{1,32}$/D')),
        ]), upgrade: static fn (Block $block): Block => $block->withData(array_diff_key($block->data, array_flip(['showlinenumbers']))));
    }

    /**
     * `{"html": html}`, kept as the `raw` profile allows. Not in the default registry: a site that accepts raw HTML
     * from its writers adds it.
     */
    public static function raw(): BlockType
    {
        return new BlockType('raw', new Shape([
            'html' => Property::required(new Html('raw')),
        ]));
    }

    /** The alignments of a cell or a column. */
    public const array ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * `{"withHeadings": bool, "stretched": bool, "content": [[cell, ...], ...]}`, every row as wide as the first. A
     * cell is its inline HTML, or, in the rich form, `{"text": html, "colspan": 1 to 100, "rowspan": 1 to 1000,
     * "header": bool, "align": "left" | "center" | "right"}`; the rich form adds the table's `caption` and the
     * alignment of each column, `align`, `""` for none. A cell spanning rows covers the columns below it, as in HTML.
     * To publish, a table holds a row at least.
     */
    public static function table(): BlockType
    {
        $alignment = new Choice(self::ALIGNMENTS);

        return new BlockType('table', new Shape([
            'withHeadings' => Property::defaults(new Boolean(), false),
            'stretched' => Property::defaults(new Boolean(), false),
            'caption' => Property::optional(new Html()),
            'align' => Property::optional(new ListOf(new Choice(['', ...self::ALIGNMENTS]))),
            'content' => Property::defaults(new ListOf(new ListOf(new TextOrShape(new Html(), new Shape([
                'text' => Property::required(new Html()),
                'colspan' => Property::optional(new Integer(1, 100)),
                'rowspan' => Property::optional(new Integer(1, 1000)),
                'header' => Property::optional(new Boolean()),
                'align' => Property::optional($alignment),
            ])))), [])->filled(),
        ]), check: static function (array $data, string $path): void {
            /** @var list<list<mixed>> $rows */
            $rows = $data['content'];
            $layout = TableGrid::layout($rows);

            foreach ($layout as $index => [$width]) {
                if ($width !== $layout[0][0]) {
                    throw InvalidDocument::at($path.'.content['.$index.']', 'every row must have as many cells as the first');
                }
            }

            /** @var list<string> $columns */
            $columns = $data['align'] ?? [];

            if (\count($columns) > ($layout[0][0] ?? 0)) {
                throw InvalidDocument::at($path.'.align', 'there are more alignments than columns');
            }
        });
    }

    /**
     * The image of `@editorjs/image`: `{"file": {"url", ...}, "caption", "withBorder", "withBackground",
     * "stretched"}`, the file keeping what the server added; with its own alternative text, `alt`, when the image has
     * one apart from its caption, and the `unsplash` credit of the inline image tool. The simple image's
     * `{"url", ...}` is read into `file`.
     */
    public static function image(): BlockType
    {
        return new BlockType('image', new Shape([
            'file' => Property::required(new Shape(['url' => Property::required(new Link())], true)),
            'caption' => Property::defaults(new Html(), ''),
            'alt' => Property::optional(new Plain(1000)),
            'withBorder' => Property::defaults(new Boolean(), false),
            'withBackground' => Property::defaults(new Boolean(), false),
            'stretched' => Property::defaults(new Boolean(), false),
            'unsplash' => Property::optional(new Shape([
                'author' => Property::required(new Plain(200)),
                'profileLink' => Property::required(new Link()),
            ])),
        ]), upgrade: static function (Block $block): Block {
            $data = $block->data;

            if (!isset($data['file']) && isset($data['url'])) {
                $data = ['file' => ['url' => $data['url']], ...array_diff_key($data, array_flip(['url']))];
            }

            return $block->withData($data);
        });
    }

    /**
     * `{"link": url, "meta": {"title", "site_name", "description", "image": {"url"}}}`, the meta keeping what the
     * server added.
     */
    public static function linkTool(): BlockType
    {
        return new BlockType('linkTool', new Shape([
            'link' => Property::required(new Link(SafeUrl::MEDIA, false)),
            'meta' => Property::defaults(new Shape([
                'title' => Property::optional(new Plain(500)),
                'site_name' => Property::optional(new Plain(200)),
                'description' => Property::optional(new Plain(2000)),
                'image' => Property::optional(new Shape(['url' => Property::required(new Link())], true)),
            ], true), []),
        ]), ['link']);
    }

    /**
     * `{"file": {"url", "size", "name", "extension"}, "title": text}`.
     */
    public static function attaches(): BlockType
    {
        return new BlockType('attaches', new Shape([
            'file' => Property::required(new Shape([
                'url' => Property::required(new Link()),
                'size' => Property::optional(new Integer(0)),
                'name' => Property::optional(new Plain(255)),
                'extension' => Property::optional(new Plain(16)),
            ], true)),
            'title' => Property::defaults(new Plain(255), ''),
        ]));
    }

    /**
     * `{"service", "source", "embed", "width", "height", "caption"}`: a service of the list, and an `https` embedding
     * address on one of its hosts. Where no frame plays — on paper —, the block shows its `poster`, an image, its
     * `title` and its source, with a QR code of the source when `qrCode` is true.
     */
    public static function embed(EmbedServices $services): BlockType
    {
        return new BlockType('embed', new Shape([
            'service' => Property::required(new Choice($services->names())),
            'source' => Property::required(new Link(SafeUrl::MEDIA, false)),
            'embed' => Property::required(new Link(['https'], false)),
            'width' => Property::optional(new Integer(1, 10_000)),
            'height' => Property::optional(new Integer(1, 10_000)),
            'caption' => Property::defaults(new Html(), ''),
            'poster' => Property::optional(new Link()),
            'title' => Property::optional(new Plain(200)),
            'qrCode' => Property::optional(new Boolean()),
        ]), check: static function (array $data, string $path) use ($services): void {
            /** @var array{service: string, embed: string} $data */
            if (!$services->allows($data['service'], $data['embed'])) {
                throw InvalidDocument::at($path.'.embed', \sprintf('the address is not on a host of %s', $data['service']));
            }
        });
    }

    /**
     * `{"name", "description", "link", "photo"}`, the name filled in to publish.
     */
    public static function personality(): BlockType
    {
        return new BlockType('personality', new Shape([
            'name' => Property::required(new Plain(200))->filled(),
            'description' => Property::defaults(new Plain(2000), ''),
            'link' => Property::optional(new Link(SafeUrl::MEDIA, false)),
            'photo' => Property::optional(new Link()),
        ]));
    }

    /**
     * An item of a list, holding the items nested in it.
     */
    private static function listItem(): Shape
    {
        return new Shape([
            'content' => Property::required(new Html())->filled(),
            'meta' => Property::defaults(new Shape([
                'checked' => Property::optional(new Boolean()),
            ]), []),
            'items' => Property::defaults(new ListOf(new Lazy(self::listItem(...))), []),
        ]);
    }

    private static function moveToTunes(Block $block): Block
    {
        $tunes = $block->tunes;

        foreach (['alignment', 'anchor'] as $name) {
            if (\array_key_exists($name, $block->data)) {
                $tunes[$name] = [$name => $block->data[$name]];
            }
        }

        return new Block($block->id, $block->type, array_diff_key($block->data, array_flip(['alignment', 'anchor'])), $tunes);
    }

    private static function upgradeList(Block $block): Block
    {
        $data = $block->data;
        $items = \is_array($data['items'] ?? null) ? $data['items'] : [];

        if ('checklist' === $block->type) {
            $data = ['style' => 'checklist', 'items' => array_map(static fn (mixed $item): mixed => \is_array($item) ? [
                'content' => $item['text'] ?? null,
                'meta' => ['checked' => $item['checked'] ?? false],
            ] : $item, $items)];
        } elseif (\is_array($items[0] ?? null) && \array_key_exists('checked', $items[0])) {
            $data = ['style' => 'checklist', 'items' => self::checkedItems($items)];
        } elseif (\is_string($items[0] ?? null)) {
            $data['items'] = array_map(static fn (mixed $item): mixed => \is_string($item) ? ['content' => $item] : $item, $items);
        }

        return $block->withData($data);
    }

    /**
     * The items of a nested checklist, `{"content", "checked", "items"}`, as list items.
     *
     * @param array<array-key, mixed> $items
     *
     * @return array<array-key, mixed>
     */
    private static function checkedItems(array $items): array
    {
        return array_map(static fn (mixed $item): mixed => \is_array($item) ? [
            'content' => $item['content'] ?? null,
            'meta' => ['checked' => true === ($item['checked'] ?? null)],
            'items' => self::checkedItems(\is_array($item['items'] ?? null) ? $item['items'] : []),
        ] : $item, $items);
    }
}
