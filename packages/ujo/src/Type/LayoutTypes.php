<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;

/**
 * The types of printed pages and game books, named at the same level as the Editor.js tools: page and column breaks,
 * page styles, spanning and flowing containers, spacing, page numbers, running footers, footnotes, tables of contents,
 * indexes, watermarks, boxed notes and read-aloud text, stat blocks, definition lists, credits, banners, decorations,
 * QR codes and styled containers.
 */
final class LayoutTypes
{
    /**
     * @return list<BlockType>
     */
    public static function all(): array
    {
        return [
            new BlockType('pageBreak', new Shape()),
            new BlockType('columnBreak', new Shape()),
            self::pageStyle(),
            self::container('wide'),
            self::columnFlow(),
            self::spacer(),
            self::pageNumber(),
            self::runningFooter(),
            self::footnote(),
            self::toc(),
            self::index(),
            self::abbreviations(),
            self::watermark(),
            self::box('note'),
            self::box('descriptive'),
            self::statblock(),
            self::definitionList(),
            self::credit(),
            self::banner(),
            self::decoration(),
            self::qrCode(),
            self::container('container', true),
        ];
    }

    /**
     * `{"variant": "frontCover" | "insideCover" | "partCover" | "backCover", "classes": [name, ...]}`, the style of the
     * page that holds it; free CSS is left to the look.
     */
    public static function pageStyle(): BlockType
    {
        return new BlockType('pageStyle', new Shape([
            'variant' => Property::optional(new Choice(['frontCover', 'insideCover', 'partCover', 'backCover'])),
            'classes' => Property::defaults(new ListOf(Plain::token()), []),
        ]));
    }

    /**
     * `{"blocks": [...]}`, for `wide`, which spans every column of the page; with `"classes": [name, ...]`, at least
     * one, for `container`, whose classes the look declares.
     */
    public static function container(string $name, bool $classes = false): BlockType
    {
        return new BlockType($name, new Shape([
            ...$classes ? ['classes' => Property::required(new ListOf(Plain::token(), 1))] : [],
            'blocks' => Property::required(new Blocks()),
        ]));
    }

    /**
     * `{"count": 2 to 6, "blocks": [...]}`, blocks flowing in columns inside the page's.
     */
    public static function columnFlow(): BlockType
    {
        return new BlockType('columnFlow', new Shape([
            'count' => Property::defaults(new Integer(2, 6), 2),
            'blocks' => Property::required(new Blocks()),
        ]));
    }

    /**
     * `{"lines": 1 to 50}`, empty lines.
     */
    public static function spacer(): BlockType
    {
        return new BlockType('spacer', new Shape([
            'lines' => Property::defaults(new Integer(1, 50), 1),
        ]));
    }

    /**
     * `{"mode": "auto" | "fixed" | "skip" | "reset", "value": text}`: the page's number counted, written, left out of
     * the count, or the count restarted.
     */
    public static function pageNumber(): BlockType
    {
        return new BlockType('pageNumber', new Shape([
            'mode' => Property::defaults(new Choice(['auto', 'fixed', 'skip', 'reset']), 'auto'),
            'value' => Property::optional(new Plain(16)),
        ]));
    }

    /**
     * `{"text": html}`, the label at the foot of each page until the next one.
     */
    public static function runningFooter(): BlockType
    {
        return new BlockType('runningFooter', new Shape([
            'text' => Property::required(new Html()),
        ]));
    }

    /**
     * `{"id": name, "text": html}`, the note that `<sup data-footnote="id">` refers to.
     */
    public static function footnote(): BlockType
    {
        return new BlockType('footnote', new Shape([
            'id' => Property::required(Plain::token()),
            'text' => Property::required(new Html()),
        ]));
    }

    /**
     * `{"title": text, "depth": 1 to 6, "wide": bool}`, a table of contents generated from the headings at render.
     */
    public static function toc(): BlockType
    {
        return new BlockType('toc', new Shape([
            'title' => Property::defaults(new Plain(200), ''),
            'depth' => Property::defaults(new Integer(1, 6), 3),
            'wide' => Property::defaults(new Boolean(), false),
        ]), maxPerDocument: 10);
    }

    /**
     * `{"title": text}`, the abbreviations of the document generated at render: each `<abbr title>` once, with the
     * first meaning it is given, in alphabetical order.
     */
    public static function abbreviations(): BlockType
    {
        return new BlockType('abbreviations', new Shape([
            'title' => Property::defaults(new Plain(200), ''),
        ]), maxPerDocument: 10);
    }

