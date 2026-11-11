<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

use Override;

/**
 * A simple block of CSS Syntax Level 3: the component values between `{` and `}`, `[` and `]`, or `(` and `)`.
 */
final readonly class SimpleBlock implements ComponentValue
{
    /**
     * @param TokenType::OpenCurly|TokenType::OpenSquare|TokenType::OpenParenthesis $opening
     * @param list<ComponentValue>                                                  $value
     */
    public function __construct(
        public TokenType $opening,
        public array $value,
        public int $sourceLine = 1,
    ) {
    }

    #[Override]
    public function line(): int
    {
        return $this->sourceLine;
    }
}
