<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Token;

use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Exception\InvalidColor;
use Phil\UxCss\Exception\UnreadableSheet;
use Phil\UxCss\Token\Scheme;
use Phil\UxCss\Token\TokenSheet;
use Phil\UxCss\Token\Tone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenSheet::class)]
final class TokenSheetTest extends TestCase
{
    public function testTheLaterDeclarationWinsAndCommentsAreSkipped(): void
    {
        $sheet = TokenSheet::fromCss(
            ":root { --phil-a: 1; /* --phil-b: 2; */ --phil-c: oklch(0.5 0 0) }\n[data-x] { --phil-a: 3; }",
            ':root { --phil-c: var(--phil-a) ; }',
        );

        self::assertSame(['--phil-a', '--phil-c'], $sheet->names());
        self::assertSame('3', $sheet->value('--phil-a'));
        self::assertSame('var(--phil-a)', $sheet->value('--phil-c'));
        self::assertTrue($sheet->has('--phil-c'));
        self::assertFalse($sheet->has('--phil-b'));
        self::assertNull($sheet->value('--phil-b'));
    }

    public function testItReadsFiles(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-ux-css');
        self::assertIsString($path);
        file_put_contents($path, ':root { --phil-a: 1; }');

        try {
            self::assertSame('1', TokenSheet::fromFiles($path)->value('--phil-a'));
        } finally {
            unlink($path);
        }
    }

    public function testADirectoryIsUnreadable(): void
    {
        $this->expectException(UnreadableSheet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The stylesheet "%s" cannot be read.', __DIR__));

        TokenSheet::fromFiles(__DIR__);
    }

    public function testAMissingFileIsUnreadable(): void
    {
        $this->expectException(UnreadableSheet::class);
        $this->expectExceptionMessageIsOrContains('The stylesheet "/nowhere/tokens.css" cannot be read.');

        TokenSheet::fromFiles('/nowhere/tokens.css');
    }

    public function testTheTonesAreThePaletteProperties(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-brand-40: oklch(0.40 0.058 195);
            --phil-brand-4: oklch(0.04 0 0);
            --phil-text-2xl: 2rem;
            --phil-brand-50: var(--phil-brand-40);
            --phil-gray-05: OKLCH(0.05 0 0);
        }');

        self::assertEquals([
            new Tone('brand', 40, new Oklch(0.40, 0.058, 195.0)),
            new Tone('gray', 5, new Oklch(0.05, 0.0, 0.0)),
        ], $sheet->tones());
        self::assertSame('--phil-gray-05', $sheet->tones()[1]->name());
    }

    public function testATonesNameIsTheWholeName(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-a--phil-brand-40: oklch(0.40 0.058 195);
            --phil-brand-401: oklch(0.40 0.058 195);
        }');

