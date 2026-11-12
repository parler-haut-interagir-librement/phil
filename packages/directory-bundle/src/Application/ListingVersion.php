<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use DateTimeImmutable;

/**
 * A version of a listing in a locale, for its history: its number, its name then, who wrote it, and when it was
 * published, null for a version never published.
 */
final readonly class ListingVersion
{
    public function __construct(
        public int $number,
        public ?string $title,
        public ?string $editedBy,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }
}