    /**
     * `{"title": text, "columns": 1 to 6, "entries": [{"term", "pages", "children": [...]}, ...]}`.
     */
    public static function index(): BlockType
    {
        return new BlockType('index', new Shape([
            'title' => Property::defaults(new Plain(200), ''),
            'columns' => Property::defaults(new Integer(1, 6), 2),
            'entries' => Property::defaults(new ListOf(self::indexEntry()), []),
        ]));
    }

    /**
     * `{"text": text}`, written across the page.
     */
    public static function watermark(): BlockType
    {
        return new BlockType('watermark', new Shape([
            'text' => Property::required(new Plain(100)),
        ]));
    }

    /**
     * `{"title": html, "blocks": [...]}`: a boxed note, `note`, or read-aloud text, `descriptive`.
     */
    public static function box(string $name): BlockType
    {
        return new BlockType($name, new Shape([
            'title' => Property::defaults(new Html(), ''),
            'blocks' => Property::required(new Blocks()),
        ]));
    }

    /**
     * The stat block of a creature, a spell or an item, in no game system: `{"kind", "name", "subtitle", "frame",
     * "wide", "sections": [{"title", "properties": [{"label", "value"}], "abilities": [{"label", "score",
     * "modifier"}], "blocks": [...]}], "record"}`, `record` naming the game data it is bound to.
     */
    public static function statblock(): BlockType
    {
        return new BlockType('statblock', new Shape([
            'kind' => Property::defaults(new Choice(['monster', 'spell', 'item', 'other']), 'monster'),
            'name' => Property::required(new Plain(200)),
            'subtitle' => Property::defaults(new Html(), ''),
            'frame' => Property::defaults(new Boolean(), false),
            'wide' => Property::defaults(new Boolean(), false),
            'sections' => Property::defaults(new ListOf(new Shape([
                'title' => Property::optional(new Plain(200)),
                'properties' => Property::optional(new ListOf(new Shape([
                    'label' => Property::required(new Plain(200)),
                    'value' => Property::required(new Html()),
                ]))),
                'abilities' => Property::optional(new ListOf(new Shape([
                    'label' => Property::required(new Plain(16)),
                    'score' => Property::required(new Integer()),
                    'modifier' => Property::optional(new Plain(16)),
                ]))),
                'blocks' => Property::optional(new Blocks()),
            ])), []),
            'record' => Property::optional(new Plain(200))->nullable(),
        ]));
    }

    /**
     * `{"items": [{"term": html, "definitions": [html, ...]}, ...]}`, at least one item of at least one definition.
     */
    public static function definitionList(): BlockType
    {
        return new BlockType('definitionList', new Shape([
            'items' => Property::required(new ListOf(new Shape([
                'term' => Property::required(new Html()),
                'definitions' => Property::required(new ListOf(new Html(), 1)),
            ]), 1)),
        ]));
    }

    /**
     * `{"title": text, "text": html}`, the credit of a work of art.
     */
    public static function credit(): BlockType
    {
        return new BlockType('credit', new Shape([
            'title' => Property::defaults(new Plain(200), ''),
            'text' => Property::required(new Html()),
        ]));
    }

    /**
     * `{"text": text}`, a cover's banner.
     */
    public static function banner(): BlockType
    {
        return new BlockType('banner', new Shape([
            'text' => Property::required(new Plain(100)),
        ]));
    }

    /**
     * `{"kind": "stain" | "ornament", "variant": 1 to 99, "color": "#rrggbb"}`, artwork the look draws.
     */
    public static function decoration(): BlockType
    {
        return new BlockType('decoration', new Shape([
            'kind' => Property::defaults(new Choice(['stain', 'ornament']), 'stain'),
            'variant' => Property::defaults(new Integer(1, 99), 1),
            'color' => Property::optional(Plain::color()),
        ]));
    }

    /**
     * `{"data": text, "rendered": stored markup}`, the content of a QR code, drawn without calling any service.
     */
    public static function qrCode(): BlockType
    {
        return new BlockType('qrCode', new Shape([
            'data' => Property::required(new Plain(2048, '/./su')),
            'rendered' => StoredMarkup::property(),
        ]));
    }

    /**
     * An entry of an index, holding its sub-entries.
     */
    private static function indexEntry(): Shape
    {
        return new Shape([
            'term' => Property::required(new Plain(200)),
            'pages' => Property::defaults(new Plain(200), ''),
            'children' => Property::defaults(new ListOf(new Lazy(self::indexEntry(...))), []),
        ]);
    }
}
