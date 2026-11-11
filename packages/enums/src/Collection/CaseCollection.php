<?php

declare(strict_types=1);

namespace Phil\Enums\Collection;

use ArrayIterator;
use BackedEnum;
use Countable;
use IteratorAggregate;
use LogicException;
use Override;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Internal\Cases;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Phil\Enums\Sort\SortDirection;
use Random\Randomizer;
use Traversable;
use UnexpectedValueException;
use UnitEnum;

/**
 * Some cases of one enum, in an order: what the operations of `AsCollectableEnumTrait` return.
 *
 * **Immutable.** Every operation returns a new collection and leaves this one as it is, so a collection can be
 * kept and refined. **A list**: the cases are always indexed from 0, in their current order — declaration order
 * until a sort changes it —, never by the keys a filter or a sort would otherwise leave behind.
 *
 * @template-covariant T of UnitEnum
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class CaseCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<T> $cases
     */
    private function __construct(
        private array $cases,
    ) {
    }

    /**
     * @template TCase of UnitEnum
     *
     * @param iterable<TCase> $cases
     *
     * @return self<TCase>
     */
    public static function of(iterable $cases): self
    {
        $list = [];

        foreach ($cases as $case) {
            $list[] = $case;
        }

        return new self($list);
    }

    /**
     * @return list<T>
     */
    public function all(): array
    {
        return $this->cases;
    }

    #[Override]
    public function count(): int
    {
        return \count($this->cases);
    }

    public function isEmpty(): bool
    {
        return [] === $this->cases;
    }

    /**
     * @return Traversable<int, T>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->cases);
    }

    /**
     * The first case, or the first one the callback accepts; `null` when there is none.
     *
     * @param (callable(T): bool)|null $accept
     *
     * @return T|null
     */
    public function first(?callable $accept = null): ?UnitEnum
    {
        return null === $accept ? ($this->cases[0] ?? null) : array_find($this->cases, $accept);
    }

    /**
     * The last case, or the last one the callback accepts; `null` when there is none.
     *
     * @param (callable(T): bool)|null $accept
     *
     * @return T|null
     */
    public function last(?callable $accept = null): ?UnitEnum
    {
        return $this->reverse()->first($accept);
    }

    /**
     * Whether the collection holds this case.
     */
    public function contains(UnitEnum $case): bool
    {
        return \in_array($case, $this->cases, true);
    }

    /**
     * The cases the callback accepts.
     *
     * @param callable(T): bool $accept
     *
     * @return self<T>
     */
    public function filter(callable $accept): self
    {
        return new self(array_values(array_filter($this->cases, $accept)));
    }

    /**
     * The cases the callback refuses.
     *
     * @param callable(T): bool $refuse
     *
     * @return self<T>
     */
    public function reject(callable $refuse): self
    {
        return $this->filter(static fn (UnitEnum $case): bool => !$refuse($case));
    }

    /**
     * The cases among those given, in the order of this collection.
     *
     * @return self<T>
     */
    public function only(UnitEnum ...$cases): self
    {
        return $this->filter(static fn (UnitEnum $case): bool => \in_array($case, $cases, true));
    }

    /**
     * The cases other than those given.
     *
     * @return self<T>
     */
    public function except(UnitEnum ...$cases): self
    {
        return $this->filter(static fn (UnitEnum $case): bool => !\in_array($case, $cases, true));
    }

    /**
     * The cases whose meta property has this value, compared strictly; a case without the attribute has the
     * property's default value.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return self<T>
     */
    public function whereMeta(string $metaProperty, mixed $value): self
    {
        return $this->filter(static fn (UnitEnum $case): bool => Reflection::metaValue($metaProperty, $case) === $value);
    }

    /**
     * The cases in at least one of these groups, the values of their `Group` meta property: the named subsets an
     * enum declares, such as the statuses a public form offers.
     *
     * @return self<T>
     */
    public function inGroup(string ...$groups): self
    {
        return $this->filter(static fn (UnitEnum $case): bool => array_any(
            Reflection::metaValueList(Group::class, $case),
            static fn (mixed $group): bool => \in_array($group, $groups, true),
        ));
    }

    /**
     * The cases without PHP's `#[\Deprecated]` attribute: those a form still offers, while a record that holds a
     * deprecated case keeps reading it.
     *
     * @return self<T>
     */
    public function withoutDeprecated(): self
    {
        return $this->reject(Reflection::isDeprecated(...));
    }

    /**
     * The cases with PHP's `#[\Deprecated]` attribute.
     *
     * @return self<T>
     */
    public function deprecated(): self
    {
        return $this->filter(Reflection::isDeprecated(...));
    }

    /**
     * The cases the callback accepts, then those it refuses.
     *
     * @param callable(T): bool $accept
     *
     * @return array{self<T>, self<T>}
     */
    public function partition(callable $accept): array
    {
        return [$this->filter($accept), $this->reject($accept)];
    }

    /**
     * The cases by the key the callback gives each one, the keys in the order they first come up. A callback that
     * gives a list puts the case under each of its keys.
     *
     * @param callable(T): (int|string|list<int|string>) $key
     *
     * @return array<int|string, self<T>>
     */
    public function groupBy(callable $key): array
    {
        $groups = [];

        foreach ($this->cases as $case) {
            $keys = $key($case);

            foreach (\is_array($keys) ? $keys : [$keys] as $group) {
                $groups[$group][] = $case;
            }
        }

        return array_map(static fn (array $cases): self => new self($cases), $groups);
    }

    /**
     * The cases by the values of a meta property: under each of its values for a repeatable one such as `Group`.
     * A case without the attribute goes under the property's default value, or under no key when it is `null`.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return array<int|string, self<T>>
     *
     * @throws UnexpectedValueException when a value cannot be an array key
     */
    public function groupByMeta(string $metaProperty): array
    {
        return $this->groupBy(static function (UnitEnum $case) use ($metaProperty): array {
            $values = Reflection::metaValueList($metaProperty, $case);
            $default = $metaProperty::defaultValue();
            $values = [] === $values && null !== $default ? [$default] : $values;

            return array_map(static fn (mixed $value): int|string => \is_int($value) || \is_string($value)
                ? $value
                : throw new UnexpectedValueException(\sprintf('%s::%s has a %s value of type %s, which cannot be a group key.', $case::class, $case->name, $metaProperty, get_debug_type($value))), $values);
        });
    }

    /**
     * The cases keyed by what the callback gives each one.
     *
     * @param callable(T): (int|string) $key
     *
     * @return array<int|string, T>
     *
     * @throws LogicException when two cases get the same key
     */
    public function keyBy(callable $key): array
    {
        $keyed = [];

        foreach ($this->cases as $case) {
            $k = $key($case);

            if (\array_key_exists($k, $keyed)) {
                throw new LogicException(\sprintf('%s and %s both have the key "%s".', $keyed[$k]->name, $case->name, $k));
            }

            $keyed[$k] = $case;
        }

        return $keyed;
    }

    /**
     * The value of a meta property for each case, in order, its default value for a case without the attribute.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return list<mixed>
     */
    public function pluck(string $metaProperty): array
    {
        return $this->map(static fn (UnitEnum $case): mixed => Reflection::metaValue($metaProperty, $case));
    }

    /**
     * A case drawn at random, each as often as its `Weight` — 1 for a case without it —; `null` when there is no
     * case, or when every weight is 0. Pass a `Randomizer` built on a seeded engine for a draw that repeats.
     *
     * @return T|null
     */
    public function random(?Randomizer $randomizer = null): ?UnitEnum
    {
        /** @var list<int> $weights */
        $weights = $this->pluck(Weight::class);
        $total = array_sum($weights);

        if (0 === $total) {
            return null;
        }

        $draw = ($randomizer ?? new Randomizer())->getInt(1, $total);

        return array_find($this->cases, static function (UnitEnum $case, int $index) use (&$draw, $weights): bool {
            $draw -= $weights[$index];

            return $draw <= 0;
        });
    }

    /**
     * The cases ordered by the key the callback gives each one, compared with `<=>`, ascending unless told
     * otherwise — `true` or `SortDirection::Descending`. The sort is stable: two cases of the same key keep their
     * order.
     *
     * @param callable(T): mixed $key
     *
     * @return self<T>
     */
    public function sortBy(callable $key, bool|SortDirection $descending = false): self
    {
        $direction = $descending instanceof SortDirection ? $descending : SortDirection::descendingIf($descending);
        $keyed = array_map(static fn (UnitEnum $case): array => [$key($case), $case], $this->cases);
        usort($keyed, static fn (array $a, array $b): int => $direction->compare($a[0], $b[0]));

        return new self(array_column($keyed, 1));
    }

    /**
     * The cases ordered by name.
     *
     * @return self<T>
     */
    public function sortByName(bool|SortDirection $descending = false): self
    {
        return $this->sortBy(static fn (UnitEnum $case): string => $case->name, $descending);
    }

    /**
     * The cases ordered by value, or by name for a pure enum.
     *
     * @return self<T>
     */
    public function sortByValue(bool|SortDirection $descending = false): self
    {
        return $this->sortBy(Cases::primitive(...), $descending);
    }

    /**
     * The cases in reverse order.
     *
     * @return self<T>
     */
    public function reverse(): self
    {
        return new self(array_reverse($this->cases));
    }

    /**
     * What the callback makes of each case, in order.
     *
     * @template TResult
     *
     * @param callable(T): TResult $map
     *
     * @return list<TResult>
     */
    public function map(callable $map): array
    {
        return array_map($map, $this->cases);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return Cases::names($this->cases);
    }

    /**
     * The values of the cases, or their names for a pure enum.
     *
     * @return list<int|string>
     */
    public function values(): array
    {
        return Cases::primitives($this->cases);
    }

    /**
     * Name to value for the cases of a backed enum, their names for a pure one: what a choice list needs.
     *
     * @return array<string, int|string>|list<string>
     */
    public function options(): array
    {
        return Cases::options($this->cases);
    }

    /**
     * Whether the cases are those of a backed enum; `false` for an empty collection.
     */
    public function isBacked(): bool
    {
        return ($this->cases[0] ?? null) instanceof BackedEnum;
    }
}
