<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Stylesheet;

use Phil\UxCss\Contrast\ContrastRule;
use Phil\UxCss\Contrast\Violation;
use Phil\UxCss\Token\TokenSheet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The token files of the package keep their own rules: the contrast rule, the numbering of the palette, the role grid
 * in both schemes and the t-shirt scales.
 */
#[CoversNothing]
final class ThemeTest extends TestCase
{
    private const string TOKENS = __DIR__.'/../../../css/tokens/';

    private const array ROLES = ['brand', 'neutral', 'danger', 'success', 'warning', 'info'];

    private const array PARTS = ['fill-quiet', 'fill-loud', 'border-quiet', 'border-loud', 'on-quiet', 'on-loud'];

    public function testTheThemeKeepsTheContrastRule(): void
    {
        $violations = new ContrastRule()->check($this->sheet());

        self::assertSame([], array_map(static fn (Violation $violation): string => (string) $violation, $violations));
    }

    public function testEachToneIsNumberedByItsLightness(): void
    {
        $tones = $this->sheet()->tones();

        self::assertCount(78, $tones, 'seven hues of eleven tones, and the near-white of the neutrals');

        foreach ($tones as $tone) {
            self::assertSame($tone->number, (int) round($tone->color->lightness * 100), $tone->name());
        }
    }

    public function testEachRoleHasTheWholeGridInBothSchemes(): void
    {
        $sheet = $this->sheet();

        self::assertSame(self::ROLES, $sheet->roles());

        foreach (self::ROLES as $role) {
            foreach (self::PARTS as $part) {
                $name = \sprintf('--phil-color-%s-%s', $role, $part);
                self::assertMatchesRegularExpression('/^light-dark\(var\(--phil-[a-z]+-\d{2}\), var\(--phil-[a-z]+-\d{2}\)\)$/', (string) $sheet->value($name), $name);
            }
        }
    }

    public function testTheColoursAndTheDimensionsAreSeparateLayers(): void
    {
        $colors = TokenSheet::fromFiles(self::TOKENS.'colors.css');
        $dimensions = TokenSheet::fromFiles(self::TOKENS.'dimensions.css');

        foreach ($colors->names() as $name) {
            self::assertStringStartsWith('--phil-color-', $name);
        }

        foreach ($dimensions->names() as $name) {
            self::assertStringStartsNotWith('--phil-color-', $name);
            self::assertStringNotContainsString('oklch(', (string) $dimensions->value($name), $name);
        }
    }

    public function testTheScalesAreInTShirtSizesWithAGlobalMultiplier(): void
    {
        $sheet = TokenSheet::fromFiles(self::TOKENS.'dimensions.css');

        foreach (['3xs', '2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'] as $size) {
            self::assertTrue($sheet->has('--phil-space-'.$size), 'space '.$size);
        }

        foreach (['xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'] as $size) {
            self::assertTrue($sheet->has('--phil-text-'.$size), 'text '.$size);
        }

        foreach (['none', 'sm', 'md', 'lg', 'xl', 'full'] as $size) {
            self::assertTrue($sheet->has('--phil-radius-'.$size), 'radius '.$size);
        }

        foreach (['sm', 'md', 'lg'] as $size) {
            self::assertTrue($sheet->has('--phil-shadow-'.$size), 'shadow '.$size);
        }

        self::assertStringContainsString('var(--phil-scale-space)', (string) $sheet->value('--phil-space-unit'));
        self::assertStringContainsString('var(--phil-scale-text)', (string) $sheet->value('--phil-text-md'));
        self::assertStringContainsString('var(--phil-scale-radius)', (string) $sheet->value('--phil-radius-unit'));
    }

    public function testTheControlsAreTouchTargets(): void
    {
        $sheet = TokenSheet::fromFiles(self::TOKENS.'dimensions.css');

        self::assertSame('2.75rem', $sheet->value('--phil-control-md'), '44 CSS pixels');
        self::assertSame('1.5rem', $sheet->value('--phil-check-size'), '24 CSS pixels, WCAG 2.2 success criterion 2.5.8');
    }

    private function sheet(): TokenSheet
    {
        return TokenSheet::fromFiles(self::TOKENS.'palette.css', self::TOKENS.'colors.css', self::TOKENS.'dimensions.css');
    }
}
