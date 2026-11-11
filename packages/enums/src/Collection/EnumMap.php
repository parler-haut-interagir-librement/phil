<?php

declare(strict_types=1);

namespace Phil\Enums\Collection;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Override;
use Phil\Enums\Internal\Cases;
use Traversable;
use UnitEnum;

/**
 * A value for every case of one enum: the colour of each status, the price of each plan, the template of each kind
 * of message. PHP arrays cannot be keyed by enum cases; this map is, and iterating it yields the cases as keys.
 *
 * **Total.** A map holds a value for every case, checked when it is built: a case added to the enum and forgotten
 * here fails where the map is made, not as a missing key the day a record reaches it. **Immutable**: `with()`
 * returns a new map.
 *
 * @template TKey of UnitEnum
 * @template TValue
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
final readonly class EnumMap implements Countable, IteratorAggregate
{
    /**
     * @param class-string<TKey>    $enum
     * @param array<string, TValue> $values by case name, one for every case, in declaration order
     */
    private function __construct(
        private string $enum,
        private array $values,
    ) {
    }

    /**
     * The map of what the callback gives each case. A `match` without a `default` arm is the natural callback: PHP
     * and PHPStan then both refuse a case it forgets.
     *
     * @template TCase of UnitEnum
     * @template TResult
     *
     * @param class-string<TCase>      $enum
     * @param callable(TCase): TResult $value
     *
     * @return self<TCase, TResult>
     */
    public static function total(string $enum, callable $value): self
    {
        $values = [];

        foreach ($enum::cases() as $case) {
            $values[$case->name] = $value($case);
        }

        return new self($enum, $values);
    }

    /**
     * The map of an array keyed by the values of the cases — their names for a pure enum —, as a configuration file
     * writes it.
     *
     * @template TCase of UnitEnum
     * @template TResult
     *
     * @param class-string<TCase>        $enum
     * @param array<int|string, TResult> $values
     *
     * @return self<TCase, TResult>
     *
     * @throws InvalidArgumentException when a case has no value, or a key is no case
     */
    public static function fromArray(string $enum, array $values): self
    {
        $primitives = Cases::primitives($enum::cases());
        $missing = array_diff($primitives, array_keys($values));
        $unknown = array_diff(array_keys($values), $primitives);

        if ([] !== $missing || [] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('An enum map of %s needs a value for every case and nothing else; missing: [%s], unknown: [%s].', $enum, implode(', ', $missing), implode(', ', $unknown)));
        }

        return self::total($enum, static fn (UnitEnum $case): mixed => $values[Cases::primitive($case)]);
    }

    /**
     * The map of an array keyed by values that may leave cases out, each such case taking the default: a partial
     * table made total by a choice the caller names, rather than by a missing key. A key that is no case is still
     * refused.
     *
     * @template TCase of UnitEnum
     * @template TResult
     *
     * @param class-string<TCase>        $enum
     * @param array<int|string, TResult> $values
     * @param TResult                    $default
     *
     * @return self<TCase, TResult>
     *
     * @throws InvalidArgumentException when a key is no case
     */
    public static function fromPartial(string $enum, array $values, mixed $default): self
    {
        return self::fromArray($enum, $values + array_fill_keys(Cases::primitives($enum::cases()), $default));
    }

    /**
     * @return class-string<TKey>
     */
    public function enum(): string
    {
        return $this->enum;
    }

    /**
     * The value of the case.
     *
     * @param TKey $case
     *
     * @return TValue
     */
    public function get(UnitEnum $case): mixed
    {
        Cases::assertOf($this->enum, $case);

        return $this->values[$case->name];
    }

    /**
     * The same map, with this value for the case.
     *
     * @param TKey   $case
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function with(UnitEnum $case, mixed $value): self
    {
        Cases::assertOf($this->enum, $case);

        return new self($this->enum, [...$this->values, $case->name => $value]);
    }

    /**
     * The map of what the callback makes of each value.
     *
     * @template TResult
     *
     * @param callable(TValue, TKey): TResult $map
     *
     * @return self<TKey, TResult>
     */
    public function map(callable $map): self
    {
        return self::total($this->enum, fn (UnitEnum $case): mixed => $map($this->get($case), $case));
    }

    /**
     * The cases, in declaration order.
     *
     * @return CaseCollection<TKey>
     */
    public function keys(): CaseCollection
    {
        return CaseCollection::of($this->enum::cases());
    }

    /**
     * @return list<TValue>
     */
    public function values(): array
    {
        return array_values($this->values);
    }

    /**
     * The values keyed by the values of the cases, or their names for a pure enum: what `fromArray()` reads back.
     *
     * @return array<int|string, TValue>
     */
    public function toArray(): array
    {
        return array_combine(Cases::primitives($this->enum::cases()), $this->values);
    }

    #[Override]
    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        foreach ($this->enum::cases() as $case) {
            yield $case => $this->values[$case->name];
        }
    }
}
