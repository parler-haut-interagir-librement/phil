<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;

/**
 * The normalizer of the readers given none: every type of the default registry, and `raw`. A normalizer is a value,
 * built once and shared by every reader.
 *
 * @internal
 */
final class RawNormalizer
{
    private static ?Normalizer $shared = null;

    private function __construct()
    {
    }

    public static function get(): Normalizer
    {
        return self::$shared ??= new Normalizer(BlockTypeRegistry::default()->with(StandardTypes::raw()));
    }
}
