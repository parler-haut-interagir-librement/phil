<?php

declare(strict_types=1);

namespace Phil\Enums\Meta;

use BackedEnum;
use LogicException;
use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\RequiredMeta;
use Phil\Enums\Attribute\TransitionsTo;
use ReflectionAttribute;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * Reads the meta properties of an enum and their values on its cases.
 *
 * **Each enum is read once per process.** Attributes cannot change while the code runs, so the meta properties of
 * an enum and the values on each of its cases are kept after the first reading: an accessor, `fromMeta()` or
 * `whereMeta()` called again reflects nothing and instantiates no attribute.
 */
final class Reflection
{
    /** @var array<class-string, array<string, class-string<AbstractMetaProperty<*>>>> enum, accessor name, meta property */
    private static array $metaProperties = [];

    /** @var array<class-string, array<string, array<class-string, non-empty-list<mixed>>>> enum, case name, meta property, values */
    private static array $metaValues = [];

    /** @var array<class-string, array<string, bool>> enum, case name, deprecated */
    private static array $deprecated = [];

    /** @var array<class-string, UnitEnum|null> */
    private static array $defaultCases = [];

    /** @var array<class-string, array<string, list<UnitEnum>>> enum, case name, the cases it may move to */
    private static array $transitions = [];

    /**
     * The meta properties the enum enables: those its own `#[Meta]` lists, then those of the `#[Meta]` of each trait
     * it uses.
     *
     * @return list<class-string<AbstractMetaProperty<*>>>
     */
    public static function metaProperties(UnitEnum $enum): array
    {
        return array_values(self::accessors($enum));
    }

    /**
     * The meta property the enum enables whose accessor has this name, or `null`: what the metadatable trait reads
     * when an accessor is called. When two enabled properties claim the same accessor, the first one listed wins.
     *
     * @return class-string<AbstractMetaProperty<*>>|null
     */
    public static function metaPropertyNamed(UnitEnum $enum, string $accessor): ?string
    {
        return self::accessors($enum)[$accessor] ?? null;
    }

    /**
     * Get the value of a meta property on the provided enum, or the property's default value when the case does
     * not carry it.
     *
     * The static return type is `mixed`; PHPStan narrows the generated accessors
     * (`->label()`, ...) via MetadatableEnum\ReflectionExtension instead.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     */
    public static function metaValue(string $metaProperty, UnitEnum $enum): mixed
    {
        $values = self::metaValueList($metaProperty, $enum);

        return [] === $values ? $metaProperty::defaultValue() : $values[0];
    }

    /**
     * Every value of a meta property on the provided enum, in declaration order: several for a repeatable one such as
     * `Group`, none when the case does not carry it.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     *
     * @return list<mixed>
     */
    public static function metaValueList(string $metaProperty, UnitEnum $enum): array
    {
        return (self::$metaValues[$enum::class][$enum->name] ??= self::readMetaValues($enum))[$metaProperty] ?? [];
    }

    /**
     * The enabled meta properties of the enum by accessor name, read on first use and kept.
     *
     * @return array<string, class-string<AbstractMetaProperty<*>>>
     */
    private static function accessors(UnitEnum $enum): array
    {
        if (!isset(self::$metaProperties[$enum::class])) {
            $enabled = [];
            $declaring = new ReflectionEnum($enum);

            foreach ([$declaring, ...$declaring->getTraits()] as $source) {
                foreach ($source->getAttributes(Meta::class) as $meta) {
                    foreach ($meta->newInstance()->metaProperties as $metaProperty) {
                        $enabled[$metaProperty::method()] ??= $metaProperty;
                    }
                }
            }

            self::$metaProperties[$enum::class] = $enabled;
        }

        return self::$metaProperties[$enum::class];
    }

