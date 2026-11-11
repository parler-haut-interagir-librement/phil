<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Key::class)]
final class KeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function keys(): iterable
    {
        yield 'a word' => ['gore', true];
        yield 'words with hyphens' => ['self-harm', true];
        yield 'digits' => ['phase2', true];
        yield 'sixty-four characters' => [str_repeat('a', 64), true];
        yield 'sixty-five characters' => [str_repeat('a', 65), false];
        yield 'empty' => ['', false];
        yield 'a capital' => ['Gore', false];
        yield 'two hyphens' => ['self--harm', false];
        yield 'a leading hyphen' => ['-gore', false];
        yield 'a trailing hyphen' => ['gore-', false];
        yield 'an underscore' => ['self_harm', false];
        yield 'a trailing newline' => ["gore\n", false];
    }

    #[DataProvider('keys')]
    public function testAKeyIsLowercaseWordsJoinedByHyphens(string $key, bool $valid): void
    {
        self::assertSame($valid, Key::isValid($key));
    }
}
