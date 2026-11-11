<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenType::class)]
#[CoversClass(Token::class)]
final class TokenTypeTest extends TestCase
{
    public function testEachTypeHasTheClassOfItsValue(): void
    {
        self::assertSame('hl-keyword', TokenType::Keyword->className());
        self::assertSame('hl-attr-name', TokenType::AttributeName->className());
        self::assertSame(
            ['keyword', 'type', 'builtin', 'constant', 'number', 'string', 'regex', 'comment', 'operator', 'punctuation', 'variable', 'function', 'meta', 'tag', 'attr-name', 'entity', 'property', 'selector', 'section', 'heading', 'emphasis', 'strong', 'link', 'code', 'inserted', 'deleted'],
            array_map(static fn (TokenType $type): string => $type->value, TokenType::cases()),
        );
    }

    public function testATokenIsPlainUnlessTyped(): void
    {
        self::assertNull(new Token('x')->type);
        self::assertSame(TokenType::Number, new Token('1', TokenType::Number)->type);
    }
}
