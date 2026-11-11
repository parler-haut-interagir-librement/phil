<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Closure;
use Phil\Enums\Internal\Cases;
use UnitEnum;

/**
 * The cases written out as one string, one piece per case: by default the `<option>` elements of an HTML `<select>`,
 * one per line.
 */
trait AsStringSelectableEnumTrait
{
    use AsSelectableEnumTrait;

    /**
     * One piece per case, joined by the glue. The callback receives the case's name and its primitive as a string —
     * its value when the enum is backed, its name when it is pure — and returns the piece.
     *
     * The default callback writes `<option value="…">…</option>`, the label made of the name's words (`IN_TRANSIT`
     * and `InTransit` read `In transit`). **It escapes the value and the label for HTML**; a callback of your own
     * returns text that is printed as it is, so it escapes what it writes into HTML itself.
     *
     * @param (Closure(string, string): string)|null $callback
     */
    public static function stringOptions(?Closure $callback = null, string $glue = "\n"): string
    {
        $callback ??= static fn (string $name, string $value): string => \sprintf(
            '<option value="%s">%s</option>',
            self::escapeForHtml($value),
            self::escapeForHtml(self::optionLabel($name)),
        );

        return implode($glue, array_map(
            static fn (UnitEnum $case): string => $callback($case->name, (string) Cases::primitive($case)),
            static::cases(),
        ));
    }

    /**
     * The words of a case name, as a sentence: split on underscores, or before each capital of a name in mixed case;
     * a name all in capitals is one word.
     */
    private static function optionLabel(string $name): string
    {
        $words = match (true) {
            str_contains($name, '_') => explode('_', $name),
            mb_strtoupper($name) === $name => [$name],
            default => preg_split('/(?=\p{Lu})/u', $name, -1, \PREG_SPLIT_NO_EMPTY),
        };

        return ucfirst(mb_strtolower(implode(' ', false === $words ? [$name] : $words)));
    }

    private static function escapeForHtml(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
