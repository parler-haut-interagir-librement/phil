<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use BadMethodCallException;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Stringable;
use UnitEnum;
use ValueError;

/**
 * The metadata of the cases, read through accessors: each meta property the enum enables with `#[Meta]` gives the
 * case a method of its name (`Label` gives `->label()`), which answers the value the case carries, or the property's
 * default value when the case carries none. A method no enabled meta property names fails as PHP fails on an
 * undefined method, so that a misspelled accessor never passes for a missing value.
 */
trait AsMetadatableEnumTrait
{
    /**
     * The first case carrying the value of this meta property, or `null`.
     *
     * @template TMetaValue
     *
     * @param AbstractMetaProperty<TMetaValue> $metaProperty
     */
    public static function tryFromMeta(AbstractMetaProperty $metaProperty): ?static
    {
        return array_find(
            static::cases(),
            static fn (UnitEnum $case): bool => Reflection::metaValue($metaProperty::class, $case) === $metaProperty->value,
        );
    }

    /**
     * The first case carrying the value of this meta property.
     *
     * @template TMetaValue
     *
     * @param AbstractMetaProperty<TMetaValue> $metaProperty
     *
     * @throws ValueError when no case carries it; the message names the enum, the property and the value
     */
    public static function fromMeta(AbstractMetaProperty $metaProperty): static
    {
        $found = static::tryFromMeta($metaProperty);

        if (null !== $found) {
            return $found;
        }

        $sought = $metaProperty->value;

        throw new ValueError(\sprintf('Enum %s does not have a case with a meta property "%s" of value "%s"', self::class, $metaProperty::class, \is_scalar($sought) || $sought instanceof Stringable ? $sought : get_debug_type($sought)));
    }

    /**
     * Whether the case carries PHP's `#[\Deprecated]` attribute.
     */
    public function isDeprecated(): bool
    {
        return Reflection::isDeprecated($this);
    }

    /**
     * The value of the meta property whose accessor is called.
     *
     * @param array<int, mixed> $arguments ignored: an accessor takes no argument
     *
     * @throws BadMethodCallException when no meta property the enum enables has an accessor of that name
     */
    public function __call(string $property, array $arguments): mixed
    {
        $metaProperty = Reflection::metaPropertyNamed($this, $property);

        if (null === $metaProperty) {
            throw new BadMethodCallException(\sprintf('Call to undefined method %s::%s()', self::class, $property));
        }

        return Reflection::metaValue($metaProperty, $this);
    }
}
