<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Phil\CommerceCatalogBundle\Application\PriceBookOrder;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceBookOrder::class)]
final class PriceBookOrderTest extends TestCase
{
    public function testBooksWithoutListsComeByCodeInByteOrder(): void
    {
        self::assertSame(['10', '9', 'a', 'b'], $this->codes(PriceBookOrder::sort([$this->book('b'), $this->book('9'), $this->book('a'), $this->book('10')])));
        self::assertSame([], PriceBookOrder::sort([]));
    }

    /**
     * A book comes after the books its list names, which win over it; a name that is no book of the list is passed
     * over.
     */
    public function testABookComesAfterTheBooksItNames(): void
    {
        $sorted = PriceBookOrder::sort([
            $this->book('retail', ['wholesale', 'vip', 'gone']),
            $this->book('wholesale', ['vip']),
            $this->book('vip'),
            $this->book('apprentice', ['retail']),
        ]);

        self::assertSame(['vip', 'wholesale', 'retail', 'apprentice'], $this->codes($sorted));
    }

    public function testCodesOfDigitsAreOrderedAsTextAndKeepTheirLists(): void
    {
        $sorted = PriceBookOrder::sort([$this->book('10', ['9']), $this->book('9'), $this->book('2', ['10'])]);

        self::assertSame(['9', '10', '2'], $this->codes($sorted));
        self::assertContainsOnlyInstancesOf(PriceBook::class, $sorted);
    }

    public function testListsThatLoopLeaveTheBooksByCode(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->codes(PriceBookOrder::sort([$this->book('c'), $this->book('b', ['a']), $this->book('a', ['b'])])));
    }

    /**
     * @param list<string> $after
     */
    private function book(string $code, array $after = []): PriceBook
    {
        return PriceBook::create($code, $code, Currency::of('CAD'), after: $after);
    }

    /**
     * @param list<PriceBook> $books
     *
     * @return list<string>
     */
    private function codes(array $books): array
    {
        return array_map(static fn (PriceBook $book): string => $book->code, $books);
    }
}
