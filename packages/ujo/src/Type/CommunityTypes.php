<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;

/**
 * The community tools of Editor.js that are read and written: alerts, toggles, columns, formulas, diagrams, charts,
 * buttons and galleries. The forms that save children flat after the block — the toggle block's `items`, the
 * collapsible block's `blockCount` — are folded into a toggle; the calumk columns, the LaTeX block, the button and the
 * carousel are read under their own names.
 */
final class CommunityTypes
{
    /** The kinds of an alert. */
    public const array ALERTS = ['info', 'primary', 'secondary', 'success', 'warning', 'danger', 'light', 'dark'];

    /**
     * @return list<BlockType>
     */
    public static function all(): array
    {
        return [
            self::alert(),
            self::toggle(),
            self::columns(),
            self::math(),
            self::mermaid(),
            self::chart(),
            self::button(),
            self::gallery(),
        ];
    }

    /**
     * `{"type": kind, "align": "left" | "center" | "right", "text": html}`; the `message` of the alert tool's README
     * is read as `text`.
     */
    public static function alert(): BlockType
    {
        return new BlockType('alert', new Shape([
            'type' => Property::defaults(new Choice(self::ALERTS), 'info'),
            'align' => Property::defaults(new Choice(['left', 'center', 'right']), 'left'),
            'text' => Property::required(new Html()),
        ]), upgrade: static fn (Block $block): Block => $block->withData(self::rename($block->data, 'message', 'text')));
    }

    /**
     * `{"text": html, "open": bool, "blocks": [...]}`, its children nested. Also read: the toggle block,
     * `{"text", "status": "open" | "closed", "fk", "items": n}`, and the collapsible block, `accordion`,
     * `{"title", "settings": {"blockCount": n, "defaultExpanded": bool}}`, whose n children follow them.
     */
    public static function toggle(): BlockType
    {
        return new BlockType('toggle', new Shape([
            'text' => Property::required(new Html()),
            'open' => Property::defaults(new Boolean(), false),
            'blocks' => Property::defaults(new Blocks(), []),
        ]), ['accordion'], static function (array $data): int {
            $settings = \is_array($data['settings'] ?? null) ? $data['settings'] : [];
            $count = $data['items'] ?? $settings['blockCount'] ?? null;

            return \is_int($count) && !isset($data['blocks']) ? $count : 0;
        }, static function (Block $block): Block {
            $data = $block->data;
            $settings = \is_array($data['settings'] ?? null) ? $data['settings'] : [];

            if (\array_key_exists('status', $data)) {
                $data['open'] = 'open' === $data['status'];
            }

            if (\array_key_exists('defaultExpanded', $settings)) {
                $data['open'] = $settings['defaultExpanded'];
            }

            return $block->withData(array_diff_key(self::rename($data, 'title', 'text'), array_flip(['status', 'fk', 'items', 'settings'])));
        });
    }

    /**
     * `{"cols": [{"blocks": [...]}, ...]}`, one to six columns side by side; a column saved as a whole document by the
     * calumk columns tool, `{"time", "blocks", "version"}`, keeps its blocks.
     */
    public static function columns(): BlockType
    {
        return new BlockType('columns', new Shape([
            'cols' => Property::required(new ListOf(new Shape([
                'blocks' => Property::required(new Blocks()),
            ]), 1, 6)),
        ]), upgrade: static function (Block $block): Block {
            $columns = $block->data['cols'] ?? null;

            return \is_array($columns) ? $block->withData([...$block->data, 'cols' => array_map(
                static fn (mixed $column): mixed => \is_array($column) ? array_diff_key($column, array_flip(['time', 'version'])) : $column,
                $columns,
            )]) : $block;
        });
    }

    /**
     * `{"latex": text, "mathml": html, "rendered": stored markup}`, a formula in LaTeX, in MathML Core — a `<math>`
     * element, kept as the `mathml` profile allows —, or both; the LaTeX block, saved as `Math` with `{"math": text}`,
     * is read as one.
     */
    public static function math(): BlockType
    {
        return new BlockType('math', new Shape([
            'latex' => Property::optional(new Plain(20_000)),
            'mathml' => Property::optional(new Html('mathml')),
            'rendered' => StoredMarkup::property(),
        ]), ['Math'], upgrade: static fn (Block $block): Block => $block->withData(self::rename($block->data, 'math', 'latex')), check: static function (array $data, string $path): void {
            $mathMl = \is_string($data['mathml'] ?? null) ? $data['mathml'] : '';

            if (!isset($data['latex']) && '' === $mathMl) {
                throw InvalidDocument::at($path.'.latex', 'a formula in LaTeX or in MathML is required');
            }

            if ('' !== $mathMl && (!str_starts_with($mathMl, '<math') || !str_ends_with($mathMl, '</math>'))) {
                throw InvalidDocument::at($path.'.mathml', 'one <math> element is expected');
            }
        });
    }

    /**
     * `{"code": text, "caption": html, "rendered": stored markup}`, a Mermaid diagram.
     */
    public static function mermaid(): BlockType
    {
        return new BlockType('mermaid', new Shape([
            'code' => Property::required(new Plain(20_000)),
            'caption' => Property::defaults(new Html(), ''),
            'rendered' => StoredMarkup::property(),
        ]));
    }

    /**
     * A Chart.js configuration, kept as it is, and its stored markup under `rendered`.
     */
    public static function chart(): BlockType
    {
        return new BlockType('chart', new Shape(['rendered' => StoredMarkup::property()], true));
    }

    /**
     * `{"link": url, "text": text}`; the button tool's `AnyButton` is read as one.
     */
    public static function button(): BlockType
    {
        return new BlockType('button', new Shape([
            'link' => Property::required(new Link()),
            'text' => Property::required(new Plain(200)),
        ]), ['AnyButton']);
    }

    /**
     * `{"files": [{"url", "caption"}, ...], "source": text, "style": "fit" | "slider"}`; the carousel, saved as a list
     * of `{"url", "caption"}`, is read as a slider.
     */
    public static function gallery(): BlockType
    {
        return new BlockType('gallery', new Shape([
            'files' => Property::required(new ListOf(new Shape([
                'url' => Property::required(new Link()),
                'caption' => Property::optional(new Html()),
            ], true), 1)),
            'source' => Property::optional(new Plain(500)),
            'style' => Property::defaults(new Choice(['fit', 'slider']), 'fit'),
        ]), ['carousel'], upgrade: static fn (Block $block): Block => array_is_list($block->data) && [] !== $block->data
            ? $block->withData(['files' => $block->data, 'style' => 'slider'])
            : $block);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function rename(array $data, string $from, string $to): array
    {
        if (!\array_key_exists($from, $data) || \array_key_exists($to, $data)) {
            return $data;
        }

        $renamed = [];

        foreach ($data as $key => $value) {
            $renamed[$from === $key ? $to : $key] = $value;
        }

        return $renamed;
    }
}
