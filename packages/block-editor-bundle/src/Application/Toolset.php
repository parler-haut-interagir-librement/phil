<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application;

use Phil\BlockEditorBundle\Application\Exception\InvalidToolset;

/**
 * The tools an editor offers: the block types it creates, named as `phil/ujo` names them, and the inline tools of its
 * formatting toolbar, named as the `rich` profile of `phil/ujo` names them. A document submitted through the editor
 * holds nothing else: other block types are refused, other inline elements unwrapped.
 *
 * The `full` preset has every tool; the `reduced` one, for simple sites, has paragraphs, headings, lists and quotes,
 * with bold, italic, links and line breaks.
 */
final readonly class Toolset
{
    /** Every block type the editor creates, in the order its slash menu lists them. */
    public const array BLOCK_TOOLS = ['paragraph', 'header', 'list', 'quote', 'code', 'alert', 'toggle', 'columns', 'table', 'image', 'delimiter'];

    /** Every inline tool of the formatting toolbar and the keyboard, in the order the toolbar shows them. */
    public const array INLINE_TOOLS = ['bold', 'italic', 'underline', 'strikethrough', 'inlineCode', 'link', 'marker', 'textColor', 'superscript', 'subscript', 'mention', 'wikiLink', 'lineBreak'];

    public const array REDUCED_BLOCK_TOOLS = ['paragraph', 'header', 'list', 'quote'];

    public const array REDUCED_INLINE_TOOLS = ['bold', 'italic', 'link', 'lineBreak'];

    /** @var list<string> */
    public array $blockTools;

    /** @var list<string> */
    public array $inlineTools;

    /**
     * @param list<string> $blockTools  block types, kept in the editor's order; `paragraph` is required
     * @param list<string> $inlineTools inline tools, kept in the toolbar's order
     *
     * @throws InvalidToolset
     */
    public function __construct(array $blockTools = self::BLOCK_TOOLS, array $inlineTools = self::INLINE_TOOLS)
    {
        $this->blockTools = $this->known('block', $blockTools, self::BLOCK_TOOLS);
        $this->inlineTools = $this->known('inline', $inlineTools, self::INLINE_TOOLS);

        if (!\in_array('paragraph', $this->blockTools, true)) {
            throw InvalidToolset::withoutParagraph();
        }
    }

    public static function full(): self
    {
        return new self();
    }

    public static function reduced(): self
    {
        return new self(self::REDUCED_BLOCK_TOOLS, self::REDUCED_INLINE_TOOLS);
    }

    /**
     * A preset, `full` or `reduced`, its block or inline tools replaced when given.
     *
     * @param ?list<string> $blockTools
     * @param ?list<string> $inlineTools
     *
     * @throws InvalidToolset
     */
    public static function preset(string $preset, ?array $blockTools = null, ?array $inlineTools = null): self
    {
        $base = match ($preset) {
            'full' => self::full(),
            'reduced' => self::reduced(),
            default => throw InvalidToolset::unknownPreset($preset),
        };

        return new self($blockTools ?? $base->blockTools, $inlineTools ?? $base->inlineTools);
    }

    public function hasBlock(string $type): bool
    {
        return \in_array($type, $this->blockTools, true);
    }

    public function hasInline(string $tool): bool
    {
        return \in_array($tool, $this->inlineTools, true);
    }

    /**
     * A key naming the toolset, the same for two toolsets of the same tools.
     */
    public function key(): string
    {
        return implode(',', $this->blockTools).'|'.implode(',', $this->inlineTools);
    }

    /**
     * @param list<string> $tools
     * @param list<string> $known
     *
     * @return list<string> the tools, in the order of the known ones
     *
     * @throws InvalidToolset
     */
    private function known(string $kind, array $tools, array $known): array
    {
        $unknown = array_diff($tools, $known);

        if ([] !== $unknown) {
            throw InvalidToolset::unknownTools($kind, $unknown, $known);
        }

        return array_values(array_intersect($known, $tools));
    }
}
