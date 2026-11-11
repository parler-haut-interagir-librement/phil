<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Curly;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Ujo\Block;

/**
 * The BBCode extensions for printed pages and game books, made into the UJO types the `{{ }}` blocks of the
 * Markdown reader make: breaks and page styles, tables of contents, spacers, page numbers, decorations, running
 * footers, watermarks, banners, columns, spoilers, and the boxes of `Curly` — notes, read-aloud text, warnings,
 * alerts, blocks across the columns, stat blocks, credits, class tables and any class of the look.
 *
 * @internal
 */
final readonly class Pages
{
    /** The depth below which a container of blocks may still hold, inside it, a block wrapped by its classes. */
    private const int MAX_NESTING = 7;

    private const array ALERTS = ['info', 'success', 'danger', 'primary', 'secondary', 'light', 'dark'];

    /** The kinds of `Curly` each tag is, and whether its option is a title. */
    private const array KINDS = [
        'note' => ['note', true], 'descriptive' => ['descriptive', true], 'warning' => ['warning', true],
        'credit' => ['artist', true], 'artist' => ['artist', true], 'wide' => ['wide', false],
        'monster' => ['monster', false], 'classtable' => ['classTable', false],
    ];

    public function __construct(
        private Builder $builder,
        private Context $context,
        private InlineParser $inline,
    ) {
    }

    /**
     * The blocks of a tag of pages; `null` for any other tag.
     *
     * @return list<Block>|null
     */
    public function block(Node $node, int $depth): ?array
    {
        return match ($node->name) {
            'pagebreak' => [$this->context->block('pageBreak', []), ...$this->pageStyle($node)],
            'columnbreak' => [$this->context->block('columnBreak', [])],
            'toc' => [$this->context->block('toc', ['title' => mb_substr($node->option ?? '', 0, 200), 'depth' => $this->number($node->attribute('depth'), 1, 6, 3), 'wide' => \in_array('wide', $node->flags, true)])],
            'spacer' => [$this->context->block('spacer', ['lines' => $this->number($node->option, 1, 50, 1)])],
            'pagenumber' => [$this->pageNumber($node)],
            'decoration' => [$this->decoration($node)],
            'footer' => [$this->context->block('runningFooter', ['text' => $this->builder->flat($node)])],
            'watermark', 'banner' => [$this->context->block($node->name, ['text' => $this->inline->cut($this->builder->flat($node), 100)])],
            'columns' => $this->columns($node, $depth),
            'spoiler' => $this->toggle($node, $depth),
            'alert' => $this->curly($node, $depth, [\in_array($node->option, self::ALERTS, true) ? $node->option ?? '' : 'info']),
            'block' => $this->curly($node, $depth, Builder::words($node->option ?? '')),
            'statblock' => $this->curly($node, $depth, ['statblock', ...Builder::words($node->option ?? '')]),
            'note', 'descriptive', 'warning', 'credit', 'artist', 'wide', 'monster', 'classtable' => $this->curly($node, $depth, [self::KINDS[$node->name][0]], self::KINDS[$node->name][1]),
            default => null,
        };
    }

    /**
     * The depth the blocks a tag holds are read at, when it is a container; `null` when it would nest them deeper than
     * UJO allows, or wrap itself in its classes too deep. Its classes, and spanning the columns, may each wrap it in a
     * block more.
     */
    public static function deeper(Node $node, int $depth): ?int
    {
        $wide = \in_array('wide', [...$node->flags, ...Builder::words($node->option ?? '')], true);
        $inner = $depth + 1 + ('' === $node->attribute('class') ? 0 : 1) + ($wide ? 1 : 0);

        return $inner > self::MAX_NESTING ? null : $inner;
    }

    /**
     * @return list<Block>
     */
    private function pageStyle(Node $node): array
    {
        $classes = [...Builder::words($node->option ?? ''), ...$node->flags];

        return [] === $classes ? [] : [Curly::pageStyle(new Attributes('', $classes), $this->context)];
    }

    private function pageNumber(Node $node): Block
    {
        $option = $node->option ?? '';

        return $this->context->block('pageNumber', match ($option) {
            '', 'auto' => [],
            'skip', 'reset' => ['mode' => $option],
            default => ['mode' => 'fixed', 'value' => mb_substr($option, 0, 16)],
        });
    }

    private function decoration(Node $node): Block
    {
        $color = $node->attribute('color');
        $hex = 7 === \strlen($color) && str_starts_with($color, '#') && 6 === strspn($color, '0123456789abcdefABCDEF', 1);

        return $this->context->block('decoration', [
            'kind' => 'ornament' === $node->attribute('kind') ? 'ornament' : 'stain',
            'variant' => $this->number($node->option, 1, 99, 1),
            ...$hex ? ['color' => $color] : [],
        ]);
    }

    /**
     * Columns side by side, `[columns][column]…[column]…[/columns]`, six at most, the blocks of the others joined to
     * the sixth; or, without `[column]`, blocks flowing in columns, `[columns=3]`.
     *
     * @return list<Block>
     */
    private function columns(Node $node, int $depth): array
    {
        $inner = self::deeper($node, $depth);

        if (null === $inner) {
            return $this->builder->blocks($node, $depth);
        }

        $groups = $this->builder->groups($node, 'column');

        if (!\in_array('column', array_map(static fn (Node $group): string => '' === $group->written ? '' : $group->name, $groups), true)) {
            return [$this->context->block('columnFlow', ['count' => $this->number($node->option, 2, 6, 2), 'blocks' => $this->builder->blocks($node, $inner)])];
        }

        $columns = array_map(fn (Node $group): array => $this->builder->blocks($group, $inner), $groups);
        $columns = array_values(array_filter([...\array_slice($columns, 0, 5), array_merge(...\array_slice($columns, 5))], static fn (array $blocks): bool => [] !== $blocks));

        return [] === $columns ? [] : [$this->context->block('columns', ['cols' => array_map(static fn (array $blocks): array => ['blocks' => $blocks], $columns)])];
    }

    /**
     * A spoiler that has a title or holds blocks: a toggle, closed.
     *
     * @return list<Block>
     */
    private function toggle(Node $node, int $depth): array
    {
        $inner = self::deeper($node, $depth);

        if (null === $inner) {
            return $this->builder->blocks($node, $depth);
        }

        return [$this->context->block('toggle', ['text' => Inline::html(Builder::escape($node->option ?? '')), 'blocks' => $this->builder->blocks($node, $inner)])];
    }

    /**
     * A box of `Curly`, its kind and the other classes from the tag, its flags among them; a title in the option
     * becomes the heading the box takes its title from.
     *
     * @param list<string> $classes
     *
     * @return list<Block>
     */
    private function curly(Node $node, int $depth, array $classes, bool $titled = false): array
    {
        $classes = [...$classes, ...$node->flags];
        $inner = self::deeper($node, $depth);

        if (null === $inner) {
            return $this->builder->blocks($node, $depth);
        }

        $title = $titled && null !== $node->option ? [$this->context->block('header', ['text' => Inline::html(Builder::escape($node->option))])] : [];
        $made = Curly::block(new Attributes('', $classes), [...$title, ...$this->builder->blocks($node, $inner)], $this->context, $this->inline);
        $name = mb_substr($node->attribute('name'), 0, 200);
        $subtitle = $node->attribute('subtitle');
        $given = [...'' === $name ? [] : ['name' => $name], ...'' === $subtitle ? [] : ['subtitle' => Inline::html(Builder::escape($subtitle))]];

        if ([] === $given || 'statblock' !== ($made[0]->type ?? '')) {
            return $made;
        }

        return [$made[0]->withData([...$made[0]->data, ...$given])];
    }

    private function number(?string $value, int $minimum, int $maximum, int $default): int
    {
        $number = filter_var($value, \FILTER_VALIDATE_INT);

        return false === $number ? $default : max($minimum, min($maximum, $number));
    }
}
