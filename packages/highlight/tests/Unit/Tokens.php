<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\Language;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

/**
 * Tokens written compactly for exact expectations: a typed token as `{type:text}`, a plain one as its text.
 */
final class Tokens
{
    /**
     * @param list<Token> $tokens
     */
    public static function render(array $tokens): string
    {
        return implode('', array_map(static fn (Token $token): string => $token->type instanceof TokenType ? '{'.$token->type->value.':'.$token->text.'}' : $token->text, $tokens));
    }

    public static function of(Language $language, string $code): string
    {
        return self::render($language->tokenize($code));
    }
}
