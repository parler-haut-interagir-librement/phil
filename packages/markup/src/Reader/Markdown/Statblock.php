<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Cursor;
use Phil\Ujo\Block;

/**
 * A stat block written as the game books write a creature, `{{monster,frame …}}`, as the UJO `statblock`: the first
 * heading is its name, an italic line right after it its subtitle; a rule, `___`, or a heading starts a section,
 * the heading its title; definitions are its properties, a table of scores — `10 (+0)` under each label — its
 * abilities, and every other block stays a block of the section.
 *
 * @internal
 */
final class Statblock
{
    /**
     * @param list<Block> $blocks
     */
    public static function read(Attributes $attributes, array $blocks, Context $context, InlineParser $inline): Block
    {
        $name = '';
        $subtitle = Inline::html('');
        $sections = [];
        $section = [];

        foreach ($blocks as $index => $block) {
            $text = Inline::of($block->data['text'] ?? null);
            $source = $text->source();
            $mark = substr($source, 0, 1);
            $italic = \in_array($mark, ['*', '_'], true) && str_ends_with($source, $mark) && !str_starts_with($source, $mark.$mark) && \strlen($source) > 1;

            if (0 === $index && 'header' === $block->type) {
                $name = $inline->cut($text, 200);
            } elseif (1 === $index && '' !== $name && 'paragraph' === $block->type && $italic) {
                $subtitle = Inline::markdown(substr($source, 1, -1));
            } elseif ('delimiter' === $block->type || 'header' === $block->type) {
                $sections = [...$sections, ...[] === $section ? [] : [$section]];
                $section = 'header' === $block->type ? ['title' => $inline->cut($text, 200)] : [];
            } else {
                $section = self::add($section, $block, $inline);
            }
        }

        return $context->block('statblock', [
            'kind' => array_first(array_intersect($attributes->classes, ['spell', 'item'])) ?? 'monster',
            'name' => $name,
            'subtitle' => $subtitle,
            'frame' => $attributes->has('frame'),
            'wide' => $attributes->has('wide'),
            'sections' => [...$sections, ...[] === $section ? [] : [$section]],
        ]);
    }

    /**
     * A block added to a section: definitions to its properties, a table of scores to its abilities, any other to its
     * blocks.
     *
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private static function add(array $section, Block $block, InlineParser $inline): array
    {
        $abilities = 'table' === $block->type ? self::abilities($block, $inline) : null;

        return match (true) {
            'definitionList' === $block->type => [...$section, 'properties' => [...self::list($section['properties'] ?? null), ...self::properties($block, $inline)]],
            null !== $abilities => [...$section, 'abilities' => $abilities],
            default => [...$section, 'blocks' => [...self::list($section['blocks'] ?? null), $block]],
        };
    }

    /**
     * @return list<array{label: string, value: Inline}>
     */
    private static function properties(Block $block, InlineParser $inline): array
    {
        $properties = [];

        foreach (BlockParser::items($block) as $item) {
            $item = \is_array($item) ? $item : [];
            $label = rtrim($inline->plain(Inline::of($item['term'] ?? null)), ':');
            $properties[] = ['label' => mb_substr($label, 0, 200), 'value' => Inline::join(array_map(Inline::of(...), self::list($item['definitions'] ?? null)), '<br>')];
        }

        return $properties;
    }

    /**
     * The abilities of a table of two rows, labels above scores, each score a number and, in parentheses, its
     * modifier; `null` when the table is anything else.
     *
     * @return list<array{label: string, score: int, modifier?: string}>|null
     */
    private static function abilities(Block $block, InlineParser $inline): ?array
    {
        $rows = self::list($block->data['content'] ?? null);

        if (2 !== \count($rows)) {
            return null;
        }

        $labels = self::list($rows[0]);
        $abilities = [];

        foreach (self::list($rows[1]) as $index => $cell) {
            $cursor = new Cursor($inline->plain(Inline::of($cell)));
            $score = $cursor->consumeWhile('+-0123456789');
            $rest = trim($cursor->rest());
            $modifier = str_starts_with($rest, '(') && str_ends_with($rest, ')') ? trim(substr($rest, 1, -1)) : null;

            if (!is_numeric($score) || ('' !== $rest && null === $modifier)) {
                return null;
            }

            $ability = ['label' => $inline->cut(Inline::of($labels[$index] ?? null), 16), 'score' => (int) $score];
            $abilities[] = null === $modifier ? $ability : [...$ability, 'modifier' => mb_substr($modifier, 0, 16)];
        }

        return $abilities;
    }

    /**
     * @return array<mixed>
     */
    private static function list(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
