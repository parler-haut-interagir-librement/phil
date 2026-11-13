<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

/**
 * One token of a formula: a command without its backslash, a number, a letter, another character, a brace, `^`, `_`,
 * `&`, a run of spaces, or the end. `$offset` is the byte where it starts.
 *
 * @internal
 */
final readonly class Token
{
    public const string COMMAND = 'command';

    public const string NUMBER = 'number';

    public const string LETTER = 'letter';

    public const string CHAR = 'char';

    public const string OPEN = '{';

    public const string CLOSE = '}';

    public const string SUP = '^';

    public const string SUB = '_';

    public const string ALIGN = '&';

    public const string SPACE = 'space';

    public const string END = 'end';

    public function __construct(
        public string $type,
        public string $value,
        public int $offset,
        public string $raw,
    ) {
    }

    public function is(string $type, ?string $value = null): bool
    {
        return $this->type === $type && (null === $value || $this->value === $value);
    }
}
