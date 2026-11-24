<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Phil\CommerceConsignmentBundle\Domain\Service\StandardMargins;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardMargins::class)]
final class StandardMarginsTest extends TestCase
{
    public function testTheShopsUnitsGiveThePriceLessTheirCost(): void
    {
        self::assertSame([6000, 3600, 2400], $this->amounts(new StandardMargins()->owned(Fixtures::cad(2000), 3, Fixtures::cad(1200))));
        self::assertSame([1000, 1200, -200], $this->amounts(new StandardMargins()->owned(Fixtures::cad(1000), 1, Fixtures::cad(1200))));
    }

    public function testAShareIsOwedWhateverThePrice(): void
    {
        self::assertSame([4000, 3000, 1000], $this->amounts(new StandardMargins()->consigned(Fixtures::cad(2000), 2, ConsignmentTerms::share(Fixtures::cad(1500)))));
        self::assertSame([1000, 1500, -500], $this->amounts(new StandardMargins()->consigned(Fixtures::cad(1000), 1, ConsignmentTerms::share(Fixtures::cad(1500)))));
    }

    /**
     * @return iterable<string, array{int, int, int, int, int}>
     */
    public static function commissions(): iterable
    {
        yield '30 % of 19.99' => [1999, 1, 300_000, 1399, 600];
        yield '30 % of three at 19.99' => [1999, 3, 300_000, 4198, 1799];
        yield 'nothing kept' => [1999, 1, 0, 1999, 0];
        yield 'everything kept' => [1999, 1, 1_000_000, 0, 1999];
        yield '1 ppm of a cent' => [1, 1, 1, 1, 0];
        yield '12.5 % of 0.10' => [10, 1, 125_000, 9, 1];
    }

    #[DataProvider('commissions')]
    public function testACommissionSplitsThePriceWithoutLosingACent(int $price, int $quantity, int $ppm, int $share, int $commission): void
    {
        self::assertSame([$price * $quantity, $share, $commission], $this->amounts(new StandardMargins()->consigned(Fixtures::cad($price), $quantity, ConsignmentTerms::commission($ppm))));
    }

    /**
     * @return array{int, int, int}
     */
    private function amounts(Margin $margin): array
    {
        return [$margin->revenue->minorUnits, $margin->cost->minorUnits, $margin->margin->minorUnits];
    }
}
