<?php

declare(strict_types=1);

namespace Phil\Highlight;

/**
 * A piece of the source and what it is; `null` for text no theme colours, such as spaces and plain names. The texts of
 * a language's tokens, joined, give back its source byte for byte.
 */
final readonly class Token
{
    public function __construct(
        public string $text,
        public ?TokenType $type = null,
    ) {
    }
}
