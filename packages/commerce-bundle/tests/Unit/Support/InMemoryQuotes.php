<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Quote;
use Phil\CommerceBundle\Domain\Repository\QuoteRepository;

/**
 * Quotes, in memory.
 */
final class InMemoryQuotes implements QuoteRepository
{
    /** @var array<string, Quote> */
    public array $quotes = [];

    #[Override]
    public function findQuote(string $orderNumber): ?Quote
    {
        return $this->quotes[$orderNumber] ?? null;
    }

    #[Override]
    public function addQuote(Quote $quote): void
    {
        $this->quotes[$quote->orderNumber] = $quote;
    }
}
