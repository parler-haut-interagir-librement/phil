<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Cursor;
use Phil\Ujo\Block;

/**
 * The blocks written `{{class …}}` and `::: class`, as the UJO types of the same role: boxed
 * notes and read-aloud text, stat blocks, a table of contents, quotes with their attribution, art credits, class
 * tables, spell lists, covers, blocks spanning the columns, alerts and warnings; and the spans alone on their line
 * that are blocks of a page: page numbers, running footers, watermarks, banners, decorations. A class UJO has no type
 * for becomes a `container`, for a look that declares it.
 */
final class Curly
{
    private const array COVERS = ['frontCover', 'insideCover', 'partCover', 'backCover'];

    private const array ALERTS = ['info', 'success', 'danger', 'primary', 'secondary', 'light', 'dark'];

    private const array KINDS = ['note', 'descriptive', 'warning', 'monster', 'statblock', 'toc', 'quote', 'artist', 'classTable', 'spellList', ...self::COVERS, ...self::ALERTS];

    /** The kinds that know what spanning the columns means for them. */
    private const array WIDE = ['monster', 'statblock', 'toc'];

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    public static function block(Attributes $attributes, array $blocks, Context $context, InlineParser $inline, string $title = ''): array
    {
        $kind = array_first(array_intersect($attributes->classes, self::KINDS));
        $wide = $attributes->has('wide');
        $title = '' === $title ? null : Inline::markdown($title);

        $made = match (true) {
            'note' === $kind, 'descriptive' === $kind => [self::box($kind, $blocks, $context, $title)],
            'warning' === $kind => [self::warning($blocks, $context, $title)],
            'monster' === $kind, 'statblock' === $kind => [Statblock::read($attributes, $blocks, $context, $inline)],
            'toc' === $kind => [self::toc($attributes, $blocks, $context, $inline)],
            'quote' === $kind => [self::quote($blocks, $context)],
            'artist' === $kind => [self::credit($blocks, $context, $inline)],
            'classTable' === $kind => self::variant($attributes, $blocks),
            'spellList' === $kind => [$context->block('columnFlow', ['blocks' => $blocks])],
            \in_array($kind, self::COVERS, true) => [$context->block('pageStyle', ['variant' => $kind]), ...$blocks],
            \in_array($kind, self::ALERTS, true) => [$context->block('alert', ['type' => $kind, 'text' => BlockParser::join([$title, ...$blocks], '<br>')])],
            $wide => [$context->block('wide', ['blocks' => $blocks])],
            default => self::container($attributes, $blocks, $context),
        };

        if ($wide && null !== $kind && !\in_array($kind, self::WIDE, true)) {
            $made = [$context->block('wide', ['blocks' => $made])];
        }

        return [] === $made ? [] : [Layout::apply($made[0], $attributes, $context, false), ...\array_slice($made, 1)];
    }

    /**
     * The text between `{{` and the `}}` that closes it on the same line, spans nested up to 8 deep included,
     * consumed; `null`, and nothing consumed, when no span starts at the cursor.
     */
    public static function scan(Cursor $cursor, int $depth = 0): ?string
    {
        $start = $cursor->position();

        if ($depth > 8 || !$cursor->consume('{{')) {
            return null;
        }

        while (!$cursor->consume('}}')) {
            $cursor->consumeUntil("{}\n");
            $nested = $cursor->comesNext('{{');

            if (str_contains("\n", $cursor->current()) || ($nested && null === self::scan($cursor, $depth + 1))) {
                $cursor->moveTo($start);

                return null;
            }

            if (!$nested && !$cursor->comesNext('}}')) {
                $cursor->consume('{' === $cursor->current() ? '{' : '}');
            }
        }

        return substr($cursor->text, $start + 2, $cursor->position() - $start - 4);
    }

    /**
     * The blocks a span alone on its line stands for; `null` when it is text.
     *
     * @return list<Block>|null
     */
    public static function span(Attributes $attributes, string $content, Context $context, InlineParser $inline): ?array
    {
        $kind = $attributes->classes[0] ?? '';
        $text = Inline::markdown($content);
        $plain = $inline->plain($text);
        $stain = (int) substr($kind, 10);

        $block = match (true) {
            'pageNumber' === $kind => $context->block('pageNumber', $attributes->has('auto') || '' === $plain || str_contains($content, '$[HB_pageNumber]') ? [] : ['mode' => 'fixed', 'value' => $inline->cut($text, 16)]),
            'skipCounting' === $kind => $context->block('pageNumber', ['mode' => 'skip']),
            'resetCounting' === $kind => $context->block('pageNumber', ['mode' => 'reset']),
            'footnote' === $kind => $context->block('runningFooter', ['text' => $text]),
            'watermark' === $kind, 'banner' === $kind => $context->block($kind, ['text' => $inline->cut($text, 100)]),
            \in_array($kind, self::COVERS, true) => $context->block('pageStyle', ['variant' => $kind]),
            str_starts_with($kind, 'watercolor') && $stain > 0 && $stain < 100 => $context->block('decoration', ['variant' => $stain, ...self::color($attributes)]),
            default => null,
        };

        return $block instanceof Block ? [Layout::apply($block, $attributes, $context, false)] : null;
    }

