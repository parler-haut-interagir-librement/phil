<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Repository;

use Phil\CommerceBundle\Domain\Model\Quote;

interface QuoteRepository
{
    public function findQuote(string $orderNumber): ?Quote;

    public function addQuote(Quote $quote): void;
}
