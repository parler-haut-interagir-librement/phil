<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * Normalized documents for the tests, from short descriptions of their blocks.
 */
final class DocumentFactory
{
    /**
     * @param list<array{id: string, type: string, data: array<string, mixed>}> $blocks
     */
    public static function document(array $blocks): Document
    {
        return new Normalizer()->normalize(Document::fromArray(['blocks' => $blocks]));
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    public static function paragraph(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'paragraph', 'data' => ['text' => $text]];
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    public static function note(string $blockId, string $noteId, string $text): array
    {
        return ['id' => $blockId, 'type' => 'footnote', 'data' => ['id' => $noteId, 'text' => $text]];
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    public static function heading(string $id, int $level, string $text = 'Title'): array
    {
        return ['id' => $id, 'type' => 'header', 'data' => ['text' => $text, 'level' => $level]];
    }

    public static function reference(string $id, string $content = '1'): string
    {
        return \sprintf('<sup data-footnote="%s">%s</sup>', $id, $content);
    }
}
