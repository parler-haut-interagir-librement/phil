<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Check\Hairlines;
use Phil\PrintPreflight\Check\InkCoverage;
use Phil\PrintPreflight\Check\Overprint;
use Phil\PrintPreflight\Check\Transparency;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InkCoverage::class)]
#[CoversClass(Overprint::class)]
#[CoversClass(Hairlines::class)]
#[CoversClass(Transparency::class)]
final class InkAndLinesTest extends TestCase
{
    public function testInkCoverageAboveTheMaximumIsAnError(): void
    {
        $document = new Document([
            new Page(Size::a5(), images: [
                $this->image('photo', 320.2),
                $this->image('portrait', 300),
                $this->image('scan', null),
            ], boxes: [
                $this->box('band', inkCoverage: 340),
                $this->box('rule', inkCoverage: 300),
                $this->box('text'),
            ]),
            new Page(Size::a5(), boxes: [$this->box('rich-black', inkCoverage: 300.01)]),
        ]);

        $findings = new InkCoverage()->check($document, new PrintProfile());

        self::assertSame([
            ['error', 'ink.image-above-maximum', 'The image "photo" on page 1 reaches 321 % of total ink coverage, above the 300 % the printer accepts.', 1],
            ['error', 'ink.flat-above-maximum', '"band" on page 1 prints a flat colour of 340 % total ink coverage, above the 300 % the printer accepts.', 1],
            ['error', 'ink.flat-above-maximum', '"rich-black" on page 2 prints a flat colour of 301 % total ink coverage, above the 300 % the printer accepts.', 2],
        ], PageChecksTest::describe($findings));
        self::assertSame(['photo', 'band', 'rich-black'], array_map(static fn (Finding $finding): ?string => $finding->subject, $findings));
        self::assertSame(['coverage' => 321, 'maximum' => 300.0], $findings[0]->parameters);
        self::assertSame(['coverage' => 340, 'maximum' => 300.0], $findings[1]->parameters);
    }

    public function testTheMaximumComesFromTheProfile(): void
    {
        $document = new Document([new Page(Size::a5(), images: [$this->image('photo', 280)], boxes: [$this->box('band', inkCoverage: 260.5)])]);

        self::assertSame([
            ['error', 'ink.image-above-maximum', 'The image "photo" on page 1 reaches 280 % of total ink coverage, above the 260.5 % the printer accepts.', 1],
        ], PageChecksTest::describe(new InkCoverage()->check($document, new PrintProfile(maximumInkCoverage: 260.5))));
        self::assertCount(2, new InkCoverage()->check($document, new PrintProfile(maximumInkCoverage: 260)));
    }

    public function testInkCoverageIsLeftToThePrinterInAnRgbWorkflow(): void
    {
        $document = new Document([new Page(Size::a5(), images: [$this->image('photo', 400)], boxes: [$this->box('band', inkCoverage: 400)])]);

        self::assertSame([], new InkCoverage()->check($document, new PrintProfile(colourWorkflow: ColourWorkflow::Rgb)));
    }

    public function testWhiteSetToOverprintVanishes(): void
    {
        $document = new Document([new Page(Size::a5(), boxes: [
            $this->box('white-title', inkCoverage: 0, overprint: true),
            $this->box('almost-white', inkCoverage: 0.49, overprint: true),
            $this->box('tint', inkCoverage: 0.5, overprint: true),
            $this->box('black-text', inkCoverage: 100, overprint: true),
            $this->box('unknown', overprint: true),
            $this->box('white-knockout', inkCoverage: 0),
        ])]);

        self::assertSame([
            ['error', 'overprint.white', '"white-title" on page 1 is white and set to overprint: it will not print at all.', 1],
            ['error', 'overprint.white', '"almost-white" on page 1 is white and set to overprint: it will not print at all.', 1],
        ], PageChecksTest::describe(new Overprint()->check($document, new PrintProfile())));
    }

    public function testOverprintIsAWarningWhereThePrinterIgnoresIt(): void
    {
        $document = new Document([new Page(Size::a5(), boxes: [
            $this->box('white-title', inkCoverage: 0, overprint: true),
            $this->box('black-text', overprint: true),
            $this->box('photo-frame'),
        ])]);

        $findings = new Overprint()->check($document, new PrintProfile(allowOverprint: false));

        self::assertSame([
            ['warning', 'overprint.not-allowed', '"white-title" on page 1 is set to overprint, which the printer ignores: it will knock out what lies beneath.', 1],
            ['warning', 'overprint.not-allowed', '"black-text" on page 1 is set to overprint, which the printer ignores: it will knock out what lies beneath.', 1],
        ], PageChecksTest::describe($findings));
        self::assertSame('black-text', $findings[1]->subject);
    }