    /**
     * The meta property values a case carries, by meta property, in declaration order. Only meta property attributes
     * are instantiated, never another attribute of the case.
     *
     * @return array<class-string, non-empty-list<mixed>>
     */
    private static function readMetaValues(UnitEnum $enum): array
    {
        $values = [];

        foreach (new ReflectionEnumUnitCase($enum::class, $enum->name)->getAttributes(AbstractMetaProperty::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $values[$attribute->getName()][] = $attribute->newInstance()->value;
        }

        return $values;
    }

    /**
     * Whether the case carries PHP's `#[\Deprecated]` attribute.
     */
    public static function isDeprecated(UnitEnum $case): bool
    {
        return self::$deprecated[$case::class][$case->name] ??= new ReflectionEnumUnitCase($case::class, $case->name)->isDeprecated();
    }

    /**
     * The case the enum marks with `#[DefaultCase]`, or `null` when it marks none.
     *
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum> $enum
     *
     * @return TEnum|null
     *
     * @throws LogicException when the enum marks more than one case
     */
    public static function defaultCase(string $enum): ?UnitEnum
    {
        if (!\array_key_exists($enum, self::$defaultCases)) {
            self::$defaultCases[$enum] = self::readDefaultCase($enum);
        }

        return self::$defaultCases[$enum];
    }

    /**
     * The meta properties that `#[RequiredMeta]` asks for and a case does not carry, by case name; empty when every
     * case carries them all, or when the enum requires none.
     *
     * @param class-string<UnitEnum> $enum
     *
     * @return array<string, non-empty-list<class-string<AbstractMetaProperty<*>>>>
     */
    public static function missingRequiredMeta(string $enum): array
    {
        $required = [];

        foreach (new ReflectionEnum($enum)->getAttributes(RequiredMeta::class) as $attribute) {
            $required = [...$required, ...$attribute->newInstance()->metaProperties];
        }

        $missing = [];

        foreach ($enum::cases() as $case) {
            $absent = array_values(array_filter($required, static fn (string $metaProperty): bool => [] === self::metaValueList($metaProperty, $case)));

            if ([] !== $absent) {
                $missing[$case->name] = $absent;
            }
        }

        return $missing;
    }

    /**
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum> $enum
     *
     * @return TEnum|null
     */
    private static function readDefaultCase(string $enum): ?UnitEnum
    {
        $marked = array_values(array_filter(
            $enum::cases(),
            static fn (UnitEnum $case): bool => [] !== new ReflectionEnumUnitCase($enum, $case->name)->getAttributes(DefaultCase::class),
        ));

        if (\count($marked) > 1) {
            throw new LogicException(\sprintf('Enum %s marks %s with #[DefaultCase]; it may mark one case only.', $enum, implode(', ', array_map(static fn (UnitEnum $case): string => $case->name, $marked))));
        }

        return $marked[0] ?? null;
    }

    /**
     * The cases the case may move to, from its `#[TransitionsTo]`, in the order it names them; none for a final
     * case.
     *
     * @return list<UnitEnum>
     *
     * @throws LogicException when the attribute names a case of another enum
     */
    public static function transitions(UnitEnum $case): array
    {
        return self::$transitions[$case::class][$case->name] ??= self::readTransitions($case);
    }

    /**
     * @return list<UnitEnum>
     */
    private static function readTransitions(UnitEnum $case): array
    {
        $attributes = new ReflectionEnumUnitCase($case::class, $case->name)->getAttributes(TransitionsTo::class);
        $cases = $case::cases();

        return array_map(
            static fn (UnitEnum $target): UnitEnum => array_find($cases, static fn (UnitEnum $known): bool => $known === $target)
                ?? throw new LogicException(\sprintf('%s::%s moves to %s::%s, a case of another enum.', $case::class, $case->name, $target::class, $target->name)),
            [] === $attributes ? [] : $attributes[0]->newInstance()->cases,
        );
    }

    /**
     * The case of the enum an outside input stands for: by value, then by name, then by `#[Alias]` — each kind tried
     * on every case before the next —, with or without regard to ASCII letter case; `null` when it stands for none.
     *
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum> $enum
     *
     * @return TEnum|null
     */
    public static function parse(string $enum, int|string $input, bool $ignoreCase = false): ?UnitEnum
    {
        $input = (string) $input;
        $matches = $ignoreCase
            ? static fn (int|string $spelling): bool => 0 === strcasecmp((string) $spelling, $input)
            : static fn (int|string $spelling): bool => (string) $spelling === $input;

        $kinds = [
            static fn (UnitEnum $case): array => $case instanceof BackedEnum ? [$case->value] : [],
            static fn (UnitEnum $case): array => [$case->name],
            static fn (UnitEnum $case): array => array_filter(self::metaValueList(Alias::class, $case), \is_string(...)),
        ];

        foreach ($kinds as $spellings) {
            $case = array_find($enum::cases(), static fn (UnitEnum $case): bool => array_any($spellings($case), $matches));

            if (null !== $case) {
                return $case;
            }
        }

        return null;
    }
}
