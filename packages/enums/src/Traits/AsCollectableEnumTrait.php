<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Sort\SortDirection;
use Random\Randomizer;

/**
 * Operations on the cases of the enum: count them, take the first or the last, filter, sort. Each one that keeps
 * several cases returns a `CaseCollection`, which offers the same operations and can be refined further.
 */
trait AsCollectableEnumTrait
{
    /**
     * Every case, in declaration order.
     *
     * @return CaseCollection<static>
     */
    public static function collect(): CaseCollection
    {
        return CaseCollection::of(static::cases());
    }

    public static function count(): int
    {
        return \count(static::cases());
    }

    /**
     * The first case, or the first one the callback accepts.
     *
     * @param (callable(static): bool)|null $accept
     */
    public static function first(?callable $accept = null): ?static
    {
        return static::collect()->first($accept);
    }

    /**
     * The last case, or the last one the callback accepts.
     *
     * @param (callable(static): bool)|null $accept
     */
    public static function last(?callable $accept = null): ?static
    {
        return static::collect()->last($accept);
    }

    /**
     * The cases the callback accepts.
     *
     * @param callable(static): bool $accept
     *
     * @return CaseCollection<static>
     */
    public static function filter(callable $accept): CaseCollection
    {
        return static::collect()->filter($accept);
    }

    /**
     * The cases whose meta property has this value.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return CaseCollection<static>
     */
    public static function whereMeta(string $metaProperty, mixed $value): CaseCollection
    {
        return static::collect()->whereMeta($metaProperty, $value);
    }

    /**
     * The cases in at least one of these groups.
     *
     * @return CaseCollection<static>
     */
    public static function inGroup(string ...$groups): CaseCollection
    {
        return static::collect()->inGroup(...$groups);
    }

    /**
     * The cases ordered by the key the callback gives each one, in the direction given as a `SortDirection` or as
     * `true` for descending, as `CaseCollection::sortBy()` takes it.
     *
     * @param callable(static): mixed $key
     *
     * @return CaseCollection<static>
     */
    public static function sortBy(callable $key, bool|SortDirection $descending = false): CaseCollection
    {
        return static::collect()->sortBy($key, $descending);
    }

    /**
     * The cases without PHP's `#[\Deprecated]` attribute.
     *
     * @return CaseCollection<static>
     */
    public static function withoutDeprecated(): CaseCollection
    {
        return static::collect()->withoutDeprecated();
    }

    /**
     * The cases by the key the callback gives each one.
     *
     * @param callable(static): (int|string|list<int|string>) $key
     *
     * @return array<int|string, CaseCollection<static>>
     */
    public static function groupBy(callable $key): array
    {
        return static::collect()->groupBy($key);
    }

    /**
     * The cases by the values of a meta property.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return array<int|string, CaseCollection<static>>
     */
    public static function groupByMeta(string $metaProperty): array
    {
        return static::collect()->groupByMeta($metaProperty);
    }

    /**
     * A case drawn at random, each as often as its `Weight`.
     */
    public static function random(?Randomizer $randomizer = null): ?static
    {
        return static::collect()->random($randomizer);
    }
}
