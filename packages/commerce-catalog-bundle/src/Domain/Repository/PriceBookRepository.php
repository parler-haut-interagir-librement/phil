<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Repository;

use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;

/**
 * Finds and stores price books. `Infrastructure\Doctrine\DoctrinePriceBookRepository` is the Doctrine adapter.
 */
interface PriceBookRepository
{
    public function find(string $code): ?PriceBook;

    /**
     * @return list<PriceBook> the books not withdrawn, by code; `Application\PriceBookOrder` orders them as they are tried
     */
    public function active(): array;

    public function add(PriceBook $book): void;
}