    /**
     * The style of the page a break opens: its cover, when a class names one, and its other classes.
     */
    public static function pageStyle(Attributes $attributes, Context $context): Block
    {
        $cover = array_first(array_intersect($attributes->classes, self::COVERS));
        $classes = array_values(array_diff(Layout::classes($attributes), self::COVERS));

        return $context->block('pageStyle', null === $cover ? ['classes' => $classes] : ['variant' => $cover, 'classes' => $classes]);
    }

    /**
     * The title of a box: the one given, or the heading it starts with, taken out of its blocks.
     *
     * @param list<Block> $blocks
     *
     * @return array{Inline, list<Block>}
     */
    private static function title(?Inline $title, array $blocks): array
    {
        if ($title instanceof Inline || 'header' !== ($blocks[0]->type ?? '')) {
            return [$title ?? Inline::html(''), $blocks];
        }

        return [Inline::of($blocks[0]->data['text'] ?? null), \array_slice($blocks, 1)];
    }

    /**
     * @param list<Block> $blocks
     */
    private static function box(string $type, array $blocks, Context $context, ?Inline $title): Block
    {
        [$title, $blocks] = self::title($title, $blocks);

        return $context->block($type, ['title' => $title, 'blocks' => $blocks]);
    }

    /**
     * @param list<Block> $blocks
     */
    private static function warning(array $blocks, Context $context, ?Inline $title): Block
    {
        [$title, $blocks] = self::title($title, $blocks);

        return $context->block('warning', ['title' => $title, 'message' => BlockParser::join($blocks, '<br>')]);
    }

    /**
     * @param list<Block> $blocks
     */
    private static function toc(Attributes $attributes, array $blocks, Context $context, InlineParser $inline): Block
    {
        [$title] = self::title(null, $blocks);
        $depth = 3;

        foreach ([4, 5, 6] as $level) {
            $depth = $attributes->has('tocDepthH'.$level) ? $level : $depth;
        }

        return $context->block('toc', ['title' => $inline->cut($title, 200), 'depth' => $depth, 'wide' => $attributes->has('wide')]);
    }

    /**
     * A quote, its attribution the line `{{attribution …}}` it holds.
     *
     * @param list<Block> $blocks
     */
    private static function quote(array $blocks, Context $context): Block
    {
        $caption = Inline::html('');
        $text = [];

        foreach ($blocks as $block) {
            $source = Inline::of($block->data['text'] ?? null)->source();

            if ('paragraph' !== $block->type || !str_contains($source, '{{attribution ')) {
                $text[] = $block;

                continue;
            }

            $lines = [];

            foreach (explode("\n", $source) as $line) {
                $attribution = str_starts_with($line, '{{attribution ') && str_ends_with($line, '}}');
                $caption = $attribution ? Inline::markdown(substr($line, 14, -2)) : $caption;
                $lines = $attribution ? $lines : [...$lines, $line];
            }

            $text[] = [] === $lines ? null : Inline::markdown(implode("\n", $lines));
        }

        return $context->block('quote', ['text' => BlockParser::join($text, '<br><br>'), 'caption' => $caption]);
    }

    /**
     * @param list<Block> $blocks
     */
    private static function credit(array $blocks, Context $context, InlineParser $inline): Block
    {
        [$title, $blocks] = self::title(null, $blocks);

        return $context->block('credit', ['title' => $inline->cut($title, 200), 'text' => BlockParser::join($blocks, '<br>')]);
    }

    /**
     * The tables of the blocks marked with a variant the look declares, with its frame and decoration.
     *
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private static function variant(Attributes $attributes, array $blocks): array
    {
        $variant = ['name' => 'classTable', 'frame' => $attributes->has('frame'), 'decoration' => $attributes->has('decoration')];

        return array_map(static fn (Block $block): Block => 'table' === $block->type ? $block->withTunes([...$block->tunes, 'variant' => $variant]) : $block, $blocks);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private static function container(Attributes $attributes, array $blocks, Context $context): array
    {
        $classes = Layout::classes($attributes);

        return [] === $classes ? $blocks : [$context->block('container', ['classes' => $classes, 'blocks' => $blocks])];
    }

    /**
     * @return array{color?: string}
     */
    private static function color(Attributes $attributes): array
    {
        $color = $attributes->styles['background-color'] ?? '';

        return 1 === preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? ['color' => $color] : [];
    }
}
