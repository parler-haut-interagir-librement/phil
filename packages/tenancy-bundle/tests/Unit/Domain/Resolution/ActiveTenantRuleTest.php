<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Resolution;

use Phil\TenancyBundle\Domain\Exception\TenantNotReachable;
use Phil\TenancyBundle\Domain\Resolution\ActiveTenantRule;
use Phil\TenancyBundle\Domain\Resolution\Outcome;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveTenantRule::class)]
final class ActiveTenantRuleTest extends TestCase
{
    public function testARememberedChoiceThatIsStillReachableIsActive(): void
    {
        $resolution = ActiveTenantRule::resolve($this->tenant('wholesale'), $this->both());

        self::assertSame(Outcome::Active, $resolution->outcome);
        self::assertSame('wholesale', $resolution->tenant()->value);
        self::assertFalse($resolution->staleChoiceDropped);
    }

    /**
     * Revoked from wholesale, still in retail: the account lands in retail because it is the only tenant left,
     * and the caller is told the old choice must be forgotten.
     */
    public function testARememberedChoiceNoLongerReachableIsDroppedForTheOnlyTenantLeft(): void
    {
        $resolution = ActiveTenantRule::resolve($this->tenant('wholesale'), ReachableTenants::of($this->tenant('retail')));

        self::assertSame('retail', $resolution->tenant()->value);
        self::assertTrue($resolution->staleChoiceDropped);
    }

    /**
     * A stale choice is never replaced by a neighbour when there is a real choice to make.
     */
    public function testARememberedChoiceNoLongerReachableAmongSeveralRequiresASelection(): void
    {
        $reachable = ReachableTenants::of($this->tenant('retail'), $this->tenant('events'));

        $resolution = ActiveTenantRule::resolve($this->tenant('wholesale'), $reachable);

        self::assertSame(Outcome::SelectionRequired, $resolution->outcome);
        self::assertNull($resolution->tenantOrNull());
        self::assertSame($reachable, $resolution->reachable);
        self::assertTrue($resolution->staleChoiceDropped);
    }

    public function testARememberedChoiceWithNothingReachableResolvesToNone(): void
    {
        $resolution = ActiveTenantRule::resolve($this->tenant('wholesale'), ReachableTenants::none());

        self::assertSame(Outcome::NoneReachable, $resolution->outcome);
        self::assertTrue($resolution->staleChoiceDropped);
    }

    public function testWithoutAChoiceASingleTenantIsActive(): void
    {
        $resolution = ActiveTenantRule::resolve(null, ReachableTenants::of($this->tenant('retail')));

        self::assertTrue($resolution->isActive());
        self::assertSame('retail', $resolution->tenant()->value);
        self::assertFalse($resolution->staleChoiceDropped);
    }

    /**
     * The first tenant of a list is an accident of ordering: the rule never picks it.
     */
    public function testWithoutAChoiceSeveralTenantsRequireASelection(): void
    {
        $resolution = ActiveTenantRule::resolve(null, $this->both());

        self::assertSame(Outcome::SelectionRequired, $resolution->outcome);
        self::assertFalse($resolution->isActive());
        self::assertFalse($resolution->staleChoiceDropped);
    }

    public function testWithoutAChoiceAndNothingReachableNoneIsActive(): void
    {
        $resolution = ActiveTenantRule::resolve(null, ReachableTenants::none());

        self::assertSame(Outcome::NoneReachable, $resolution->outcome);
        self::assertFalse($resolution->staleChoiceDropped);
    }

    public function testASwitchToAReachableTenantIsAccepted(): void
    {
        self::assertSame('retail', ActiveTenantRule::switchTo($this->tenant('retail'), $this->both())->value);
    }

    public function testASwitchToAnUnreachableTenantIsRefused(): void
    {
        $this->expectException(TenantNotReachable::class);
        $this->expectExceptionMessageIsOrContains('The tenant "events" is not reachable by this account.');

        ActiveTenantRule::switchTo($this->tenant('events'), $this->both());
    }

    public function testNoSwitchIsAcceptedWhenNothingIsReachable(): void
    {
        $this->expectException(TenantNotReachable::class);

        ActiveTenantRule::switchTo($this->tenant('retail'), ReachableTenants::none());
    }

    private function tenant(string $value): TenantId
    {
        return TenantId::fromString($value);
    }

    private function both(): ReachableTenants
    {
        return ReachableTenants::of($this->tenant('retail'), $this->tenant('wholesale'));
    }
}
