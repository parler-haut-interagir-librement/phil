<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\Stylesheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stylesheet::class)]
final class StylesheetTest extends TestCase
{
    public function testThePrintSheetFollowsGcpm(): void
    {
        $css = $this->read(Stylesheet::Print);

        self::assertStringContainsString('float: footnote', $css);
        self::assertStringContainsString('::footnote-call', $css);
        self::assertStringContainsString('::footnote-marker', $css);
        self::assertStringContainsString('@footnote', $css);
        self::assertStringContainsString('attr(data-note-label)', $css);
    }

    public function testThePerPageSheetHandsTheNumbersToTheEngine(): void
    {
        $css = $this->read(Stylesheet::PrintPerPage);

        self::assertStringContainsString('counter-reset: footnote', $css);
        self::assertStringContainsString('content: counter(footnote);', $css);
        self::assertStringContainsString('content: counter(footnote) ". ";', $css);
    }

    public function testTheTextOfASheetIsThatOfItsFile(): void
    {
        foreach (Stylesheet::cases() as $sheet) {
            self::assertSame($this->read($sheet), $sheet->contents());
        }
    }

    public function testTheScreenSheetHoldsTheMarginColumnAndTheNarrowToggle(): void
    {
        $css = $this->read(Stylesheet::Screen);

        self::assertStringContainsString('@media (min-width: 72rem)', $css);
        self::assertStringContainsString('.isSidenoteSwitch:checked ~ .isSidenote', $css);
        self::assertStringContainsString('.isSidenoteSwitch:focus-visible + .isSidenoteToggle', $css);
        self::assertStringContainsString('@layer phil.components', $css);
    }

    public function testAnAnimationStandsOnlyWhereMotionIsWelcome(): void
    {
        $css = $this->read(Stylesheet::Screen);
        $guard = strpos($css, 'prefers-reduced-motion: no-preference');

        self::assertNotFalse($guard);
        self::assertSame(1, substr_count($css, 'animation:'));
        self::assertGreaterThan($guard, strpos($css, 'animation:'));
        self::assertStringNotContainsString('transition', $css);
        self::assertStringNotContainsString('<script', $css);
    }

    private function read(Stylesheet $sheet): string
    {
        $css = file_get_contents($sheet->path());
        self::assertIsString($css);

        return $css;
    }
}
