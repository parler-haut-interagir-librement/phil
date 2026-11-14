<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application;

use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Domain\Exception\TenantNotReachable;
use Phil\TenancyBundle\Domain\Resolution\Outcome;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\FixedReachableTenants;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\InMemoryActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveTenant::class)]
final class ActiveTenantTest extends TestCase
{
    public function testAReachableChoiceIsResolvedAndKept(): void
    {
        $store = new InMemoryActiveTenantStore(TenantId::fromString('wholesale'));

        $resolution = $this->activeTenant($store)->resolve();

        self::assertSame('wholesale', $resolution->tenant()->value);
        self::assertSame(0, $store->forgotten);
    }

    public function testAStaleChoiceIsForgotten(): void
    {
        $store = new InMemoryActiveTenantStore(TenantId::fromString('events'));

        $resolution = $this->activeTenant($store)->resolve();

        self::assertSame(Outcome::SelectionRequired, $resolution->outcome);
        self::assertSame(1, $store->forgotten);
        self::assertNull($store->remembered());
    }

    public function testASwitchIsRemembered(): void
    {
        $store = new InMemoryActiveTenantStore(TenantId::fromString('retail'));

        $this->activeTenant($store)->switchTo(TenantId::fromString('wholesale'));

        self::assertSame('wholesale', $store->remembered()?->value);
    }

    public function testARefusedSwitchLeavesTheChoiceAsItWas(): void
    {
        $store = new InMemoryActiveTenantStore(TenantId::fromString('retail'));

        try {
            $this->activeTenant($store)->switchTo(TenantId::fromString('events'));
            self::fail('The switch should have been refused.');
        } catch (TenantNotReachable) {
        }

        self::assertSame('retail', $store->remembered()?->value);
        self::assertSame(0, $store->forgotten);
    }

    public function testLeavingForgetsTheChoice(): void
    {
        $store = new InMemoryActiveTenantStore(TenantId::fromString('retail'));

        $this->activeTenant($store)->leave();

        self::assertNull($store->remembered());
    }

    private function activeTenant(InMemoryActiveTenantStore $store): ActiveTenant
    {
        return new ActiveTenant(
            $store,
            new FixedReachableTenants(ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale'))),
        );
    }
}
