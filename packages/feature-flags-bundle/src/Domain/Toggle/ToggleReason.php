<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Toggle;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The reason an operator gives for toggling a flag. Mandatory: an empty or blank reason is refused, so the
 * audit line always says why the behavior changed.
 *
 * Blank means Unicode blank: the reason is trimmed of every white space and every space separator (`\s` and
 * `\p{Z}` in Unicode mode, so U+00A0 NO-BREAK SPACE or U+3000 IDEOGRAPHIC SPACE included), and a reason left
 * empty is refused. A reason that is not valid UTF-8 is refused too.
 */
final readonly class ToggleReason
{
    public const int MAX_LENGTH = 1000;

    /**
     * The characters trimmed from both ends, and the only characters of a blank string.
     */
    public const string BLANK = '[\s\p{Z}]';

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        $trimmed = preg_replace('/^'.self::BLANK.'+|'.self::BLANK.'+$/uD', '', $value);

        if (null === $trimmed) {
            throw InvalidValue::because('A toggle reason must be valid UTF-8.');
        }

        if ('' === $trimmed) {
            throw InvalidValue::because('A toggle needs a reason; an empty or blank one is refused.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A toggle reason is at most %d characters.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }
}
