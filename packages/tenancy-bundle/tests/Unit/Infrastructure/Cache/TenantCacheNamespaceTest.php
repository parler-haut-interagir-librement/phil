<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCacheNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

#[CoversClass(TenantCacheNamespace::class)]
final class TenantCacheNamespaceTest extends TestCase
{
    public function testAReadableReferenceStaysReadable(): void
    {
        self::assertSame('tenant.acme', TenantCacheNamespace::of(TenantId::fromString('acme')));
        self::assertSame('tenant.Rose-2_b', TenantCacheNamespace::of(TenantId::fromString('Rose-2_b')));
        self::assertSame('tenant.'.str_repeat('a', 64), TenantCacheNamespace::of(TenantId::fromString(str_repeat('a', 64))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function digested(): iterable
    {
        yield 'a dot' => ['a.b', 'tenant-'.self::digest('a.b')];
        yield 'a space' => ['Acme Shop', 'tenant-'.self::digest('Acme Shop')];
        yield 'a reserved character' => ['acme:shop', 'tenant-'.self::digest('acme:shop')];
        yield 'a letter outside ASCII' => ['café', 'tenant-'.self::digest('café')];
        yield 'one byte too long' => [str_repeat('a', 65), 'tenant-'.self::digest(str_repeat('a', 65))];
        yield 'a slash' => ['acme/', 'tenant-'.self::digest('acme/')];
    }

    #[DataProvider('digested')]
    public function testAnyOtherReferenceIsDigested(string $reference, string $namespace): void
    {
        $actual = TenantCacheNamespace::of(TenantId::fromString($reference));

        self::assertSame($namespace, $actual);
        self::assertSame(71, \strlen($actual));
        self::assertMatchesRegularExpression('/^tenant-[0-9a-f]{64}$/D', $actual);
        self::assertSame($actual, CacheItem::validateKey($actual));
    }

    public function testTheDigestIsTheHexadecimalSha256(): void
    {
        self::assertSame('tenant-2e7336dc8eba87ef472df568c35482abf2575dc3e5eac0c5c62b8ffaeac2c934', TenantCacheNamespace::of(TenantId::fromString('a.b')));
    }

    public function testTwoReferencesThatLookAlikeGetTwoNamespaces(): void
    {
        self::assertNotSame(TenantCacheNamespace::of(TenantId::fromString('acme')), TenantCacheNamespace::of(TenantId::fromString('Acme')));
        self::assertNotSame(TenantCacheNamespace::of(TenantId::fromString('a-b')), TenantCacheNamespace::of(TenantId::fromString('a.b')));
    }

    public function testTheTagPrefixIsTheNamespaceAndADot(): void
    {
        self::assertSame('tenant.acme.', TenantCacheNamespace::tagPrefix(TenantId::fromString('acme')));
        self::assertSame('', TenantCacheNamespace::tagPrefix(null));
    }

    public function testTheTenantsFormsAreReserved(): void
    {
        self::assertTrue(TenantCacheNamespace::isReserved('tenant.acme'));
        self::assertTrue(TenantCacheNamespace::isReserved('tenant-x'));
        self::assertFalse(TenantCacheNamespace::isReserved('tenant'));
        self::assertFalse(TenantCacheNamespace::isReserved('tenants'));
        self::assertFalse(TenantCacheNamespace::isReserved('a.tenant.acme'));
        self::assertSame('prices', TenantCacheNamespace::refuseReserved('prices', 'tag'));
    }

    public function testAReservedNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The tag "tenant.acme.prices" starts with "tenant." or "tenant-", which the tenants\' namespaces use.');

        TenantCacheNamespace::refuseReserved('tenant.acme.prices', 'tag');
    }

    private static function digest(string $reference): string
    {
        return hash('sha256', $reference);
    }
}
