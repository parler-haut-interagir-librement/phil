<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Domain\ValueObject;

/**
 * Something the validator noticed in a list of synonyms that is allowed but likely a mistake: the rule it is about, by
 * its position from 0, a code and a sentence.
 */
final readonly class Violation
{
    /** A phrase on the left of two rules: a search reads it as both, merged. */
    public const string OVERLAP = 'overlap';

    /** A phrase a one-way rule leads to is on the left of another rule, which a search does not follow. */
    public const string CHAIN = 'chain';

    public function __construct(
        public int $set,
        public string $code,
        public string $message,
    ) {
    }
}
