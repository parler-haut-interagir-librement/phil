<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;

/**
 * One publication of a locale, kept in its archive: the version it was published from, and when, in UTC.
 */
final readonly class ArchivedVersion
{
    public function __construct(
        public int $version,
        public DateTimeImmutable $publishedAt,
    ) {
    }
}
