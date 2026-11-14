<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Shop;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShopNetwork::class)]
final class ShopNetworkTest extends TestCase
{
    public function testAShopSellsItsOwnCatalogueFirstThenThoseItIncludes(): void
    {
        $network = $this->network();

        self::assertSame(['general', 'rose', 'tavern'], $this->references($network->catalogues($this->id('general'))));
        self::assertSame(['rose'], $this->references($network->catalogues($this->id('rose'))));
        self::assertSame(['elsewhere'], $this->references($network->catalogues($this->id('elsewhere'))));
    }

    public function testAShopSellsTheCataloguesItIncludesAndNoOthers(): void
    {
        $network = $this->network();

        self::assertTrue($network->sells($this->id('general'), $this->id('general')));
        self::assertTrue($network->sells($this->id('general'), $this->id('tavern')));
        self::assertFalse($network->sells($this->id('rose'), $this->id('general')));
        self::assertFalse($network->sells($this->id('rose'), $this->id('tavern')));
        self::assertFalse($network->sells($this->id('general'), $this->id('elsewhere')));
    }

    public function testTheShopsThatIncludeACatalogue(): void
    {
        $network = ShopNetwork::fromReferences(['general' => ['rose'], 'rose' => [], 'fair' => ['rose', 'tavern']]);

        self::assertSame(['general', 'fair'], $this->references($network->includedBy($this->id('rose'))));
        self::assertSame(['fair'], $this->references($network->includedBy($this->id('tavern'))));
        self::assertSame([], $network->includedBy($this->id('general')));
    }

    public function testLinkedShopsShareTheirAccounts(): void
    {
        $network = ShopNetwork::fromReferences(['general' => ['rose', 'tavern'], 'fair' => ['market']]);

        self::assertTrue($network->sharesAccounts($this->id('rose'), $this->id('rose')));
        self::assertTrue($network->sharesAccounts($this->id('general'), $this->id('rose')));
        self::assertTrue($network->sharesAccounts($this->id('rose'), $this->id('general')));
        self::assertTrue($network->sharesAccounts($this->id('rose'), $this->id('tavern')));
        self::assertFalse($network->sharesAccounts($this->id('rose'), $this->id('market')));
        self::assertFalse($network->sharesAccounts($this->id('general'), $this->id('fair')));
        self::assertFalse($network->sharesAccounts($this->id('elsewhere'), $this->id('rose')));
    }

    public function testNoNetworkSellsOnlyEachShopsOwnCatalogue(): void
    {
        $network = ShopNetwork::none();

        self::assertSame(['general'], $this->references($network->catalogues($this->id('general'))));
        self::assertFalse($network->sharesAccounts($this->id('general'), $this->id('rose')));
    }

    public function testANumericReferenceIsAShop(): void
    {
        $network = ShopNetwork::fromReferences(['42' => ['7']]);

        self::assertSame(['42', '7'], $this->references($network->catalogues($this->id('42'))));
        self::assertSame(['42'], $this->references($network->includedBy($this->id('7'))));
    }

    /**
     * @param array<string, list<string>> $includes
     */
    #[DataProvider('refusals')]
    public function testAMalformedNetworkIsRefused(array $includes, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShopNetwork::fromReferences($includes);
    }

    /**
     * @return iterable<string, array{array<string, list<string>>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'itself' => [['general' => ['general']], 'The shop "general" cannot include itself.'];
        yield 'twice' => [['general' => ['rose', 'rose']], 'The shop "general" includes "rose" twice.'];
        yield 'two levels' => [['general' => ['rose'], 'rose' => ['tavern']], 'The shop "rose" is included by "general" and includes shops of its own: inclusion is one level deep.'];
        yield 'malformed' => [['general' => [' rose']], 'surrounding whitespace'];
    }

    private function network(): ShopNetwork
    {
        return ShopNetwork::fromReferences(['general' => ['rose', 'tavern'], 'rose' => []]);
    }

    private function id(string $reference): TenantId
    {
        return TenantId::fromString($reference);
    }

    /**
     * @param list<TenantId> $tenants
     *
     * @return list<string>
     */
    private function references(array $tenants): array
    {
        return array_map(static fn (TenantId $tenant): string => $tenant->value, $tenants);
    }
}
