<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredHostTenants::class)]
final class ConfiguredHostTenantsTest extends TestCase
{
    public function testEachHostServesItsTenant(): void
    {
        $hosts = new ConfiguredHostTenants(['shop.example' => 'general', 'Rose.Example' => 'rose', 'bücher.example' => 'books', 'shop.localhost' => 'dev']);

        self::assertSame('general', $hosts->tenantFor('shop.example')?->value);
        self::assertSame('rose', $hosts->tenantFor('rose.example')?->value);
        self::assertSame('books', $hosts->tenantFor('xn--bcher-kva.example')?->value);
        self::assertSame('dev', $hosts->tenantFor('shop.localhost')?->value);
        self::assertNull($hosts->tenantFor('www.shop.example'));
        self::assertNull($hosts->tenantFor('example'));
    }

    #[DataProvider('refusals')]
    public function testAMalformedHostIsRefused(string $host, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ConfiguredHostTenants([$host => 'general']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'port' => ['shop.example:8443', '"shop.example:8443" is not a host alone'];
        yield 'path' => ['shop.example/fr', '"shop.example/fr" is not a host alone'];
        yield 'trailing slash' => ['shop.example/', '"shop.example/" is not a host alone'];
        yield 'query' => ['shop.example?a=1', 'is not a host alone'];
        yield 'fragment' => ['shop.example#top', 'is not a host alone'];
        yield 'address' => ['192.0.2.1', '"192.0.2.1" is not a host: '];
        yield 'credentials' => ['me@shop.example', '"me@shop.example" is not a host: '];
    }

    public function testAHostListedTwiceOnceReadIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The host "shop.example" is listed twice.');

        new ConfiguredHostTenants(['shop.example' => 'general', 'SHOP.example' => 'rose']);
    }

    public function testAMalformedReferenceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new ConfiguredHostTenants(['shop.example' => '']);
    }

    public function testAnAliasServesTheTenantOfItsHost(): void
    {
        $hosts = new ConfiguredHostTenants(['rose.example' => 'rose', 'shop.example' => 'general'], ['WWW.Rose.Example' => 'Rose.Example', 'rosé.example' => 'rose.example']);

        self::assertSame('rose', $hosts->tenantFor('www.rose.example')?->value);
        self::assertSame('rose', $hosts->tenantFor('xn--ros-dma.example')?->value);
        self::assertSame('rose.example', $hosts->canonicalHostOf('www.rose.example'));
        self::assertSame('rose.example', $hosts->canonicalHostOf('xn--ros-dma.example'));
        self::assertNull($hosts->canonicalHostOf('rose.example'), 'A host is no alias.');
        self::assertNull($hosts->canonicalHostOf('elsewhere.example'));
        self::assertNull($hosts->tenantFor('elsewhere.example'));
    }

    /**
     * @param array<string, string> $aliases
     */
    #[DataProvider('aliasRefusals')]
    public function testAnAliasIsRefused(array $aliases, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ConfiguredHostTenants(['rose.example' => 'rose', 'shop.example' => 'general'], $aliases);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function aliasRefusals(): iterable
    {
        yield 'a host of its own' => [['Shop.Example' => 'rose.example'], 'The alias "shop.example" is a host of its own.'];
        yield 'listed twice' => [['www.rose.example' => 'rose.example', 'WWW.rose.example' => 'shop.example'], 'The alias "www.rose.example" is listed twice.'];
        yield 'of no host' => [['www.rose.example' => 'tavern.example'], 'The alias "www.rose.example" stands for "tavern.example", which is not a host of a tenant.'];
        yield 'of an alias' => [['www.rose.example' => 'rose.example', 'w3.rose.example' => 'www.rose.example'], 'stands for "www.rose.example", which is not a host of a tenant.'];
        yield 'a malformed alias' => [['www.rose.example:80' => 'rose.example'], 'is not a host alone'];
        yield 'a malformed host' => [['www.rose.example' => 'rose.example/'], 'is not a host alone'];
    }
}
