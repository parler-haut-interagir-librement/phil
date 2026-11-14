<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The filter registered as the installation guide says, on a real connection and outside any request: enabled
 * from the start, fail-closed until a tenant is set, and suspended only where a reviewer can see it.
 */
#[CoversClass(ActiveTenantFilter::class)]
final class DoctrineFilterTest extends FunctionalTestCase
{
    public function testTheFilterIsEnabledAndMatchesNothingUntilATenantIsSet(): void
    {
        self::assertTrue($this->entityManager()->getFilters()->isEnabled(ActiveTenantFilter::NAME));
        self::assertSame([], $this->labels());
    }

    public function testTheActivatedTenantSeesOnlyItsRows(): void
    {
        $this->filter()->activate(TenantId::fromString('retail'));

        self::assertSame(['R-1', 'R-2'], $this->labels());
    }

    /**
     * The reference is quoted by the connection: a reference shaped like SQL is only an unknown tenant.
     */
    public function testAReferenceShapedLikeSqlMatchesNothing(): void
    {
        $this->filter()->activate(TenantId::fromString("retail' OR '1' = '1"));

        self::assertSame([], $this->labels());
    }

    public function testWorkAcrossTenantsSuspendsTheFilterAndRestoresItsTenant(): void
    {
        $filters = $this->entityManager()->getFilters();
        $this->filter()->activate(TenantId::fromString('wholesale'));

        $filters->suspend(ActiveTenantFilter::NAME);

        try {
            self::assertSame(['R-1', 'R-2', 'W-1'], $this->labels());
        } finally {
            $filters->restore(ActiveTenantFilter::NAME);
        }

        self::assertSame(['W-1'], $this->labels());
    }

    private function filter(): ActiveTenantFilter
    {
        $filter = $this->entityManager()->getFilters()->getFilter(ActiveTenantFilter::NAME);
        self::assertInstanceOf(ActiveTenantFilter::class, $filter);

        return $filter;
    }

    /**
     * @return list<string>
     */
    private function labels(): array
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        $labels = [];

        foreach ($entityManager->getRepository(Invoice::class)->createQueryBuilder('i')->orderBy('i.label')->getQuery()->toIterable() as $invoice) {
            self::assertInstanceOf(Invoice::class, $invoice);
            $labels[] = $invoice->label;
        }

        return $labels;
    }
}
