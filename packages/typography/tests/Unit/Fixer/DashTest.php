<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\Dash;
use Phil\Typography\Fixer\DashStyle;
use Phil\Typography\Locale;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dash::class)]
#[CoversClass(DashStyle::class)]
final class DashTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function spaced(): iterable
    {
        yield 'em and en dashes' => ['a---b a--b', 'a—b a–b'];
        yield 'four hyphens' => ['a----b', 'a—-b'];
        yield 'ranges' => ['1914-1918, pages 12-15. 1-2', '1914–1918, pages 12–15. 1–2'];
        yield 'range in parentheses' => ['(5-7)', '(5–7)'];
        yield 'dates and chains' => ['2026-11-06 and 555-123-4567 and 1-2-3', '2026-11-06 and 555-123-4567 and 1-2-3'];
        yield 'long numbers' => ['12345-6 and 1-23456', '12345-6 and 1-23456'];
        yield 'decimals' => ['1.5-2 and 1-2.5 and 1,5-2 and 1-2,5', '1.5-2 and 1-2.5 and 1,5-2 and 1-2,5'];
        yield 'ratios, paths and times' => ['3:4-5 4-5:6 1/2-3 2-3/4', '3:4-5 4-5:6 1/2-3 2-3/4'];
        yield 'after an en dash' => ['1–2-3', '1–2-3'];
        yield 'between words' => ['this - that', 'this – that'];
        yield 'not a dash between words' => ['well-known - ', 'well-known - '];
        yield 'two spaces' => ['a  -  b', 'a  -  b'];
    }

    #[DataProvider('spaced')]
    public function testTheSpacedStyle(string $text, string $expected): void
    {
        self::assertSame([$expected], new Dash()->fix(Run::of($text))->texts);
        self::assertSame([$expected], new Dash(DashStyle::Spaced)->fix(Run::of($text))->texts);
    }

    public function testTheClosedStyle(): void
    {
        self::assertSame(['this—that, 1914–1918'], new Dash(DashStyle::Closed)->fix(Run::of('this - that, 1914-1918'))->texts);
    }

    public function testTheDashSeesTheNeighbouringParts(): void
    {
        self::assertSame(['this', ' – ', 'that'], new Dash()->fix(new Run(['this', ' - ', 'that']))->texts);
        self::assertSame(['1', '–', '2'], new Dash()->fix(new Run(['1', '-', '2']))->texts);
    }

    /**
     * @return iterable<array{string, DashStyle}>
     */
    public static function styles(): iterable
    {
        yield ['en', DashStyle::Closed];
        yield ['en_US', DashStyle::Closed];
        yield ['en_CA', DashStyle::Closed];
        yield ['en_PH', DashStyle::Closed];
        yield ['en_GB', DashStyle::Spaced];
        yield ['en_AU', DashStyle::Spaced];
        yield ['fr', DashStyle::Spaced];
        yield ['fr_CA', DashStyle::Spaced];
        yield ['de', DashStyle::Spaced];
    }

    #[DataProvider('styles')]
    public function testTheStyleOfALocale(string $locale, DashStyle $style): void
    {
        self::assertSame($style, DashStyle::for(Locale::parse($locale)));
    }
}
