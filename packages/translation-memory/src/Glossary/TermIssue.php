<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

/**
 * One issue of a target against the glossary: its kind, the term, how many times the source holds the term and the
 * target its translations, and the refused words found.
 */
final readonly class TermIssue
{
    public function __construct(
        public TermIssueKind $kind,
        public Term $term,
        public int $expected = 0,
        public int $found = 0,
        public ?string $forbidden = null,
    ) {
    }
}
