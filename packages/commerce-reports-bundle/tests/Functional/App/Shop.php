<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Functional\App;

use DateTimeImmutable;
use Override;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * A shop of the test application, which connects its ports to its own figures: two books and a game, sold in two
 * currencies.
 */
final readonly class Shop implements SoldLines, StockFigures, StockedSkus
{
    private InMemoryStockedSkus $skus;

    public function __construct()
    {
        $this->skus = new InMemoryStockedSkus([
            new StockedSku('BOOK-1', 'First book', 'books'),
            new StockedSku('BOOK-2', 'Second book', 'books'),
            new StockedSku('GAME-1', 'A game', 'games'),
        ]);
    }

    #[Override]
    public function between(Period $period): iterable
    {
        foreach ([Lines::sold('BOOK-1', 2, 3000, 500), Lines::sold('GAME-1', 1, 4500, 0, 'USD', category: 'games')] as $line) {
            if ($period->contains($line->soldAt)) {
                yield $line;
            }
        }
    }

    #[Override]
    public function of(string $sku, Period $period): StockFigure
    {
        return match ($sku) {
            'BOOK-1' => new StockFigure($sku, 6, 2, 6, new DateTimeImmutable('2026-11-10T12:00:00Z')),
            'BOOK-2' => new StockFigure($sku, 4, 0, 4, new DateTimeImmutable('2026-05-01T12:00:00Z')),
            default => new StockFigure($sku, 0, 0, 0, new DateTimeImmutable('2026-11-10T12:00:00Z')),
        };
    }

    #[Override]
    public function all(): iterable
    {
        return $this->skus->all();
    }

    #[Override]
    public function find(string $sku): ?StockedSku
    {
        return $this->skus->find($sku);
    }
}
