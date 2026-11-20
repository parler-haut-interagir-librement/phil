<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\DoctrineTenantActivation;
use Phil\TenancyBundle\Infrastructure\Doctrine\EntityTenant;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantWriteGuard;
use Phil\TenancyBundle\Infrastructure\Exception\CrossTenantWrite;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Currency;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A shared database, one entity manager, two tenants: the identity map of Doctrine answers `find()` before any query,
 * so an entity loaded for one tenant must not survive the switch to another, and a flush in one tenant must not write
 * another tenant's rows (ASVS 5.0, 8.4.1).
 */
#[CoversClass(DoctrineTenantActivation::class)]
#[CoversClass(TenantWriteGuard::class)]
#[CoversClass(EntityTenant::class)]
#[CoversClass(CrossTenantWrite::class)]
final class IdentityMapTest extends FunctionalTestCase
{
    public function testAnEntityLoadedInOneTenantIsNotFoundFromAnother(): void
    {
        $retail = $this->invoiceId('R-1');
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $loaded = $this->entityManager()->find(Invoice::class, $retail);
        self::assertInstanceOf(Invoice::class, $loaded);

        $seen = $context->within(TenantId::fromString('wholesale'), fn (): ?Invoice => $this->entityManager()->find(Invoice::class, $retail));

        self::assertNull($seen, 'the identity map does not leak the retail invoice into wholesale');
        self::assertFalse($this->entityManager()->contains($loaded));
        $again = $this->entityManager()->find(Invoice::class, $retail);
        self::assertInstanceOf(Invoice::class, $again, 'back in retail, the invoice loads again');
        self::assertSame('R-1', $again->label);
    }

    public function testAReferenceIsNotServedFromTheIdentityMapOfAnotherTenant(): void
    {
        $retail = $this->invoiceId('R-1');
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $loaded = $this->entityManager()->find(Invoice::class, $retail);

        $reference = $context->within(TenantId::fromString('wholesale'), fn (): ?Invoice => $this->entityManager()->getReference(Invoice::class, $retail));

        self::assertNotSame($loaded, $reference);
    }

    public function testEnteringTheSameTenantAgainKeepsItsEntities(): void
    {
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $loaded = $this->entityManager()->find(Invoice::class, $this->invoiceId('R-1'));
        self::assertInstanceOf(Invoice::class, $loaded);

        $context->within(TenantId::fromString('retail'), static fn (): null => null);

        self::assertTrue($this->entityManager()->contains($loaded));
    }

    public function testEntitiesEveryTenantSharesStayManaged(): void
    {
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $currency = $this->entityManager()->find(Currency::class, 'CAD');
        self::assertInstanceOf(Currency::class, $currency);

        $context->within(TenantId::fromString('wholesale'), static fn (): null => null);

        self::assertTrue($this->entityManager()->contains($currency));
    }

    public function testATenantWritesItsOwnRowsAndSharedOnes(): void
    {
        $this->context()->enter(TenantId::fromString('wholesale'));
        $this->entityManager()->persist(new Invoice('wholesale', 'W-2'));
        $this->entityManager()->persist(new Currency('USD'));

        $this->entityManager()->flush();

        self::assertSame(['R-1', 'R-2', 'W-1', 'W-2'], $this->labels());
        self::assertSame(['CAD', 'EUR', 'USD'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT code FROM currency ORDER BY code'));
    }

    public function testLeavingWhenNoTenantWasActiveKeepsTheEntitiesWrittenThere(): void
    {
        $invoice = new Invoice('retail', 'R-3');
        $this->entityManager()->persist($invoice);
        $this->entityManager()->flush();

        $this->context()->leave();

        self::assertTrue($this->entityManager()->contains($invoice));
    }

    public function testLeavingTheTenantDetachesItsEntities(): void
    {
        $retail = $this->invoiceId('R-1');
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $loaded = $this->entityManager()->find(Invoice::class, $retail);
        self::assertInstanceOf(Invoice::class, $loaded);

        $context->leave();

        self::assertFalse($this->entityManager()->contains($loaded));
        self::assertNull($this->entityManager()->find(Invoice::class, $retail), 'no tenant: the filter matches nothing');
    }

    public function testAnEntityPersistedForAnotherTenantIsRefused(): void
    {
        $this->context()->enter(TenantId::fromString('wholesale'));
        $this->entityManager()->persist(new Invoice('retail', 'R-3'));

        $this->expectException(CrossTenantWrite::class);
        $this->expectExceptionMessageIsOrContains('The entity "Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice" belongs to the tenant "retail", not to the active tenant "wholesale": the flush is refused.');

        $this->entityManager()->flush();
    }

    public function testAnEntityMovedToAnotherTenantIsRefused(): void
    {
        $this->context()->enter(TenantId::fromString('retail'));
        $loaded = $this->entityManager()->find(Invoice::class, $this->invoiceId('R-1'));
        self::assertInstanceOf(Invoice::class, $loaded);
        $loaded->tenantRef = 'wholesale';

        $this->expectException(CrossTenantWrite::class);

        $this->entityManager()->flush();
    }

    /**
     * An entity persisted in one tenant and not flushed stays scheduled across a switch: its write is refused in the
     * other tenant, and lands once back in its own.
     */
    public function testAPendingInsertionIsNeitherLostNorWrittenUnderAnotherTenant(): void
    {
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));
        $this->entityManager()->persist(new Invoice('retail', 'R-3'));

        try {
            $context->within(TenantId::fromString('wholesale'), fn () => $this->entityManager()->flush());
            self::fail('The flush in wholesale must be refused.');
        } catch (CrossTenantWrite) {
        }

        $this->entityManager()->flush();
        self::assertSame(['R-1', 'R-2', 'R-3', 'W-1'], $this->labels());
    }

    public function testAPendingDeletionIsRefusedUnderAnotherTenant(): void
    {
        $context = $this->context();
        $context->enter(TenantId::fromString('retail'));

        $loaded = $this->entityManager()->find(Invoice::class, $this->invoiceId('R-1'));
        self::assertInstanceOf(Invoice::class, $loaded);
        $this->entityManager()->remove($loaded);

        $this->expectException(CrossTenantWrite::class);

        $context->within(TenantId::fromString('wholesale'), fn () => $this->entityManager()->flush());
    }

    public function testWorkInNoTenantOrAcrossTenantsWritesAnyTenant(): void
    {
        $this->entityManager()->persist(new Invoice('retail', 'R-3'));
        $this->entityManager()->flush();

        $this->context()->enter(TenantId::fromString('wholesale'));
        $filters = $this->entityManager()->getFilters();
        $filters->suspend(ActiveTenantFilter::NAME);

        try {
            $this->entityManager()->persist(new Invoice('retail', 'R-4'));
            $this->entityManager()->flush();
        } finally {
            $filters->restore(ActiveTenantFilter::NAME);
        }

        $this->entityManager()->persist(new Invoice('wholesale', 'W-2'));
        $this->entityManager()->flush();
        self::assertSame(['R-1', 'R-2', 'R-3', 'R-4', 'W-1', 'W-2'], $this->labels());
    }

    private function context(): TenantContext
    {
        return $this->service(TenantContext::class, TenantContext::class);
    }

    private function invoiceId(string $label): int
    {
        $id = $this->entityManager()->getConnection()->fetchOne('SELECT id FROM invoice WHERE label = ?', [$label]);
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @return list<string>
     */
    private function labels(): array
    {
        /** @var list<string> */
        return $this->entityManager()->getConnection()->fetchFirstColumn('SELECT label FROM invoice ORDER BY label');
    }
}
