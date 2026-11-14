<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Counts the invoices it can read, and records the tenant it was handled in, to be read by the tests.
 */
#[AsMessageHandler]
final class CountInvoicesHandler
{
    /** @var list<array{?string, int}> */
    public array $handled = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CountInvoices $message): void
    {
        $this->entityManager->clear();
        $this->handled[] = [$this->context->current()?->value, \count($this->entityManager->getRepository(Invoice::class)->findAll())];
    }
}
