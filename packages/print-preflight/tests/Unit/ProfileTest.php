<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Closure;
use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Exception\InvalidProfile;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintProfile::class)]
#[CoversClass(InvalidProfile::class)]
final class ProfileTest extends TestCase
{
    public function testTheDefaultsSuitMostPrinters(): void
    {
        $profile = new PrintProfile();

        self::assertSame([], $profile->trimSizes);
        self::assertTrue($profile->uniformTrimSize);
        self::assertEqualsWithDelta(Length::mm(3)->points, $profile->bleed->points, 1e-9);
        self::assertEqualsWithDelta(Length::mm(5)->points, $profile->safetyMargin->points, 1e-9);
        self::assertSame(150.0, $profile->minimumResolution);
        self::assertSame(300.0, $profile->targetResolution);
        self::assertNull($profile->maximumResolution);
        self::assertSame(1, $profile->minimumPages);
        self::assertNull($profile->maximumPages);
        self::assertSame(1, $profile->pageMultiple);
        self::assertSame(ColourWorkflow::Cmyk, $profile->colourWorkflow);
        self::assertFalse($profile->allowSpotColours);
        self::assertSame(300.0, $profile->maximumInkCoverage);
        self::assertSame(0.25, $profile->minimumLineWidth->points);
        self::assertTrue($profile->allowOverprint);
        self::assertTrue($profile->allowTransparency);
    }

    public function testLimitsMayMeet(): void
    {
        $profile = new PrintProfile(bleed: Length::pt(0), safetyMargin: Length::pt(0), minimumResolution: 300, targetResolution: 300, maximumResolution: 300, minimumPages: 4, maximumPages: 4, pageMultiple: 1);

        self::assertSame(0.0, $profile->bleed->points);
        self::assertSame(0.0, $profile->safetyMargin->points);
        self::assertSame(4, $profile->maximumPages);
        self::assertSame(300.0, $profile->maximumResolution);
        self::assertSame(0.0, new PrintProfile(minimumLineWidth: Length::pt(0))->minimumLineWidth->points);
        self::assertSame(0.1, new PrintProfile(maximumInkCoverage: 0.1)->maximumInkCoverage);
    }

    /**
     * @return iterable<string, array{Closure(): PrintProfile, string}>
     */
    public static function invalidProfiles(): iterable
    {
        yield 'negative bleed' => [static fn (): PrintProfile => new PrintProfile(bleed: Length::pt(-1)), 'The bleed and the safety margin cannot be negative.'];
        yield 'negative margin' => [static fn (): PrintProfile => new PrintProfile(safetyMargin: Length::pt(-1)), 'The bleed and the safety margin cannot be negative.'];
        yield 'no minimum resolution' => [static fn (): PrintProfile => new PrintProfile(minimumResolution: 0), 'The minimum resolution must be positive and the target at least the minimum; 0 and 300 ppi given.'];
        yield 'target below minimum' => [static fn (): PrintProfile => new PrintProfile(minimumResolution: 200, targetResolution: 199.5), 'The minimum resolution must be positive and the target at least the minimum; 200 and 199.5 ppi given.'];
        yield 'maximum below target' => [static fn (): PrintProfile => new PrintProfile(maximumResolution: 299), 'The maximum resolution cannot be below the target; 299 and 300 ppi given.'];
        yield 'no minimum page' => [static fn (): PrintProfile => new PrintProfile(minimumPages: 0), 'The minimum page count must be at least 1 and the maximum at least the minimum; 0 and none given.'];
        yield 'maximum below minimum' => [static fn (): PrintProfile => new PrintProfile(minimumPages: 2, maximumPages: 1), 'The minimum page count must be at least 1 and the maximum at least the minimum; 2 and 1 given.'];
        yield 'no multiple' => [static fn (): PrintProfile => new PrintProfile(pageMultiple: 0), 'The page count must be a multiple of at least 1; 0 given.'];
        yield 'no ink coverage' => [static fn (): PrintProfile => new PrintProfile(maximumInkCoverage: 0), 'The maximum ink coverage must be positive; 0 % given.'];
        yield 'negative line width' => [static fn (): PrintProfile => new PrintProfile(minimumLineWidth: Length::pt(-0.1)), 'The minimum line width cannot be negative.'];
    }

    /**
     * @param Closure(): PrintProfile $profile
     */
    #[DataProvider('invalidProfiles')]
    public function testContradictoryRulesAreRefused(Closure $profile, string $message): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains($message);

        $profile();
    }

    public function testSeveritiesAndIgnoredRulesAreLookedUpByCode(): void
    {
        $profile = new PrintProfile(severities: [Rule::PageCountMultiple->value => Severity::Warning], ignore: [Rule::ColourRgbInCmyk->value]);
        $multiple = Finding::of(Rule::PageCountMultiple, 'x');
        $empty = Finding::of(Rule::PageCountEmpty, 'x');

        self::assertSame(Severity::Warning, $profile->severityOf($multiple));
        self::assertSame(Severity::Error, $profile->severityOf($empty));
        self::assertTrue($profile->ignores(Finding::of(Rule::ColourRgbInCmyk, 'x')));
        self::assertFalse($profile->ignores($multiple));
    }

    public function testTrimSizesAreAcceptedFromTheList(): void
    {
        self::assertTrue(new PrintProfile()->accepts(Size::mm(1, 1)));

        $profile = new PrintProfile(trimSizes: [Size::a5(), Size::mm(152.4, 228.6)]);

        self::assertTrue($profile->accepts(Size::a5()));
        self::assertTrue($profile->accepts(Size::in(6, 9)));
        self::assertFalse($profile->accepts(Size::a4()));
    }
}
