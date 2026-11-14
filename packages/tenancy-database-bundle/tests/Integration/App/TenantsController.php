<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Invoice;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Reads the invoices of the host's tenant, or of two tenants one after the other in one request, with the entity
 * manager and with native SQL.
 */
final readonly class TenantsController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $context,
    ) {
    }

    public function invoices(): JsonResponse
    {
        return new JsonResponse($this->read());
    }

    public function both(): JsonResponse
    {
        $read = [];

        foreach (['retail', 'wholesale', 'retail'] as $i => $tenant) {
            $read[$i.'-'.$tenant] = $this->context->within(TenantId::fromString($tenant), $this->read(...));
        }

        return new JsonResponse($read);
    }

    /**
     * @return array{entities: list<string>, native: list<string>, first: ?string}
     */
    private function read(): array
    {
        $entities = array_map(static fn (Invoice $invoice): string => $invoice->label, $this->entityManager->getRepository(Invoice::class)->findBy([], ['label' => 'ASC']));
        /** @var list<string> $native */
        $native = $this->entityManager->getConnection()->fetchFirstColumn('SELECT label FROM invoice ORDER BY label');
        $first = $this->entityManager->find(Invoice::class, 1);

        return ['entities' => $entities, 'native' => $native, 'first' => $first?->label];
    }
}
