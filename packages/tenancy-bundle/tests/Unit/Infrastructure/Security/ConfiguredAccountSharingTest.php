<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Security;

use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Security\ConfiguredAccountSharing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredAccountSharing::class)]
final class ConfiguredAccountSharingTest extends TestCase
{
    public function testTheTenantsOfAGroupShareTheirAccounts(): void
    {
        $sharing = new ConfiguredAccountSharing([['fair', 'fair-backstage'], ['club', 'club-shop', 'club-forum']]);

        self::assertTrue($sharing->isShared('ana', $this->id('fair'), $this->id('fair-backstage')));
        self::assertTrue($sharing->isShared('ana', $this->id('club-forum'), $this->id('club-shop')));
        self::assertFalse($sharing->isShared('ana', $this->id('fair'), $this->id('club')));
        self::assertFalse($sharing->isShared('ana', $this->id('fair'), $this->id('elsewhere')));
        self::assertFalse($sharing->isShared('ana', $this->id('elsewhere'), $this->id('fair')));
    }

    public function testLinkedShopsShareTheirCustomerAccounts(): void
    {
        $sharing = new ConfiguredAccountSharing([], ShopNetwork::fromReferences(['general' => ['rose']]));

        self::assertTrue($sharing->isShared('ana', $this->id('rose'), $this->id('general')));
        self::assertFalse($sharing->isShared('ana', $this->id('rose'), $this->id('tavern')));
    }

    public function testNoAccountIsSharedWithARequestInNoTenant(): void
    {
        $sharing = new ConfiguredAccountSharing([['fair', 'fair-backstage']], ShopNetwork::fromReferences(['general' => ['rose']]));

        self::assertFalse($sharing->isShared('ana', $this->id('fair'), null));
        self::assertFalse($sharing->isShared('ana', $this->id('general'), null));
    }

    public function testWithNothingDeclaredNoAccountIsShared(): void
    {
        self::assertFalse(new ConfiguredAccountSharing()->isShared('ana', $this->id('fair'), $this->id('club')));
    }

    private function id(string $reference): TenantId
    {
        return TenantId::fromString($reference);
    }

    public function testASessionOpenedInNoTenantIsSharedOnlyWithSharedSessions(): void
    {
        $tavern = TenantId::fromString('tavern');

        self::assertFalse(new ConfiguredAccountSharing([['rose', 'tavern']])->isShared('ana', null, $tavern));
        self::assertTrue(new ConfiguredAccountSharing(sharedSessions: true)->isShared('ana', null, $tavern));
        self::assertFalse(new ConfiguredAccountSharing(sharedSessions: true)->isShared('ana', TenantId::fromString('rose'), $tavern), 'a session opened on a tenant stays on it');
    }
}
