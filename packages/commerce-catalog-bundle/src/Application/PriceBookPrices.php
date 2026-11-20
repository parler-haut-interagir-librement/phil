<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Repository\PriceBookRepository;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\Money\Money;

/**
 * Gives the core the price of a product from the price books: among the books that apply to the buyer's group, the
 * channel and the moment, in the product's currency, the first in the order of their `after` lists that prices the
 * SKU for the line's quantity. Other sellables than products are left to their own resolvers.
 *
 * The active books are read and ordered once, with their tiers indexed by SKU, and kept until `reset()`: a cart of twenty lines
 * re-priced on each change asks the repository once, not twenty times. The bundle tags the service `kernel.reset`,
 * so that a long-running worker reads the books again for each request or message.
 */
final class PriceBookPrices implements UnitPriceResolver
{
    /**
     * @var list<array{book: PriceBook, tiers: array<string, list<PriceTier>>}>|null
     */
    private ?array $books = null;

    public function __construct(
        private readonly PriceBookRepository $repository,
    ) {
    }

    #[Override]
    public function priceFor(Sellable $sellable, PricingContext $context): ?Money
    {
        if (ProductSellables::TYPE !== $sellable->reference->type) {
            return null;
        }

        foreach ($this->books() as ['book' => $book, 'tiers' => $tiers]) {
            if (!$book->currency->equals($sellable->unitPrice->currency) || !$book->appliesTo($context->customerGroup, $context->channel, $context->at)) {
                continue;
            }

            $reached = null;

            // A book's tiers come by quantity, smallest first: the last one reached is the highest.
            foreach ($tiers[$sellable->reference->id] ?? [] as $tier) {
                if ($tier->minQuantity <= $context->quantity) {
                    $reached = $tier;
                }
            }

            if ($reached instanceof PriceTier) {
                return $reached->unitPrice;
            }
        }

        return null;
    }

    /**
     * Forgets the books read, so that the next price reads them again.
     */
    public function reset(): void
    {
        $this->books = null;
    }

    /**
     * @return list<array{book: PriceBook, tiers: array<string, list<PriceTier>>}>
     */
    private function books(): array
    {
        if (null === $this->books) {
            $this->books = [];

            foreach (PriceBookOrder::sort($this->repository->active()) as $book) {
                $tiers = [];

                foreach ($book->tiers() as $tier) {
                    $tiers[$tier->sku][] = $tier;
                }

                $this->books[] = ['book' => $book, 'tiers' => $tiers];
            }
        }

        return $this->books;
    }
}
