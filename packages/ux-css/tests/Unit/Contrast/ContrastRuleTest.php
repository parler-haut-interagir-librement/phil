<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Contrast;

use Phil\UxCss\Contrast\ContrastRule;
use Phil\UxCss\Contrast\Pair;
use Phil\UxCss\Contrast\Violation;
use Phil\UxCss\Token\Scheme;
use Phil\UxCss\Token\TokenSheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContrastRule::class)]
final class ContrastRuleTest extends TestCase
{
    private const string PAGE = ':root {
        --phil-color-surface: light-dark(oklch(1 0 0), oklch(0 0 0));
        --phil-color-surface-raised: light-dark(oklch(0.95 0 0), oklch(0.15 0 0));
        --phil-color-text: light-dark(oklch(0 0 0), oklch(1 0 0));
        --phil-color-text-muted: light-dark(oklch(0.3 0 0), oklch(0.8 0 0));
        --phil-color-link: light-dark(oklch(0.3 0 0), oklch(0.8 0 0));
        --phil-color-focus: light-dark(oklch(0.5 0 0), oklch(0.7 0 0));
        --phil-color-activity-1: light-dark(oklch(0.9 0 0), oklch(0.3 0 0));
        --phil-color-activity-2: light-dark(oklch(0.7 0 0), oklch(0.5 0 0));
        --phil-color-activity-3: light-dark(oklch(0.5 0 0), oklch(0.7 0 0));
        --phil-color-activity-4: light-dark(oklch(0.3 0 0), oklch(0.9 0 0));
    }';

    public function testASheetThatKeepsTheRuleHasNoViolation(): void
    {
        self::assertSame([], new ContrastRule()->check(TokenSheet::fromCss(self::PAGE.':root {
            --phil-gray-10: oklch(0.10 0 0);
            --phil-gray-60: oklch(0.60 0 0);
            --phil-gray-95: oklch(0.95 0 0);
            --phil-color-x-fill-quiet: light-dark(var(--phil-gray-95), var(--phil-gray-10));
            --phil-color-x-on-quiet: light-dark(var(--phil-gray-10), var(--phil-gray-95));
            --phil-color-x-fill-loud: light-dark(var(--phil-gray-10), var(--phil-gray-95));
            --phil-color-x-on-loud: light-dark(var(--phil-gray-95), var(--phil-gray-10));
            --phil-color-x-border-loud: light-dark(var(--phil-gray-10), var(--phil-gray-60));
        }')));
    }

    public function testThePairsAreThoseOfThePageThenOfEachRoleThenTheExtraOnes(): void
    {
        $extra = new Pair('--phil-color-badge', '--phil-color-surface', 3.0);
        $pairs = new ContrastRule([$extra])->pairs(TokenSheet::fromCss(':root { --phil-color-x-fill-quiet: red; }'));

        self::assertEquals([
            new Pair('--phil-color-text', '--phil-color-surface', 4.5),
            new Pair('--phil-color-text', '--phil-color-surface-raised', 4.5),
            new Pair('--phil-color-text-muted', '--phil-color-surface', 4.5),
            new Pair('--phil-color-text-muted', '--phil-color-surface-raised', 4.5),
            new Pair('--phil-color-link', '--phil-color-surface', 4.5),
            new Pair('--phil-color-link', '--phil-color-surface-raised', 4.5),
            new Pair('--phil-color-surface', '--phil-color-text', 4.5),
            new Pair('--phil-color-focus', '--phil-color-surface', 3.0),
            new Pair('--phil-color-focus', '--phil-color-surface-raised', 3.0),
            new Pair('--phil-color-activity-3', '--phil-color-surface', 3.0),
            new Pair('--phil-color-activity-4', '--phil-color-surface', 3.0),
            new Pair('--phil-color-activity-3', '--phil-color-activity-1', 3.0),
            new Pair('--phil-color-activity-4', '--phil-color-activity-2', 3.0),
            new Pair('--phil-color-x-on-quiet', '--phil-color-x-fill-quiet', 4.5),
            new Pair('--phil-color-x-on-loud', '--phil-color-x-fill-loud', 4.5),
            new Pair('--phil-color-x-on-quiet', '--phil-color-surface', 4.5),
            new Pair('--phil-color-x-on-quiet', '--phil-color-surface-raised', 4.5),
            new Pair('--phil-color-x-border-loud', '--phil-color-surface', 3.0),
            new Pair('--phil-color-x-border-loud', '--phil-color-surface-raised', 3.0),
            new Pair('--phil-color-x-fill-loud', '--phil-color-surface', 3.0),
            new Pair('--phil-color-x-fill-loud', '--phil-color-surface-raised', 3.0),
            $extra,
        ], $pairs);
    }

    public function testAPairBelowItsMinimumIsAViolationInItsScheme(): void
    {
        $violations = new ContrastRule([new Pair('--phil-color-badge', '--phil-color-surface', 3.0)])->check(TokenSheet::fromCss(
            self::PAGE.':root { --phil-color-badge: light-dark(oklch(0.9 0 0), oklch(0.2 0 0)); }',
        ));

        self::assertCount(2, $violations);
        self::assertSame(Scheme::Light, $violations[0]->scheme);
        self::assertSame(Scheme::Dark, $violations[1]->scheme);
        self::assertSame(3.0, $violations[0]->minimum);
        self::assertNotNull($violations[0]->ratio);
        self::assertLessThan(3.0, $violations[0]->ratio);
        self::assertMatchesRegularExpression('/^--phil-color-badge on --phil-color-surface \(light\): 1\.\d\d:1, below 3\.0:1$/', (string) $violations[0]);
    }

    public function testATokenThatDoesNotResolveIsAViolation(): void
    {
        $violations = new ContrastRule()->check(TokenSheet::fromCss(':root { --phil-color-text: oklch(0 0 0); }'));

        self::assertCount(26, $violations);
        self::assertEquals(
            new Violation('--phil-color-text', '--phil-color-surface', Scheme::Light, null, 4.5, '--phil-color-surface does not resolve to an OKLCH colour'),
            $violations[0],
        );
        self::assertSame('--phil-color-text-muted on --phil-color-surface (light): --phil-color-text-muted does not resolve to an OKLCH colour', (string) $violations[4]);
    }

    public function testTonesFarApartMustContrast(): void
    {
        // Two greys 50 apart pass; tones whose numbers lie about their lightness do not.
        $violations = new ContrastRule()->check(TokenSheet::fromCss(self::PAGE.':root {
            --phil-gray-10: oklch(0.10 0 0);
            --phil-gray-60: oklch(0.60 0 0);
            --phil-fake-50: oklch(0.25 0 0);
            --phil-fake-90: oklch(0.42 0 0);
        }'));

        self::assertSame(
            ['--phil-gray-10 on --phil-fake-50', '--phil-gray-10 on --phil-fake-90', '--phil-fake-50 on --phil-fake-90'],
            array_map(static fn (Violation $violation): string => $violation->foreground.' on '.$violation->background, $violations),
        );
        self::assertSame([3.0, 4.5, 3.0], array_map(static fn (Violation $violation): float => $violation->minimum, $violations));
        self::assertMatchesRegularExpression('/^\d\.\d\d:1, below the 3\.0:1 two tones 40 apart must reach$/', $violations[0]->reason);
        self::assertMatchesRegularExpression('/^\d\.\d\d:1, below the 4\.5:1 two tones 80 apart must reach$/', $violations[1]->reason);
        self::assertNull($violations[1]->scheme);
        self::assertSame('--phil-gray-10 on --phil-fake-90: '.$violations[1]->reason, (string) $violations[1]);
    }

    public function testTonesFiftyApartMustReachTheTextContrast(): void
    {
        $violations = new ContrastRule()->check(TokenSheet::fromCss(self::PAGE.':root {
            --phil-fake-10: oklch(0.30 0 0);
            --phil-gray-60: oklch(0.60 0 0);
        }'));

        self::assertCount(1, $violations);
        self::assertSame(4.5, $violations[0]->minimum);
        self::assertGreaterThan(3.0, $violations[0]->ratio);
    }

    public function testAPairExactlyAtItsMinimumIsNoViolation(): void
    {
        $css = self::PAGE.':root { --phil-color-badge: light-dark(oklch(0.3 0 0), oklch(0.8 0 0)); }';
        $sheet = TokenSheet::fromCss($css);
        $badge = $sheet->resolve('--phil-color-badge', Scheme::Light);
        $surface = $sheet->resolve('--phil-color-surface', Scheme::Light);
        self::assertNotNull($badge);
        self::assertNotNull($surface);
        $exact = $badge->contrastWith($surface);

        $violations = new ContrastRule([new Pair('--phil-color-badge', '--phil-color-surface', $exact)])->check($sheet);

        self::assertSame([], array_filter($violations, static fn (Violation $violation): bool => Scheme::Light === $violation->scheme));
    }

    public function testATwoToneGapBelowFortyIsNotChecked(): void
    {
        self::assertSame([], new ContrastRule()->check(TokenSheet::fromCss(self::PAGE.':root {
            --phil-gray-20: oklch(0.5 0 0);
            --phil-gray-59: oklch(0.5 0 0);
        }')));
    }

    public function testAToneOutsideTheGamutIsAViolation(): void
    {
        $violations = new ContrastRule()->check(TokenSheet::fromCss(self::PAGE.':root { --phil-hot-50: oklch(0.5 0.4 30); }'));

        self::assertEquals([new Violation('--phil-hot-50', '--phil-hot-50', null, null, 0.0, 'outside the sRGB gamut')], $violations);
    }
}
