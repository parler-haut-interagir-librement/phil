<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Scanner;

use Phil\Highlight\Scanner\Output;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Output::class)]
final class OutputTest extends TestCase
{
    public function testItJoinsTextsOfTheSameTypeAndDropsEmptyOnes(): void
    {
        $output = new Output();
        self::assertNull($output->significant());
        $output->add('a');
        $output->add('');
        $output->add(' ');
        $output->add('1', TokenType::Number);
        $output->add('2', TokenType::Number);
        $output->add('', TokenType::String);
        $output->append([new Token('3', TokenType::Number), new Token('x', TokenType::String)]);
        $output->add("\n ");

        self::assertEquals([new Token('a '), new Token('123', TokenType::Number), new Token('x', TokenType::String), new Token("\n ")], $output->tokens());
        self::assertEquals(new Token('x', TokenType::String), $output->significant(), 'spaces are not significant');
    }
}
