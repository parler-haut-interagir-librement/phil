<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Ujo\Block;
use Phil\Ujo\Html\SafeUrl;

/**
 * The second pass of a reading, once every definition is known: the inline content of each block read into HTML, a
 * paragraph split around the images it holds — each an `image` block —, an image inside other inline content made a
 * link, an anchor given to each heading without one, and the footnotes referenced added at the end, in the order of
 * their first reference.
 *
 * @internal
 */
final readonly class Resolver
{
    public function __construct(
        private Context $context,
        private InlineParser $inline,
    ) {
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    public function document(array $blocks): array
    {
        $blocks = $this->blocks($blocks);

        for ($done = 0; $done < \count($this->context->numbers);) {
            foreach (\array_slice($this->context->numbers, $done, null, true) as $label => $number) {
                $blocks[] = $this->context->block('footnote', ['id' => 'fn-'.$number, 'text' => $this->html($this->context->footnotes[$label] ?? Inline::html(''))]);
                ++$done;
            }
        }

        return $blocks;
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private function blocks(array $blocks): array
    {
        $resolved = [];

        foreach ($blocks as $block) {
            foreach ('paragraph' === $block->type ? $this->paragraph($block) : [$this->block($block)] as $made) {
                $resolved[] = $made;
            }
        }

        return $resolved;
    }

    private function block(Block $block): Block
    {
        $data = array_map($this->value(...), $block->data);
        $tunes = $block->tunes;

        if ('header' === $block->type && !isset($tunes['anchor'])) {
            $tunes['anchor'] = ['anchor' => $this->context->anchors->slug(InlineParser::text(\is_string($data['text'] ?? null) ? $data['text'] : ''))];
        }

        return new Block($block->id, $block->type, $data, $tunes);
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Inline => $this->html($value),
            \is_array($value) && ($value[0] ?? null) instanceof Block => $this->blocks(array_values(array_filter($value, static fn (mixed $block): bool => $block instanceof Block))),
            \is_array($value) => array_map($this->value(...), $value),
            default => $value,
        };
    }

    /**
     * @return list<Block>
     */
    private function paragraph(Block $block): array
    {
        $pieces = explode("\0", $this->inline->html(Inline::of($block->data['text'] ?? null)));

        if (1 === \count($pieces)) {
            return '' === trim($pieces[0]) ? [] : [$block->withData(['text' => $pieces[0]])];
        }

        $blocks = [];

        foreach ($pieces as $index => $piece) {
            $made = match (true) {
                1 === $index % 2 => $this->image($this->context->images[$piece]),
                '' === trim($piece) => null,
                default => $this->context->block('paragraph', ['text' => trim($piece)], $block->tunes),
            };

            if ($made instanceof Block) {
                $blocks[] = $made;
            }
        }

        return $blocks;
    }

    /**
     * An image block: its caption, and its alternative text when it has one; an image whose address is not safe is a
     * paragraph of its text, its alternative text or else its caption.
     *
     * @param array{string, string, string, string} $image
     */
    private function image(array $image): ?Block
    {
        [$address, $alt, $attributes, $caption] = $image;

        if (!SafeUrl::isSafe($address, SafeUrl::MEDIA)) {
            $text = InlineParser::imageText($image);

            return '' === $text ? null : $this->context->block('paragraph', ['text' => InlineParser::escape($text)]);
        }

        $data = ['file' => ['url' => $address], 'caption' => InlineParser::escape($caption), ...'' === $alt ? [] : ['alt' => $alt]];

        return Layout::apply($this->context->block('image', $data), Attributes::parse($attributes), $this->context);
    }

    private function html(Inline $inline): string
    {
        return $this->inline->images($this->inline->html($inline), static fn (array $image): string => InlineParser::link($image[0], InlineParser::escape(InlineParser::imageText($image))));
    }
}
