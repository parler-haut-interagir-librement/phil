<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use LogicException;
use Phil\Enums\Meta\Reflection;

/**
 * The case the enum marks with `#[DefaultCase]`.
 */
trait AsDefaultableEnumTrait
{
    /**
     * @throws LogicException when the enum marks no case, or more than one
     */
    public static function default(): self
    {
        return self::tryDefault() ?? throw new LogicException(\sprintf('Enum %s marks no case with #[DefaultCase].', self::class));
    }

    /**
     * The default case, or `null` when the enum marks none.
     *
     * @throws LogicException when the enum marks more than one
     */
    public static function tryDefault(): ?self
    {
        return Reflection::defaultCase(self::class);
    }
}
