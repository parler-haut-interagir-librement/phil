<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use DateTimeImmutable;

/**
 * The tests' books, with what the actions did to them.
 */
final class Shelf
{
    /** @var list<Book> */
    public array $books;

    /** @var list<int> */
    public array $archived = [];

    /** @param int $count how many books */
    public function __construct(int $count = 60)
    {
        $this->books = [];

        for ($number = 1; $number <= $count; ++$number) {
            $this->books[] = new Book(
                $number,
                \sprintf('Book %02d', $number),
                100 + $number,
                new DateTimeImmutable(\sprintf('2026-01-%02d 12:00:00 UTC', $number % 28 + 1)),
                0 === $number % 2 ? Status::Draft : Status::Live,
                0 !== $number % 3,
            );
        }
    }
}
