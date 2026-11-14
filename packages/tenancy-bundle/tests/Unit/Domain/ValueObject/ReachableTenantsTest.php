<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReachableTenants::class)]
final class ReachableTenantsTest extends TestCase
{
    public function testNoneIsEmptyAndReachesNothing(): void
    {
        $none = ReachableTenants::none();

        self::assertTrue($none->isEmpty());
        self::assertCount(0, $none);
        self::assertNull($none->single());
        self::assertFalse($none->contains(TenantId::fromString('retail')));
    }

    public function testItContainsWhatItWasGivenAndNothingElse(): void
    {
        $reachable = ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale'));

        self::assertTrue($reachable->contains(TenantId::fromString('retail')));
        self::assertTrue($reachable->contains(TenantId::fromString('wholesale')));
        self::assertFalse($reachable->contains(TenantId::fromString('Retail')));
        self::assertFalse($reachable->isEmpty());
        self::assertCount(2, $reachable);
    }

    /**
     * Tenants spread from a keyed array still form a list.
     */
    public function testTheTenantsAreAList(): void
    {
        $retail = TenantId::fromString('retail');

        self::assertSame([$retail], ReachableTenants::of(...['first' => $retail])->toList());
    }

    public function testItKeepsTheGivenOrder(): void
    {
        $wholesale = TenantId::fromString('wholesale');
        $retail = TenantId::fromString('retail');

        $reachable = ReachableTenants::of($wholesale, $retail);

        self::assertSame([$wholesale, $retail], $reachable->toList());
        self::assertSame([$wholesale, $retail], iterator_to_array($reachable));
    }

    public function testASingleTenantIsReturnedBySingle(): void
    {
        $retail = TenantId::fromString('retail');

        self::assertSame($retail, ReachableTenants::of($retail)->single());
    }

    public function testSingleIsNullWithSeveralTenants(): void
    {
        self::assertNull(ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale'))->single());
    }

    public function testADuplicateIsRefusedRatherThanCollapsed(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The tenant "retail" is listed twice.');

        ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale'), TenantId::fromString('retail'));
    }
}
