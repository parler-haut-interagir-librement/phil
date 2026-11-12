<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

/**
 * A term found in a text: the term, the words as written, and where, in characters of the plain text.
 */
final readonly class TermHit
{
    public function __construct(
        public Term $term,
        public string $text,
        public int $offset,
    ) {
    }
}