    public function testLinesThinnerThanTheMinimumAreHairlines(): void
    {
        $document = new Document([
            new Page(Size::a5(), boxes: [
                $this->box('hairline', lineWidth: Length::pt(0)),
                $this->box('thin-rule', lineWidth: Length::pt(0.2)),
                $this->box('rule', lineWidth: Length::pt(0.25)),
                $this->box('text'),
            ]),
            new Page(Size::a5(), boxes: [$this->box('border', lineWidth: Length::mm(0.05))]),
        ]);

        $findings = new Hairlines()->check($document, new PrintProfile());

        self::assertSame([
            ['warning', 'line.hairline', '"hairline" on page 1 draws lines 0 pt thick, thinner than the 0.25 pt the printer reproduces; they may break up or vanish.', 1],
            ['warning', 'line.hairline', '"thin-rule" on page 1 draws lines 0.2 pt thick, thinner than the 0.25 pt the printer reproduces; they may break up or vanish.', 1],
            ['warning', 'line.hairline', '"border" on page 2 draws lines 0.14 pt thick, thinner than the 0.25 pt the printer reproduces; they may break up or vanish.', 2],
        ], PageChecksTest::describe($findings));
        self::assertSame(['width' => '0.2 pt', 'minimum' => '0.25 pt'], $findings[1]->parameters);
        self::assertSame('thin-rule', $findings[1]->subject);
        self::assertCount(1, new Hairlines()->check($document, new PrintProfile(minimumLineWidth: Length::pt(0.1))));
    }

    public function testTransparencyIsReportedWhereThePrinterDoesNotAcceptIt(): void
    {
        $document = new Document([
            new Page(Size::a5(), images: [
                new PlacedImage('logo', Rectangle::mm(20, 20, 40, 40), 1000, 1000, transparency: true),
                $this->image('photo', null),
            ], boxes: [
                new Box('shadow', Rectangle::mm(20, 70, 40, 40), transparency: true),
                $this->box('text'),
                new Box('glow', Rectangle::mm(20, 70, 40, 40), transparency: true),
            ]),
            new Page(Size::a5(), boxes: [new Box('veil', Rectangle::mm(20, 20, 40, 40), transparency: true)]),
        ]);

        $findings = new Transparency()->check($document, new PrintProfile(allowTransparency: false));

        self::assertSame([
            ['warning', 'transparency.not-allowed', '"logo" on page 1 uses transparency, which the printer does not accept; flatten it before the file is sent.', 1],
            ['warning', 'transparency.not-allowed', '"shadow" on page 1 uses transparency, which the printer does not accept; flatten it before the file is sent.', 1],
            ['warning', 'transparency.not-allowed', '"glow" on page 1 uses transparency, which the printer does not accept; flatten it before the file is sent.', 1],
            ['warning', 'transparency.not-allowed', '"veil" on page 2 uses transparency, which the printer does not accept; flatten it before the file is sent.', 2],
        ], PageChecksTest::describe($findings));
        self::assertSame(['logo', 'shadow', 'glow', 'veil'], array_map(static fn (Finding $finding): ?string => $finding->subject, $findings));
        self::assertSame([], new Transparency()->check($document, new PrintProfile()));
    }

    public function testTheNewRulesFollowTheProfile(): void
    {
        $document = new Document([new Page(Size::a5(), images: [$this->image('photo', 330)], boxes: [
            $this->box('rule', lineWidth: Length::pt(0.1)),
            $this->box('white', inkCoverage: 0, overprint: true),
        ])]);
        $profile = new PrintProfile(
            bleed: Length::pt(0),
            severities: [Rule::InkImageAboveMaximum->value => Severity::Warning],
            ignore: [Rule::LineHairline->value],
        );

        self::assertSame([
            ['warning', 'ink.image-above-maximum', 'The image "photo" on page 1 reaches 330 % of total ink coverage, above the 300 % the printer accepts.', 1],
            ['error', 'overprint.white', '"white" on page 1 is white and set to overprint: it will not print at all.', 1],
        ], PageChecksTest::describe(new Preflight($profile)->check($document)->findings));
    }

    private function image(string $id, ?float $inkCoverage): PlacedImage
    {
        return new PlacedImage($id, Rectangle::mm(20, 20, 40, 40), 1000, 1000, ColourSpace::Cmyk, inkCoverage: $inkCoverage);
    }

    private function box(string $id, ?float $inkCoverage = null, bool $overprint = false, ?Length $lineWidth = null): Box
    {
        return new Box($id, Rectangle::mm(20, 20, 40, 40), inkCoverage: $inkCoverage, overprint: $overprint, lineWidth: $lineWidth);
    }
}
