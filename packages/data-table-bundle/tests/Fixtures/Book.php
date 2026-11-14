<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use DateTimeImmutable;

/**
 * A row of the tests' tables.
 */
final class Book
{
    public function __construct(
        public int $id,
        public string $title,
        public int $pages,
        public DateTimeImmutable $published,
        public Status $status,
        public bool $inPrint = true,
        public int $version = 1,
    ) {
    }
}
