<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\TextDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextDirection::class)]
final class TextDirectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, TextDirection}>
     */
    public static function texts(): iterable
    {
        yield 'Latin' => ['Hello', TextDirection::LeftToRight];
        yield 'Hebrew' => ['שלום', TextDirection::RightToLeft];
        yield 'Arabic' => ['مرحبا', TextDirection::RightToLeft];
        yield 'Devanagari' => ['नमस्ते', TextDirection::LeftToRight];
        yield 'the first strong letter after digits and marks' => ['2026 — « مرحبا » hello', TextDirection::RightToLeft];
        yield 'Latin before Arabic' => ['« Hello » مرحبا', TextDirection::LeftToRight];
        yield 'no strong letter' => ['2026 — !', TextDirection::LeftToRight];
        yield 'empty' => ['', TextDirection::LeftToRight];
    }

    #[DataProvider('texts')]
    public function testTheDirectionOfTheFirstStrongLetter(string $text, TextDirection $direction): void
    {
        self::assertSame($direction, TextDirection::of($text));
    }

    public function testTheScanIsBounded(): void
    {
        self::assertSame(1024, TextDirection::MAX_SCAN);
        self::assertSame(TextDirection::LeftToRight, TextDirection::of(str_repeat('1', 1024).'مرحبا'));
        self::assertSame(TextDirection::RightToLeft, TextDirection::of(str_repeat('1', 1023).'مرحبا'));
    }
}
