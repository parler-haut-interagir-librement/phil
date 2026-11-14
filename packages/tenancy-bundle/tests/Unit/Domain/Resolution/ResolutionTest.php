<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Resolution;

use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\Resolution\Outcome;
use Phil\TenancyBundle\Domain\Resolution\Resolution;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolution::class)]
#[CoversClass(NoActiveTenant::class)]
final class ResolutionTest extends TestCase
{
    public function testAnActiveResolutionGivesItsTenant(): void
    {
        $retail = TenantId::fromString('retail');

        $resolution = Resolution::active($retail, ReachableTenants::of($retail));

        self::assertSame($retail, $resolution->tenant());
        self::assertSame($retail, $resolution->tenantOrNull());
    }

    public function testNoStaleChoiceIsDroppedUnlessSaid(): void
    {
        $retail = TenantId::fromString('retail');

        self::assertFalse(Resolution::active($retail, ReachableTenants::of($retail))->staleChoiceDropped);
        self::assertFalse(Resolution::selectionRequired(ReachableTenants::of($retail))->staleChoiceDropped);
        self::assertFalse(Resolution::noneReachable()->staleChoiceDropped);
        self::assertTrue(Resolution::noneReachable(true)->staleChoiceDropped);
    }

    public function testAskingForTheTenantWhenASelectionIsRequiredIsRefused(): void
    {
        $resolution = Resolution::selectionRequired(ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale')));

        $this->expectException(NoActiveTenant::class);
        $this->expectExceptionMessageIsOrContains('has not chosen one');

        $resolution->tenant();
    }

    public function testAskingForTheTenantWhenNoneIsReachableIsRefused(): void
    {
        $resolution = Resolution::noneReachable();

        $this->expectException(NoActiveTenant::class);
        $this->expectExceptionMessageIsOrContains('cannot act in any tenant');

        $resolution->tenant();
    }

    public function testNoneReachableCarriesAnEmptySet(): void
    {
        $resolution = Resolution::noneReachable(true);

        self::assertSame(Outcome::NoneReachable, $resolution->outcome);
        self::assertTrue($resolution->reachable->isEmpty());
        self::assertTrue($resolution->staleChoiceDropped);
    }
}
