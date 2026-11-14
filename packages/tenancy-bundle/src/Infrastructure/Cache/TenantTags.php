<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Closure;
use Symfony\Component\Cache\CacheItem;

/**
 * The tags of a cache item, prefixed for the active tenant on the way in and stripped on the way out.
 *
 * A tag-aware pool reads the tags an item is saved with from the item itself, where `ItemInterface::tag()` put them:
 * the tags are rewritten there, before the pool saves the item, as Symfony's own tag-aware adapters read them.
 */
final class TenantTags
{
    /**
     * @var (Closure(CacheItem): array<array-key, mixed>)|null
     */
    private static ?Closure $newTags = null;

    /**
     * @var (Closure(CacheItem, array<string, mixed>): void)|null
     */
    private static ?Closure $setNewTags = null;

    /**
     * @var (Closure(CacheItem, array<string, mixed>): void)|null
     */
    private static ?Closure $setTags = null;

    private function __construct()
    {
    }

    /**
     * Prefixes the tags the item is about to be saved with. A tag already carrying the prefix, as on an item saved
     * again, is kept; any other tag of a tenant's form is refused.
     */
    public static function prefix(CacheItem $item, string $prefix): void
    {
        $tags = self::newTags($item);

        if ([] === $tags) {
            return;
        }

        $prefixed = [];

        foreach ($tags as $tag => $value) {
            $tag = (string) $tag;

            if ('' !== $prefix && str_starts_with($tag, $prefix)) {
                $prefixed[$tag] = $value;

                continue;
            }

            $tag = $prefix.TenantCacheNamespace::refuseReserved($tag, 'tag');
            $prefixed[$tag] = $tag;
        }

        self::$setNewTags ??= Closure::bind(static function (CacheItem $item, array $tags): void {
            $item->newMetadata[CacheItem::METADATA_TAGS] = $tags;
        }, null, CacheItem::class);

        (self::$setNewTags)($item, $prefixed);
    }

    /**
     * Strips the prefix from the tags the item was read with, so that its metadata names the tags it was saved with.
     */
    public static function strip(CacheItem $item, string $prefix): void
    {
        $tags = $item->getMetadata()[CacheItem::METADATA_TAGS] ?? null;

        if ('' === $prefix || !\is_array($tags)) {
            return;
        }

        self::$setTags ??= Closure::bind(static function (CacheItem $item, array $tags): void {
            $item->metadata[CacheItem::METADATA_TAGS] = $tags;
        }, null, CacheItem::class);

        (self::$setTags)($item, self::stripped($tags, $prefix));
    }

    /**
     * @param array<array-key, mixed> $metadata
     *
     * @return array<array-key, mixed>
     */
    public static function stripMetadata(array $metadata, string $prefix): array
    {
        if ('' === $prefix || !isset($metadata[CacheItem::METADATA_TAGS]) || !\is_array($metadata[CacheItem::METADATA_TAGS])) {
            return $metadata;
        }

        $metadata[CacheItem::METADATA_TAGS] = self::stripped($metadata[CacheItem::METADATA_TAGS], $prefix);

        return $metadata;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function newTags(CacheItem $item): array
    {
        self::$newTags ??= Closure::bind(static function (CacheItem $item): array {
            $tags = $item->newMetadata[CacheItem::METADATA_TAGS] ?? [];

            return \is_array($tags) ? $tags : [];
        }, null, CacheItem::class);

        return (self::$newTags)($item);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return array<string, string>
     */
    private static function stripped(array $tags, string $prefix): array
    {
        $stripped = [];

        foreach (array_keys($tags) as $tag) {
            $tag = (string) $tag;

            if (str_starts_with($tag, $prefix)) {
                $tag = substr($tag, \strlen($prefix));
            }

            $stripped[$tag] = $tag;
        }

        return $stripped;
    }
}
