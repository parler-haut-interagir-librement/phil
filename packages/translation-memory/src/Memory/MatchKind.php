<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

/**
 * How a unit of the memory matches a source: the same text, white space aside, or a close one.
 */
enum MatchKind: string
{
    case Exact = 'exact';
    case Fuzzy = 'fuzzy';
}
