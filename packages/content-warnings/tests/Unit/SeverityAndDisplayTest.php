<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
#[CoversClass(Display::class)]
final class SeverityAndDisplayTest extends TestCase
{
    public function testSeveritiesAreOrderedFromMildToSevere(): void
    {
        self::assertTrue(Severity::Mild->isAtLeast(Severity::Mild));
        self::assertFalse(Severity::Mild->isAtLeast(Severity::Moderate));
        self::assertTrue(Severity::Moderate->isAtLeast(Severity::Mild));
        self::assertFalse(Severity::Moderate->isAtLeast(Severity::Severe));
        self::assertTrue(Severity::Severe->isAtLeast(Severity::Moderate));
        self::assertTrue(Severity::Severe->isAtLeast(Severity::Severe));
    }

    public function testDisplaysAreOrderedFromShowToHide(): void
    {
        self::assertFalse(Display::Show->isStricterThan(Display::Show));
        self::assertTrue(Display::Fold->isStricterThan(Display::Show));
        self::assertFalse(Display::Fold->isStricterThan(Display::Fold));
        self::assertFalse(Display::Fold->isStricterThan(Display::Hide));
        self::assertTrue(Display::Hide->isStricterThan(Display::Fold));
    }

    public function testTheStrictestDisplayWins(): void
    {
        self::assertSame(Display::Show, Display::strictest());
        self::assertSame(Display::Show, Display::strictest(Display::Show));
        self::assertSame(Display::Fold, Display::strictest(Display::Show, Display::Fold, Display::Show));
        self::assertSame(Display::Hide, Display::strictest(Display::Hide, Display::Fold));
        self::assertSame(Display::Hide, Display::strictest(Display::Fold, Display::Hide, Display::Show));
    }
}
