<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Repository\PriceBookRepository;

final class InMemoryPriceBooks implements PriceBookRepository
{
    /** @var array<string, PriceBook> */
    private array $books = [];

    /** How many times the active books were read. */
    public int $reads = 0;

    public function __construct(PriceBook ...$books)
    {
        foreach ($books as $book) {
            $this->add($book);
        }
    }

    #[Override]
    public function find(string $code): ?PriceBook
    {
        return $this->books[$code] ?? null;
    }

    #[Override]
    public function active(): array
    {
        ++$this->reads;
        $books = array_values(array_filter($this->books, static fn (PriceBook $book): bool => $book->isActive()));
        usort($books, static fn (PriceBook $left, PriceBook $right): int => strcmp($left->code, $right->code));

        return $books;
    }

    #[Override]
    public function add(PriceBook $book): void
    {
        $this->books[$book->code] = $book;
    }
}
