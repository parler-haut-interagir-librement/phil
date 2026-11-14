<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Migration;

use Closure;
use Phil\SettingsBundle\Domain\Setting\Keys;

/**
 * One change a migration makes to the stored values when a setting's declaration evolves. Each step can run twice
 * and change nothing the second time.
 *
 * - `rename()` moves the values of a key to another, unless the subject already has a value of the new key; a value
 *   the new setting refuses, or equal to its default, is dropped.
 * - `convert()` gives each stored value, decoded, to a function, whose result the setting checks: a value it refuses,
 *   or equal to its default, is dropped. It serves a type changed, a choice renamed, a range moved.
 * - `defaultChanged()` drops the stored values equal to the new default, which no longer differ from it; with
 *   `keepForSite`, a site's setting with no stored value gets the previous default, so that the site keeps behaving
 *   as before. A user who never chose gets the new default.
 * - `remove()` drops the values of a key no longer declared.
 */
final readonly class Step
{
    /**
     * @param (Closure(mixed): mixed)|null      $converter
     * @param bool|int|string|list<string>|null $previous
     */
    private function __construct(
        public StepKind $kind,
        public string $key,
        public ?string $to = null,
        public ?Closure $converter = null,
        public bool|int|string|array|null $previous = null,
        public bool $keepForSite = false,
    ) {
    }

    public static function rename(string $from, string $to): self
    {
        return new self(StepKind::Rename, Keys::setting($from), Keys::setting($to));
    }

    /**
     * @param Closure(mixed): mixed $converter given a stored value, decoded from JSON; returns the new value
     */
    public static function convert(string $key, Closure $converter): self
    {
        return new self(StepKind::Convert, Keys::setting($key), converter: $converter);
    }

    /**
     * @param bool|int|string|list<string> $previous the default before it changed
     */
    public static function defaultChanged(string $key, bool|int|string|array $previous, bool $keepForSite = true): self
    {
        return new self(StepKind::DefaultChanged, Keys::setting($key), previous: $previous, keepForSite: $keepForSite);
    }

    public static function remove(string $key): self
    {
        return new self(StepKind::Remove, Keys::setting($key));
    }
}
