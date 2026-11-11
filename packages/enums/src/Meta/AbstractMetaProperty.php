<?php

declare(strict_types=1);

namespace Phil\Enums\Meta;

/**
 * A value an enum case carries as an attribute — a label, a colour, a weight —, read back through an accessor the
 * metadatable trait generates on the enum.
 *
 * A subclass is a PHP attribute: `#[Attribute] final class Label extends AbstractMetaProperty {}` makes
 * `#[Label('Draft')]` on a case, and `->label()` on the enum. Three things can be tuned in the subclass:
 *
 * - `transform()` rewrites the value as the attribute is built (`'red'` stored as `'text-red-500'`);
 * - `defaultValue()` is what a case that does not carry the attribute answers (`null` unless overridden);
 * - `customMethodName()` names the accessor, which is otherwise the short class name with a lower-case first letter.
 *
 * @template TValue
 */
abstract class AbstractMetaProperty
{
    /**
     * @param TValue $value
     */
    final public function __construct(
        public mixed $value,
    ) {
        $this->value = $this->transform($value);
    }

    /**
     * What a case answers when it does not carry this attribute. Narrow the return type in a subclass (`string`,
     * `int`…) and the PHPStan extension types the accessor without `null`.
     *
     * @return TValue|null
     */
    public static function defaultValue(): mixed
    {
        return null;
    }

    /**
     * A new instance holding the value, transformed as the constructor would: what `fromMeta()` and `tryFromMeta()`
     * are given to look a case up by its metadata.
     *
     * @param TValue $value
     */
    public static function make(mixed $value): static
    {
        return new static($value);
    }

    /**
     * The name of the accessor that reads this attribute on a case: `customMethodName()` when it gives one, else the
     * short class name with its first letter lower-cased (`Description` gives `description()`).
     */
    public static function method(): string
    {
        $custom = static::customMethodName();

        return null === $custom || '' === $custom ? self::accessorOf(static::class) : $custom;
    }

    /**
     * The value as stored once the attribute is built; the value given, unless a subclass rewrites it.
     *
     * @param TValue $value
     *
     * @return TValue
     */
    protected function transform(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The accessor's name when it should not be derived from the class name; `null` derives it.
     */
    protected static function customMethodName(): ?string
    {
        return null;
    }

    private static function accessorOf(string $class): string
    {
        $separator = strrpos($class, '\\');

        return lcfirst(false === $separator ? $class : substr($class, $separator + 1));
    }
}