        self::assertSame([], $sheet->tones());
    }

    public function testARolesNameIsTheWholeName(): void
    {
        $sheet = TokenSheet::fromCss(':root { --phil-a--phil-color-brand-fill-quiet: red; --phil-color-call-fill-quieter: red; }');

        self::assertSame([], $sheet->roles());
    }

    public function testTheRolesAreThoseWithAQuietFill(): void
    {
        $sheet = TokenSheet::fromCss(':root { --phil-color-brand-fill-quiet: red; --phil-color-brand-fill-loud: red; --phil-color-call-to-action-fill-quiet: red; --phil-color-text: red; }');

        self::assertSame(['brand', 'call-to-action'], $sheet->roles());
    }

    public function testItResolvesVariablesAndLightDark(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-white: oklch(1 0 0);
            --phil-black: oklch(0 0 0);
            --phil-surface: light-dark(var(--phil-white), var(--phil-black));
            --phil-alias: var(--phil-surface);
            --phil-fallback: var(--phil-missing, light-dark(var(--phil-black), oklch(0.5 0 0)));
            --phil-direct: light-dark(oklch(0.2 0.1 30), oklch(0.8 0.1 30));
        }');

        self::assertEquals(new Oklch(1.0, 0.0, 0.0), $sheet->resolve('--phil-surface', Scheme::Light));
        self::assertEquals(new Oklch(0.0, 0.0, 0.0), $sheet->resolve('--phil-surface', Scheme::Dark));
        self::assertEquals(new Oklch(0.0, 0.0, 0.0), $sheet->resolve('--phil-alias', Scheme::Dark));
        self::assertEquals(new Oklch(0.0, 0.0, 0.0), $sheet->resolve('--phil-fallback', Scheme::Light));
        self::assertEquals(new Oklch(0.5, 0.0, 0.0), $sheet->resolve('--phil-fallback', Scheme::Dark));
        self::assertEquals(new Oklch(0.8, 0.1, 30.0), $sheet->resolve('--phil-direct', Scheme::Dark));
    }

    public function testTheFunctionsAreReadWhateverTheirCaseAndLines(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-white: oklch(1 0 0);
            --phil-black: oklch(0 0 0);
            --phil-upper: OKLCH(0.5 0 0);
            --phil-light-dark: LIGHT-DARK(var(--phil-white),
                var(--phil-black));
            --phil-var: VAR(--phil-missing,
                oklch(0.5 0 0));
            --phil-spaced: var( --phil-white );
            --phil-spaced-fallback: var( --phil-white , oklch(0.5 0 0));
            --phil-declared-first: var(--phil-white, oklch(0.5 0 0));
        }');

        self::assertEquals(new Oklch(0.5, 0.0, 0.0), $sheet->resolve('--phil-upper', Scheme::Light));
        self::assertEquals(new Oklch(0.0, 0.0, 0.0), $sheet->resolve('--phil-light-dark', Scheme::Dark));
        self::assertEquals(new Oklch(0.5, 0.0, 0.0), $sheet->resolve('--phil-var', Scheme::Light));
        self::assertEquals(new Oklch(1.0, 0.0, 0.0), $sheet->resolve('--phil-spaced', Scheme::Light));
        self::assertEquals(new Oklch(1.0, 0.0, 0.0), $sheet->resolve('--phil-spaced-fallback', Scheme::Light));
        self::assertEquals(new Oklch(1.0, 0.0, 0.0), $sheet->resolve('--phil-declared-first', Scheme::Light), 'the fallback serves only when the property is missing');
    }

    public function testAFunctionIsTheWholeValue(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-white: oklch(1 0 0);
            --phil-black: oklch(0 0 0);
            --phil-before-var: calc(1) var(--phil-white);
            --phil-after-var: var(--phil-white) solid;
            --phil-after-light-dark: light-dark(var(--phil-white), var(--phil-black)) solid;
            --phil-two-fallbacks: var(--phil-missing, var(--phil-white), calc(1));
        }');

        self::assertNull($sheet->resolve('--phil-before-var', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-after-var', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-after-light-dark', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-two-fallbacks', Scheme::Light), 'the fallback of var() is everything after its first comma');
    }

    public function testNestedLightDarkCountTowardsTheLimit(): void
    {
        $sheet = TokenSheet::fromCss(\sprintf(':root { --phil-sixteen: %s; --phil-seventeen: %s; }', $this->nested(16), $this->nested(17)));

        self::assertEquals(new Oklch(0.5, 0.0, 0.0), $sheet->resolve('--phil-sixteen', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-seventeen', Scheme::Light));
    }

    public function testWhatDoesNotEndOnAColourResolvesToNothing(): void
    {
        $sheet = TokenSheet::fromCss(':root {
            --phil-size: 1rem;
            --phil-loop: var(--phil-loop);
            --phil-dangling: var(--phil-missing);
            --phil-odd: light-dark(oklch(0 0 0));
            --phil-deep-0: oklch(0.5 0 0);
        }'.$this->chain(17));

        self::assertNull($sheet->resolve('--phil-size', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-loop', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-dangling', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-odd', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-missing', Scheme::Light));
        self::assertNull($sheet->resolve('--phil-deep-17', Scheme::Light), 'a chain longer than the limit is taken for a cycle');
        self::assertEquals(new Oklch(0.5, 0.0, 0.0), $sheet->resolve('--phil-deep-16', Scheme::Light));
    }

    public function testAMalformedColourIsReported(): void
    {
        $this->expectException(InvalidColor::class);

        TokenSheet::fromCss(':root { --phil-x: oklch(0.5, 0, 0); }')->resolve('--phil-x', Scheme::Light);
    }

    private function nested(int $depth): string
    {
        return str_repeat('light-dark(', $depth).'oklch(0.5 0 0)'.str_repeat(', oklch(0 0 0))', $depth);
    }

    private function chain(int $length): string
    {
        $css = ':root {';

        for ($i = 1; $i <= $length; ++$i) {
            $css .= \sprintf(' --phil-deep-%d: var(--phil-deep-%d);', $i, $i - 1);
        }

        return $css.' }';
    }
}
