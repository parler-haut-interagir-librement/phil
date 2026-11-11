<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

use Override;

/**
 * A token of CSS Syntax Level 3, with its escapes resolved.
 *
 * `value` is the name of an ident, function, at-keyword or hash, the text of a string or URL, the character of a
 * delim, and the representation of a number as written (`+1.50`, `1e3`). A dimension keeps its unit in `unit`; a hash
 * says whether it could be an identifier in `isId`; a number says whether it is an integer in `isInteger`.
 */
final readonly class Token implements ComponentValue
{
    public function __construct(
        public TokenType $type,
        public string $value = '',
        public int $sourceLine = 1,
        public string $unit = '',
        public bool $isId = false,
        public bool $isInteger = false,
    ) {
    }

    #[Override]
    public function line(): int
    {
        return $this->sourceLine;
    }

    /** Whether the token is of this type and, for a delim, this character. */
    public function is(TokenType $type, ?string $delim = null): bool
    {
        return $this->type === $type && (null === $delim || $this->value === $delim);
    }

    /** Whether the token is an ident with this name, compared in ASCII lower case. */
    public function isIdent(string $name): bool
    {
        return TokenType::Ident === $this->type && strtolower($this->value) === $name;
    }
}
