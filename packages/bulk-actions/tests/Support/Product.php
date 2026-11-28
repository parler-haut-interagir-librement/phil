<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

/**
 * An item of the tests, as an ORM entity would be: changed in memory, written by a flush.
 */
final class Product
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $status = 'draft',
        public ?int $price = null,
        public int $version = 1,
    ) {
    }
}
