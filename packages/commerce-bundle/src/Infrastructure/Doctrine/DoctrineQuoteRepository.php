<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Model\Quote;
use Phil\CommerceBundle\Domain\Repository\QuoteRepository;

/**
 * Quotes through Doctrine.
 */
final readonly class DoctrineQuoteRepository implements QuoteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findQuote(string $orderNumber): ?Quote
    {
        return $this->entityManager->find(Quote::class, $orderNumber);
    }

    #[Override]
    public function addQuote(Quote $quote): void
    {
        $this->entityManager->persist($quote);
    }
}
