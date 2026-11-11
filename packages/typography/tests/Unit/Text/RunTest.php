<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Text;

use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Run::class)]
final class RunTest extends TestCase
{
    public function testItHoldsItsParts(): void
    {
        $run = new Run(['a', 'b', 'c'], [false, true]);

        self::assertSame(['a', 'b', 'c'], $run->texts);
        self::assertFalse($run->isLocked(0));
        self::assertTrue($run->isLocked(1));
        self::assertFalse($run->isLocked(2));
        self::assertSame(['x', 'y'], new Run(['x', 'y'])->texts);
        self::assertSame([], Run::of('x')->locked);
    }

    public function testMapGivesTheCharactersAroundEachPart(): void
    {
        $seen = [];
        $run = new Run(['ab', '', 'cé', 'LOCK', 'fg', ''], [false, false, false, true]);

        $mapped = $run->map(static function (string $text, string $before, string $after) use (&$seen): string {
            $seen[] = [$text, $before, $after];

            return strtoupper($text).'!';
        });

        self::assertSame([['ab', '', 'c'], ['', '!', 'c'], ['cé', '!', 'L'], ['fg', 'K', ''], ['', '!', '']], $seen);
        self::assertSame(['AB!', '!', 'Cé!', 'LOCK', 'FG!', '!'], $mapped->texts);
        self::assertSame([false, false, false, true], $mapped->locked);
    }

    public function testTheCharactersAroundAreWholeCharacters(): void
    {
        $seen = [];
        new Run(['aé', 'b', 'éc'])->map(static function (string $text, string $before, string $after) use (&$seen): string {
            $seen[] = $before.'|'.$after;

            return $text;
        });

        self::assertSame(['|b', 'é|é', 'b|'], $seen);
    }

    public function testMapSkipsEmptyPartsForTheCharacterAfter(): void
    {
        $seen = [];
        new Run(['a', '', '', 'b'])->map(static function (string $text, string $before, string $after) use (&$seen): string {
            $seen[] = $before.'|'.$after;

            return '';
        });

        self::assertSame(['|b', '|b', '|b', '|'], $seen);
    }

    public function testReplaceFixesEachPartWithTheCharactersAround(): void
    {
        $run = new Run(['word', ' !', 'end']);
        $fixed = $run->replace(static fn (string $text): string => str_replace(' !', '_!', $text));

        self::assertSame(['word', '_!', 'end'], $fixed->texts);
        self::assertSame(['A b'], Run::of('a b')->replace(static fn (string $text): string => str_replace('a', 'A', $text))->texts);
    }

    public function testReplaceSeesTheContextButNeverChangesIt(): void
    {
        $fix = static fn (string $text): string => preg_replace('/(?<=x)-|-(?=y)/', '+', $text) ?? $text;

        self::assertSame(['x', '+', 'y'], new Run(['x', '-', 'y'])->replace($fix)->texts);
        self::assertSame(['a', '-', 'b'], new Run(['a', '-', 'b'])->replace($fix)->texts);
    }

    public function testReplaceFixesThePartAloneWhenTheFixChangesTheContext(): void
    {
        $fix = static fn (string $text): string => str_replace(['ab', 'b'], ['X', 'B'], $text);

        self::assertSame(['a', 'B'], new Run(['a', 'b'])->replace($fix)->texts);
        self::assertSame(['B', 'c'], new Run(['b', 'c'])->replace(static fn (string $text): string => str_replace(['bc', 'b'], ['', 'B'], $text))->texts);
        self::assertSame(['', 'd'], new Run(['', 'd'])->replace(static fn (string $text): string => '' === $text ? 'Z' : $text)->texts);
        self::assertSame(['a', 'b'], new Run(['a', 'b'])->replace(static fn (string $text): string => 'ab' === $text ? 'XB' : $text)->texts);
    }

    public function testCharactersSeesTheWholeRun(): void
    {
        $run = new Run(['ab', 'cd', 'é'], [false, true]);
        $seen = [];

        $rewritten = $run->characters(static function (array $characters) use (&$seen): array {
            $seen = $characters;

            return array_map(strtoupper(...), [...$characters, 'extra']);
        });

        self::assertSame(['a', 'b', 'c', 'd', 'é'], $seen);
        self::assertSame(['AB', 'cd', 'é'], $rewritten->texts);
        self::assertSame([false, true], $rewritten->locked);
    }

    public function testCharactersKeepsWhatTheRewriteLeavesOut(): void
    {
        $run = new Run(['ab', '', 'c']);

        self::assertSame(['xb', '', 'c'], $run->characters(static fn (array $characters): array => [0 => 'x'])->texts);
        self::assertSame([], new Run([])->characters(static fn (array $characters): array => $characters)->texts);
    }
}
