<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Attribute\CurrentTenant;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Currency;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Pages of an application that knows nothing of tenants: the invoices and the currencies, read through the ORM,
 * and two pages that ask for the active tenant as an argument. The selector lists the reachable tenants; the
 * bundle's switch takes the choice.
 */
final readonly class TenancyController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HeaderReachableTenants $reachableTenants,
    ) {
    }

    public function invoices(): JsonResponse
    {
        $labels = array_map(static fn (Invoice $invoice): string => $invoice->label, $this->entityManager->getRepository(Invoice::class)->findBy([], ['label' => 'ASC']));

        return new JsonResponse($labels);
    }

    public function currencies(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (Currency $currency): string => $currency->code, $this->entityManager->getRepository(Currency::class)->findAll()));
    }

    public function current(#[CurrentTenant] TenantId $tenant): JsonResponse
    {
        return new JsonResponse([$tenant->value]);
    }

    public function optional(#[CurrentTenant] ?TenantId $tenant): JsonResponse
    {
        return new JsonResponse($tenant instanceof TenantId ? [$tenant->value] : []);
    }

    public function select(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (TenantId $tenant): string => $tenant->value, $this->reachableTenants->reachable()->toList()));
    }
}
