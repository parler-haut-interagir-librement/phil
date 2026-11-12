<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Override;
use Phil\TranslationBundle\Application\SourceDocument;
use Phil\TranslationBundle\Application\SourceDocuments;
use Phil\TranslationMemory\LanguageTag;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The documents of a test, by reference; `page:1` is an English page of two paragraphs by default.
 */
final class Sources implements SourceDocuments
{
    /**
     * @param array<string, SourceDocument> $documents
     */
    public function __construct(
        public array $documents = [],
    ) {
        $this->documents += ['page:1' => new SourceDocument(self::page('Hello world.', 'The <b>red</b> door.'), LanguageTag::of('en'), 'Welcome')];
    }

    #[Override]
    public function find(string $document): ?SourceDocument
    {
        return $this->documents[$document] ?? null;
    }

    public static function page(string ...$paragraphs): Document
    {
        $blocks = [];

        foreach (array_values($paragraphs) as $index => $text) {
            $blocks[] = new Block('p'.($index + 1), 'paragraph', ['text' => $text]);
        }

        return new Document($blocks);
    }
}
