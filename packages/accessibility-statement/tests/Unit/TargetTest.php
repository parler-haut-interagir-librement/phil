<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
#[CoversClass(ConformanceTarget::class)]
#[CoversClass(ConformanceStatus::class)]
#[CoversClass(NonConformityReason::class)]
final class TargetTest extends TestCase
{
    public function testEachVersionOfWcagHasItsTitleAndRecommendation(): void
    {
        self::assertSame(['WCAG 2.0', 'WCAG 2.1', 'WCAG 2.2'], array_map(static fn (Standard $standard): string => $standard->title(), Standard::cases()));
        self::assertSame(
            ['https://www.w3.org/TR/WCAG20/', 'https://www.w3.org/TR/WCAG21/', 'https://www.w3.org/TR/WCAG22/'],
            array_map(static fn (Standard $standard): string => $standard->url(), Standard::cases()),
        );
    }

    public function testEachVersionIncludesTheCriteriaItDefines(): void
    {
        self::assertTrue(Standard::Wcag22->includes(SuccessCriterion::TargetSizeMinimum));
        self::assertFalse(Standard::Wcag21->includes(SuccessCriterion::TargetSizeMinimum));
        self::assertTrue(Standard::Wcag21->includes(SuccessCriterion::Reflow));
        self::assertFalse(Standard::Wcag20->includes(SuccessCriterion::Reflow));
        self::assertFalse(Standard::Wcag20->includes(SuccessCriterion::ConsistentHelp));
        self::assertTrue(Standard::Wcag20->includes(SuccessCriterion::ContrastMinimum));
        self::assertTrue(Standard::Wcag21->includes(SuccessCriterion::ContrastMinimum));
    }

    public function testATargetCoversTheCriteriaOfItsVersionUpToItsLevel(): void
    {
        $counts = [];

        foreach (Standard::cases() as $standard) {
            foreach (ConformanceLevel::cases() as $level) {
                $counts[$standard->title().' '.$level->value] = \count(new ConformanceTarget($standard, $level)->criteria());
            }
        }

        // The numbers of criteria the W3C gives: 4.1.1 Parsing, gone from WCAG 2.2, is counted in neither.
        self::assertSame([
            'WCAG 2.0 A' => 24, 'WCAG 2.0 AA' => 37, 'WCAG 2.0 AAA' => 60,
            'WCAG 2.1 A' => 29, 'WCAG 2.1 AA' => 49, 'WCAG 2.1 AAA' => 77,
            'WCAG 2.2 A' => 31, 'WCAG 2.2 AA' => 55, 'WCAG 2.2 AAA' => 86,
        ], $counts);

        self::assertSame(range(0, 36), array_keys(new ConformanceTarget(Standard::Wcag20)->criteria()));

        $target = new ConformanceTarget();
        self::assertSame('WCAG 2.2 AA', $target->describe());
        self::assertSame(SuccessCriterion::NonTextContent, $target->criteria()[0]);
        self::assertTrue($target->covers(SuccessCriterion::ContrastMinimum));
        self::assertFalse($target->covers(SuccessCriterion::ContrastEnhanced));
        self::assertFalse(new ConformanceTarget(level: ConformanceLevel::A)->covers(SuccessCriterion::ContrastMinimum));
        self::assertSame('WCAG 2.1 AAA', new ConformanceTarget(Standard::Wcag21, ConformanceLevel::AAA)->describe());
    }

    public function testTheStatusFollowsTheShareOfCriteriaNotMet(): void
    {
        $target = new ConformanceTarget(Standard::Wcag20, ConformanceLevel::A);
        $criteria = $target->criteria();
        $failing = static fn (int $count): array => array_map(static fn (SuccessCriterion $criterion): NonConformity => new NonConformity($criterion), \array_slice($criteria, 0, $count));

        self::assertSame(ConformanceStatus::Full, ConformanceStatus::derive($target, []));
        self::assertSame(ConformanceStatus::Full, ConformanceStatus::derive($target, [new NonConformity(SuccessCriterion::NonTextContent, NonConformityReason::OutOfScope)]));
        self::assertSame(ConformanceStatus::Partial, ConformanceStatus::derive($target, $failing(1)));
        self::assertSame(ConformanceStatus::Partial, ConformanceStatus::derive($target, [...$failing(1), ...$failing(1)]));
        self::assertSame(ConformanceStatus::Partial, ConformanceStatus::derive($target, $failing(12)));
        self::assertSame(ConformanceStatus::NonConformant, ConformanceStatus::derive($target, $failing(13)));
    }

    public function testOnlyContentOutsideTheScopeLeavesConformanceWhole(): void
    {
        self::assertTrue(NonConformityReason::NonCompliance->countsAgainstConformance());
        self::assertTrue(NonConformityReason::DisproportionateBurden->countsAgainstConformance());
        self::assertFalse(NonConformityReason::OutOfScope->countsAgainstConformance());
    }
}
