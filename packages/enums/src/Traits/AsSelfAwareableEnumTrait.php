<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use BackedEnum;
use ReflectionEnum;

/**
 * What kind of enum this is, asked of the enum itself: pure, or backed by integers or by strings.
 */
trait AsSelfAwareableEnumTrait
{
    /**
     * Whether the enum has no backing values.
     */
    public static function isPure(): bool
    {
        return !self::isBacked();
    }

    /**
     * Whether each case has a backing value.
     */
    public static function isBacked(): bool
    {
        /** @phpstan-ignore function.impossibleType, function.alreadyNarrowedType */
        return is_subclass_of(self::class, BackedEnum::class);
    }

    /**
     * Whether the backing values are integers.
     */
    public static function isBackedByInteger(): bool
    {
        return 'int' === (string) new ReflectionEnum(self::class)->getBackingType();
    }

    /**
     * Whether the backing values are strings.
     */
    public static function isBackedByString(): bool
    {
        return 'string' === (string) new ReflectionEnum(self::class)->getBackingType();
    }
}
