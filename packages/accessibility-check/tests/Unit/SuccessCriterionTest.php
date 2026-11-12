<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\ContrastLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuccessCriterion::class)]
#[CoversClass(ContrastLevel::class)]
final class SuccessCriterionTest extends TestCase
{
    public function testEveryCriterionOfWcag22IsKnownWithoutParsing(): void
    {
        self::assertCount(86, SuccessCriterion::cases());
        self::assertNull(SuccessCriterion::tryFrom('4.1.1'));
    }

    public function testACriterionHasItsTitleAndLevel(): void
    {
        self::assertSame('Non-text Content', SuccessCriterion::NonTextContent->title());
        self::assertSame(ConformanceLevel::A, SuccessCriterion::NonTextContent->level());
        self::assertSame('Contrast (Minimum)', SuccessCriterion::from('1.4.3')->title());
        self::assertSame(ConformanceLevel::AA, SuccessCriterion::ContrastMinimum->level());
        self::assertSame(ConformanceLevel::AAA, SuccessCriterion::ContrastEnhanced->level());
        self::assertSame('Status Messages', SuccessCriterion::StatusMessages->title());
    }

    public function testTheUnderstandingPageIsNamedAfterTheTitle(): void
    {
        self::assertSame('https://www.w3.org/WAI/WCAG22/Understanding/name-role-value.html', SuccessCriterion::NameRoleValue->understandingUrl());
        self::assertSame('https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html', SuccessCriterion::ContrastMinimum->understandingUrl());
        self::assertSame('https://www.w3.org/WAI/WCAG22/Understanding/audio-only-and-video-only-prerecorded.html', SuccessCriterion::AudioOnlyAndVideoOnlyPrerecorded->understandingUrl());
    }

    public function testEveryCriterionHasATitleAndAPage(): void
    {
        foreach (SuccessCriterion::cases() as $criterion) {
            self::assertNotSame('', $criterion->title());
            self::assertMatchesRegularExpression('#^https://www\.w3\.org/WAI/WCAG22/Understanding/[a-z-]+\.html$#D', $criterion->understandingUrl());
        }
    }

    public function testTheContrastLevelsRequireTheirRatios(): void
    {
        self::assertSame(4.5, ContrastLevel::AA->minimumRatio(false));
        self::assertSame(3.0, ContrastLevel::AA->minimumRatio(true));
        self::assertSame(7.0, ContrastLevel::AAA->minimumRatio(false));
        self::assertSame(4.5, ContrastLevel::AAA->minimumRatio(true));
        self::assertSame(SuccessCriterion::ContrastMinimum, ContrastLevel::AA->criterion());
        self::assertSame(SuccessCriterion::ContrastEnhanced, ContrastLevel::AAA->criterion());
    }
}
