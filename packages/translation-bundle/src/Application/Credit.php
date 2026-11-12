<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

/**
 * A person thanked for the translation of a document: the name they are credited under, and how many of its segments
 * they translated and reviewed.
 */
final readonly class Credit
{
    public function __construct(
        public string $name,
        public int $translated = 0,
        public int $reviewed = 0,
    ) {
    }
}
