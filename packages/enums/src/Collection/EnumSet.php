<?php

declare(strict_types=1);

namespace Phil\Enums\Collection;

use BackedEnum;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;
use Override;
use Phil\Enums\Internal\Cases;
use Traversable;
use UnitEnum;

/**
 * A set of cases of one enum: each case at most once, always in declaration order, with the operations of a set —
 * union, intersection, difference, complement — rather than those of a list. What permissions, the days a shop
 * opens or the channels a person accepts are.
 *
 * **Immutable**, like `CaseCollection`. **Of one enum**: the enum is known even when the set is empty, and an
 * operation between sets of two enums is refused. **Storable as a bitmask** when the enum is backed by integers
 * that are powers of two — the flags of C# and Python —, so that a whole set fits in one integer column.
 *
 * @template T of UnitEnum
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class EnumSet implements Countable, IteratorAggregate
{
    /**
     * @param class-string<T> $enum
     * @param list<T>         $cases in declaration order
     */
    private function __construct(
        private string $enum,
        private array $cases,
    ) {
    }

    /**
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase> $enum
     *
     * @return self<TCase>
     */
    public static function of(string $enum, UnitEnum ...$cases): self
    {
        Cases::assertOf($enum, ...$cases);

        return self::where($enum, static fn (UnitEnum $case): bool => \in_array($case, $cases, true));
    }

    /**
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase> $enum
     *
     * @return self<TCase>
     */
    public static function none(string $enum): self
    {
        return new self($enum, []);
    }

    /**
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase> $enum
     *
     * @return self<TCase>
     */
    public static function all(string $enum): self
    {
        return new self($enum, $enum::cases());
    }

    /**
     * The set of the cases these values — names for a pure enum — stand for, as a form or a request sends them.
     *
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase>  $enum
     * @param iterable<int|string> $values
     *
     * @return self<TCase>
     *
     * @throws InvalidArgumentException when a value is no case's
     */
    public static function fromValues(string $enum, iterable $values): self
    {
        $wanted = [];

        foreach ($values as $value) {
            $wanted[] = (string) $value;
        }

        $unknown = array_diff($wanted, Cases::primitives($enum::cases()));

        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('No case of enum %s has the value %s.', $enum, implode(', ', array_unique($unknown))));
        }

        return self::where($enum, static fn (UnitEnum $case): bool => \in_array((string) Cases::primitive($case), $wanted, true));
    }

    /**
     * The set a bitmask stands for: each case whose value is a bit of the mask.
     *
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase> $enum
     *
     * @return self<TCase>
     *
     * @throws LogicException           when the enum is not one of bit flags
     * @throws InvalidArgumentException when the mask has a bit no case stands for
     */
    public static function fromBitmask(string $enum, int $mask): self
    {
        $unknown = $mask & ~self::allFlags($enum);

        if (0 !== $unknown) {
            throw new InvalidArgumentException(\sprintf('The bitmask %d has bits no case of enum %s stands for: %d.', $mask, $enum, $unknown));
        }

        return self::where($enum, static fn (UnitEnum $case): bool => 0 !== ($mask & self::flag($case)));
    }

    /**
     * @return class-string<T>
     */
    public function enum(): string
    {
        return $this->enum;
    }

    public function contains(UnitEnum $case): bool
    {
        return \in_array($case, $this->cases, true);
    }

    /**
     * This set and these cases.
     *
     * @return self<T>
     */
    public function with(UnitEnum ...$cases): self
    {
        return $this->union(self::of($this->enum, ...$cases));
    }

    /**
     * This set but these cases.
     *
     * @return self<T>
     */
    public function without(UnitEnum ...$cases): self
    {
        return $this->diff(self::of($this->enum, ...$cases));
    }

    /**
     * The cases in this set, in the other, or in both.
     *
     * @param self<T> $other
     *
     * @return self<T>
     */
    public function union(self $other): self
    {
        $this->assertSameEnum($other);

        return self::where($this->enum, fn (UnitEnum $case): bool => $this->contains($case) || $other->contains($case));
    }

    /**
     * The cases in both sets.
     *
     * @param self<T> $other
     *
     * @return self<T>
     */
    public function intersect(self $other): self
    {
        $this->assertSameEnum($other);

        return self::where($this->enum, fn (UnitEnum $case): bool => $this->contains($case) && $other->contains($case));
    }

    /**
     * The cases in this set and not in the other.
     *
     * @param self<T> $other
     *
     * @return self<T>
     */
    public function diff(self $other): self
    {
        $this->assertSameEnum($other);

        return self::where($this->enum, fn (UnitEnum $case): bool => $this->contains($case) && !$other->contains($case));
    }

    /**
     * The cases of the enum that are not in this set.
     *
     * @return self<T>
     */
    public function complement(): self
    {
        return self::where($this->enum, fn (UnitEnum $case): bool => !$this->contains($case));
    }

    /**
     * @param self<T> $other
     */
    public function equals(self $other): bool
    {
        return $this->enum === $other->enum && $this->cases === $other->cases;
    }

    /**
     * Whether every case of this set is in the other.
     *
     * @param self<T> $other
     */
    public function isSubsetOf(self $other): bool
    {
        return $this->diff($other)->isEmpty();
    }

    /**
     * Whether every case of the other set is in this one.
     *
     * @param self<T> $other
     */
    public function isSupersetOf(self $other): bool
    {
        return $other->isSubsetOf($this);
    }

    public function isEmpty(): bool
    {
        return [] === $this->cases;
    }

    #[Override]
    public function count(): int
    {
        return \count($this->cases);
    }

    /**
     * @return Traversable<int, T>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->cases;
    }

    /**
     * The cases, as a collection to filter, sort or map.
     *
     * @return CaseCollection<T>
     */
    public function cases(): CaseCollection
    {
        return CaseCollection::of($this->cases);
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
     * The set as one integer: the values of its cases, or'ed.
     *
     * @throws LogicException when the enum is not one of bit flags
     */
    public function toBitmask(): int
    {
        self::allFlags($this->enum);

        return array_reduce($this->cases, static fn (int $mask, UnitEnum $case): int => $mask | self::flag($case), 0);
    }

    /**
     * @template TCase of UnitEnum
     *
     * @param class-string<TCase>   $enum
     * @param callable(TCase): bool $keep
     *
     * @return self<TCase>
     */
    private static function where(string $enum, callable $keep): self
    {
        return new self($enum, array_values(array_filter($enum::cases(), $keep)));
    }

    /**
     * Every bit the enum stands for, after checking that each case is a flag.
     *
     * @param class-string<UnitEnum> $enum
     */
    private static function allFlags(string $enum): int
    {
        return array_reduce($enum::cases(), static fn (int $mask, UnitEnum $case): int => $mask | self::flag($case), 0);
    }

    private static function flag(UnitEnum $case): int
    {
        if (!$case instanceof BackedEnum || !\is_int($case->value) || $case->value <= 0 || 0 !== ($case->value & ($case->value - 1))) {
            throw new LogicException(\sprintf('%s::%s is not a bit flag: a bitmask needs an enum backed by integers that are powers of two.', $case::class, $case->name));
        }

        return $case->value;
    }

    /**
     * @param self<T> $other
     */
    private function assertSameEnum(self $other): void
    {
        if ($other->enum !== $this->enum) {
            throw new InvalidArgumentException(\sprintf('A set of enum %s cannot be combined with a set of enum %s.', $this->enum, $other->enum));
        }
    }
}
