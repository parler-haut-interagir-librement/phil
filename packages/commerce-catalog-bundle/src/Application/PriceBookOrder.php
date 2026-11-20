<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * The order price books are tried in: by code, then moved so that each one comes after the books its `after` list
 * names, by the sorter Symfony orders tagged services with. A name that is no book of the list is passed over. Lists
 * that loop — two books each after the other — are set aside, and the books are tried by code.
 */
final class PriceBookOrder
{
    /**
     * @param array<PriceBook> $books
     *
     * @return list<PriceBook>
     */
    public static function sort(array $books): array
    {
        $byCode = [];
        $constraints = [];

        foreach ($books as $book) {
            $byCode[$book->code] = $book;

            if ([] !== $book->after()) {
                $constraints[$book->code] = ['after' => $book->after()];
            }
        }

        ksort($byCode, \SORT_STRING);
        $seed = array_map(strval(...), array_keys($byCode));

        try {
            $sorted = BeforeAfterSorter::sort($seed, $constraints);
        } catch (InvalidArgumentException) {
            $sorted = $seed;
        }

        return array_map(static fn (string $code): PriceBook => $byCode[$code], $sorted);
    }
}
