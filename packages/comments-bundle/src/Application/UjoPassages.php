<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Phil\CommentsBundle\Domain\Anchoring\InlineHtml;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The texts of a UJO document where passages are anchored, and a passage replaced in it. A text is a field of a
 * block's data whose name — or, in a list, the name of the list — says it holds words: `text`, `caption`, `title`,
 * `message`, `content`, `description`, `name`, `term`, `subtitle`, and the source of code and formulas. Its path joins
 * the keys from the block's data, `items.0.content` for a list item, `content.1.2` for a table cell. Fields of inline
 * HTML are read as a browser reads their text; the source of `code`, `mermaid` and `math` blocks is plain text.
 */
final class UjoPassages
{
    private const array WORDS = ['text', 'caption', 'title', 'message', 'content', 'description', 'name', 'term', 'subtitle', 'code', 'latex'];

    private const array PLAIN = ['code' => 'code', 'mermaid' => 'code', 'math' => 'latex'];

    private const string KEY = '/^[A-Za-z0-9_]{1,64}$/D';

    private const int DEPTH = 16;

    /**
     * Every text of the document, block by block in reading order, nested blocks after the block that holds them.
     *
     * @return list<Passage>
     */
    public static function of(Document $document): array
    {
        $passages = [];

        foreach ($document->walk() as $block) {
            foreach (self::fields($block->data, [], null) as $path => $value) {
                $passages[] = new Passage($block->id, $path, self::isPlain($block, $path) ? $value : InlineHtml::text($value));
            }
        }

        return $passages;
    }

    /**
     * The document with the passage replaced by a text, the marks around it kept.
     *
     * @throws InvalidComment when the anchor is not in a block, or the passage is no longer at its place
     */
    public static function apply(Document $document, Anchor $anchor, string $replacement): Document
    {
        $block = null === $anchor->block ? null : $document->find($anchor->block);
        $path = explode('.', $anchor->field ?? '');
        $value = $block instanceof Block ? self::at($block->data, $path) : null;

        if (!$block instanceof Block || !\is_string($value)) {
            throw InvalidComment::because('The passage is not in a text of the document.');
        }

        $plain = self::isPlain($block, $anchor->field ?? '');
        $length = $anchor->end - $anchor->start;

        if (mb_substr($plain ? $value : InlineHtml::text($value), $anchor->start, $length) !== $anchor->quote) {
            throw InvalidComment::because('The passage is no longer at its place in the document.');
        }

        $text = $plain
            ? mb_substr($value, 0, $anchor->start).$replacement.mb_substr($value, $anchor->end)
            : InlineHtml::replace($value, $anchor->start, $length, $replacement);
        $changed = $block->withData(self::with($block->data, $path, $text));

        return $document->withBlocks(array_map(static fn (Block $each): Block => self::swap($each, $changed), $document->blocks));
    }

    /**
     * The texts of a block's data, by path.
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $path
     *
     * @return iterable<string, string>
     */
    private static function fields(array $data, array $path, ?string $name): iterable
    {
        foreach ($data as $key => $value) {
            $named = \is_int($key) ? $name : $key;
            $key = (string) $key;
            $at = [...$path, $key];

            if (1 !== preg_match(self::KEY, $key)) {
                continue;
            }

            if (\is_string($value) && \in_array($named, self::WORDS, true)) {
                yield implode('.', $at) => $value;
            } elseif (\is_array($value) && \count($at) < self::DEPTH) {
                yield from self::fields($value, $at, $named);
            }
        }
    }

    private static function isPlain(Block $block, string $path): bool
    {
        return (self::PLAIN[$block->type] ?? null) === $path;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $path
     */
    private static function at(array $data, array $path): mixed
    {
        $value = $data;

        foreach ($path as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $path
     *
     * @return array<array-key, mixed>
     */
    private static function with(array $data, array $path, string $text): array
    {
        $patch = [array_pop($path) => $text];

        foreach (array_reverse($path) as $key) {
            $patch = [$key => $patch];
        }

        return array_replace_recursive($data, $patch);
    }

    private static function swap(Block $block, Block $changed): Block
    {
        return $block->id === $changed->id ? $changed : $block->withData(self::swapIn($block->data, $changed));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function swapIn(array $data, Block $changed): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            $value instanceof Block => self::swap($value, $changed),
            \is_array($value) => self::swapIn($value, $changed),
            default => $value,
        }, $data);
    }
}
