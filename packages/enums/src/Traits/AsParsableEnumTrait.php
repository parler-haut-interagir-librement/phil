<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Meta\Reflection;
use ValueError;

/**
 * Reads a case from outside input — a query string, an import, an older API — more leniently than `from()`: by
 * value, by name, or by one of its `#[Alias]` spellings, and without regard to case when asked.
 *
 * **Values first, then names, then aliases.** Each kind is tried on every case before the next, so that a value is
 * never taken for another case's alias. `Reflection::parse()` reads the same way for an enum without the trait.
 */
trait AsParsableEnumTrait
{
    /**
     * The case this input stands for.
     *
     * @throws ValueError when it stands for none
     */
    public static function parse(int|string $input, bool $ignoreCase = false): self
    {
        return self::tryParse($input, $ignoreCase) ?? throw new ValueError(\sprintf('"%s" is neither a value, a name nor an alias of enum %s.', $input, self::class));
    }

    /**
     * The case this input stands for, or `null`.
     */
    public static function tryParse(int|string $input, bool $ignoreCase = false): ?self
    {
        return Reflection::parse(self::class, $input, $ignoreCase);
    }
}
