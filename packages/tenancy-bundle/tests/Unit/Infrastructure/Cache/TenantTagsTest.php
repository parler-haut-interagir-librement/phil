<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use Phil\TenancyBundle\Infrastructure\Cache\TenantTags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * The tags of an item as it is saved and as it is read, with and without the prefix of a tenant.
 */
#[CoversClass(TenantTags::class)]
final class TenantTagsTest extends TestCase
{
    private const string PREFIX = 'tenant.retail.';

    public function testAnItemWithoutTagsIsLeftAsItIs(): void
    {
        $item = new ArrayAdapter()->getItem('k');

        TenantTags::prefix($item, self::PREFIX);

        self::assertSame([], $this->newMetadata($item));
    }

    public function testEveryTagGetsThePrefixExceptTheOnesThatCarryItAlready(): void
    {
        $item = new TagAwareAdapter(new ArrayAdapter())->getItem('k')->tag([self::PREFIX.'kept', 'plain', '123']);

        TenantTags::prefix($item, self::PREFIX);

        $expected = [self::PREFIX.'kept', self::PREFIX.'plain', self::PREFIX.'123'];
        self::assertSame(array_combine($expected, $expected), $this->newMetadata($item)[CacheItem::METADATA_TAGS]);
    }

    public function testWithoutPrefixTheTagsOfNoTenantAreKept(): void
    {
        $item = new TagAwareAdapter(new ArrayAdapter())->getItem('k')->tag(['plain']);

        TenantTags::prefix($item, '');

        self::assertSame(['plain' => 'plain'], $this->newMetadata($item)[CacheItem::METADATA_TAGS]);
    }

    public function testTheMetadataOfAnItemWithoutTagsOrPrefixIsReturnedWhole(): void
    {
        $metadata = ['expiry' => 12.5, 'ctime' => 3];

        self::assertSame($metadata, TenantTags::stripMetadata($metadata, self::PREFIX));
        self::assertSame([...$metadata, CacheItem::METADATA_TAGS => 'not a list'], TenantTags::stripMetadata([...$metadata, CacheItem::METADATA_TAGS => 'not a list'], self::PREFIX));
        self::assertSame([...$metadata, CacheItem::METADATA_TAGS => [self::PREFIX.'a' => 'a']], TenantTags::stripMetadata([...$metadata, CacheItem::METADATA_TAGS => [self::PREFIX.'a' => 'a']], ''));
    }

    public function testTheTagsOfTheMetadataLoseThePrefixWhateverTheirValues(): void
    {
        $stripped = TenantTags::stripMetadata(['expiry' => 12.5, CacheItem::METADATA_TAGS => [self::PREFIX.'a' => 1, 'b' => 'other', self::PREFIX.'7' => true, '8' => 8]], self::PREFIX);

        self::assertSame(['expiry' => 12.5, CacheItem::METADATA_TAGS => ['a' => 'a', 'b' => 'b', '7' => '7', '8' => '8']], $stripped);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function newMetadata(CacheItem $item): array
    {
        $metadata = new ReflectionProperty(CacheItem::class, 'newMetadata')->getValue($item);
        self::assertIsArray($metadata);

        return $metadata;
    }
}
