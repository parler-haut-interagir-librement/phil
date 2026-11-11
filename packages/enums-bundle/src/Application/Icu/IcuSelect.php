<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Icu;

use BackedEnum;
use LogicException;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use UnitEnum;

/**
 * An ICU `select` message over the cases of an enum, to translate a sentence that changes with a case rather than
 * the case alone:
 *
 * ```
 * {status, select, pending {Pending} in_transit {In transit} other {}}
 * ```
 *
 * Each case is a branch keyed by its value — its name, in snake case, for a pure enum, since ICU keys are
 * identifiers —, its text what the callback gives: the case's `Label`, or its name, by default. Paste it in a
 * catalogue of the `+intl-icu` format and edit each branch's sentence.
 *
 * **A key must be an ICU keyword**: no white space and no pattern syntax, so `in-progress`, `v1.2` or `a b` would
 * make a message `MessageFormatter` refuses. The keys are checked to hold only letters, marks, digits and
 * underscores, never to be `other` — the branch ICU requires —, and never to come twice; an enum that breaks the rule
 * is refused with a message naming its case, rather than exported into a broken catalogue. The argument's name is
 * checked the same way.
 */
final class IcuSelect
{
    private function __construct()
    {
    }

    /** The keyword ICU requires as the last branch of a `select`. */
    private const string OTHER = 'other';

    /**
     * @param class-string<UnitEnum>            $enum
     * @param (callable(UnitEnum): string)|null $text
     *
     * @throws LogicException when the argument or a case's key is not an ICU keyword, is `other`, or comes twice
     */
    public static function of(string $enum, string $argument, ?callable $text = null, string $other = ''): string
    {
        if (!self::isKeyword($argument)) {
            throw new LogicException(\sprintf('"%s" cannot name an ICU argument: use letters, digits and underscores only.', $argument));
        }

        $text ??= EnumTranslation::plainLabel(...);
        $branches = [];

        foreach ($enum::cases() as $case) {
            $key = self::key($case);

            if (!self::isKeyword($key)) {
                throw new LogicException(\sprintf('The key "%s" of %s::%s is not an ICU keyword: ICU refuses white space and punctuation such as "-" or ".", so give the case a value of letters, digits and underscores.', $key, $enum, $case->name));
            }

            if (self::OTHER === $key) {
                throw new LogicException(\sprintf('The key of %s::%s is "other", the branch ICU keeps for every other value: give the case another value.', $enum, $case->name));
            }

            if (isset($branches[$key])) {
                throw new LogicException(\sprintf('%s has two cases keyed "%s": an ICU select takes each key once.', $enum, $key));
            }

            $branches[$key] = $key.' {'.self::escape($text($case)).'}';
        }

        return '{'.$argument.', select, '.implode(' ', array_merge($branches, [self::OTHER.' {'.self::escape($other).'}'])).'}';
    }

    /**
     * Letters, marks, digits and underscores: a subset of ICU's keywords, which exclude only white space and
     * pattern syntax, wide enough for any enum value and safe in every ICU version.
     */
    private static function isKeyword(string $key): bool
    {
        return 1 === preg_match('/^[\p{L}\p{M}\p{N}_]+$/Du', $key);
    }

    private static function key(UnitEnum $case): string
    {
        return $case instanceof BackedEnum
            ? (string) $case->value
            : mb_strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $case->name));
    }

    /**
     * ICU quotes a brace with apostrophes, and an apostrophe with another.
     */
    private static function escape(string $text): string
    {
        return strtr($text, ["'" => "''", '{' => "'{'", '}' => "'}'"]);
    }
}
