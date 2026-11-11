<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Check\Placement;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * On a 500 × 700 pt page, with 9 pt of bleed and an 18 pt safety margin: the tolerance of 0.5 pt puts the limits at
 * 17.5 pt for the margin, 0.5 pt past the trim edge for crossing it, and 8.5 pt past it for reaching the bleed.
 */
#[CoversClass(Placement::class)]
final class PlacementTest extends TestCase
{
    /**
     * @return iterable<string, array{Rectangle, ?string, ?string}>
     */
    public static function content(): iterable
    {
        yield 'well inside' => [Rectangle::pt(50, 50, 100, 100), null, null];
        yield 'at the margin, top left' => [Rectangle::pt(17.5, 17.5, 100, 100), null, null];
        yield 'at the margin, bottom right' => [Rectangle::pt(382.5, 582.5, 100, 100), null, null];
        yield 'in the top margin' => [Rectangle::pt(50, 17.4, 100, 100), 'placement.in-safety-margin', 'top'];
        yield 'in the right margin' => [Rectangle::pt(382.6, 50, 100, 100), 'placement.in-safety-margin', 'right'];
        yield 'in the bottom margin' => [Rectangle::pt(50, 582.6, 100, 100), 'placement.in-safety-margin', 'bottom'];
        yield 'in the left margin' => [Rectangle::pt(17.4, 50, 100, 100), 'placement.in-safety-margin', 'left'];
        yield 'in two margins' => [Rectangle::pt(5, 5, 100, 100), 'placement.in-safety-margin', 'top, left'];
        yield 'on the trim edge, within tolerance' => [Rectangle::pt(-0.5, -0.5, 501, 701), 'placement.in-safety-margin', 'top, right, bottom, left'];
        yield 'across the top' => [Rectangle::pt(50, -0.6, 100, 100), 'placement.crosses-trim', 'top'];
        yield 'across the right' => [Rectangle::pt(400.6, 50, 100, 100), 'placement.crosses-trim', 'right'];
        yield 'across the bottom' => [Rectangle::pt(50, 600.6, 100, 100), 'placement.crosses-trim', 'bottom'];
        yield 'across the left' => [Rectangle::pt(-0.6, 50, 100, 100), 'placement.crosses-trim', 'left'];
        yield 'across all' => [Rectangle::pt(-10, -10, 520, 720), 'placement.crosses-trim', 'top, right, bottom, left'];
        yield 'beyond the top' => [Rectangle::pt(50, -20, 100, 20.5), 'placement.outside-trim', null];
        yield 'beyond the right' => [Rectangle::pt(499.5, 50, 100, 100), 'placement.outside-trim', null];
        yield 'beyond the bottom' => [Rectangle::pt(50, 699.5, 100, 100), 'placement.outside-trim', null];
        yield 'beyond the left' => [Rectangle::pt(-20, 50, 20.5, 100), 'placement.outside-trim', null];
        yield 'just inside the top' => [Rectangle::pt(50, -20, 100, 20.6), 'placement.crosses-trim', 'top'];
        yield 'just inside the right' => [Rectangle::pt(499.4, 50, 100, 100), 'placement.crosses-trim', 'right'];
        yield 'just inside the bottom' => [Rectangle::pt(50, 699.4, 100, 100), 'placement.crosses-trim', 'bottom'];
        yield 'just inside the left' => [Rectangle::pt(-20, 50, 20.6, 100), 'placement.crosses-trim', 'left'];
    }

    #[DataProvider('content')]
    public function testContentStaysInsideTheTrimBoxAndItsMargin(Rectangle $frame, ?string $code, ?string $edges): void
    {
        self::assertSame($this->expected($code, $edges), $this->check(new Box('text', $frame)));
    }

    /**
     * @return iterable<string, array{Rectangle, ?string, ?string}>
     */
    public static function bleeding(): iterable
    {
        yield 'full bleed' => [Rectangle::pt(-9, -9, 518, 718), null, null];
        yield 'to the bleed within tolerance' => [Rectangle::pt(-8.5, -8.5, 517, 717), null, null];
        yield 'past the bleed' => [Rectangle::pt(-20, -20, 540, 740), null, null];
        yield 'inside, clear of the edges' => [Rectangle::pt(0.6, 0.6, 498.8, 698.8), null, null];
        yield 'short at the top' => [Rectangle::pt(-9, -8.4, 518, 717.4), 'placement.short-of-bleed', 'top'];
        yield 'short at the right' => [Rectangle::pt(-9, -9, 517.4, 718), 'placement.short-of-bleed', 'right'];
        yield 'short at the bottom' => [Rectangle::pt(-9, -9, 518, 717.4), 'placement.short-of-bleed', 'bottom'];
        yield 'short at the left' => [Rectangle::pt(-8.4, -9, 517.4, 718), 'placement.short-of-bleed', 'left'];
        yield 'touching the top' => [Rectangle::pt(100, 0.5, 100, 100), 'placement.short-of-bleed', 'top'];
        yield 'touching the right' => [Rectangle::pt(399.5, 100, 100, 100), 'placement.short-of-bleed', 'right'];
        yield 'touching the bottom' => [Rectangle::pt(100, 599.5, 100, 100), 'placement.short-of-bleed', 'bottom'];
        yield 'touching the left' => [Rectangle::pt(0.5, 100, 100, 100), 'placement.short-of-bleed', 'left'];
        yield 'trimmed exactly' => [Rectangle::pt(0, 0, 500, 700), 'placement.short-of-bleed', 'top, right, bottom, left'];
        yield 'outside' => [Rectangle::pt(-9, -9, 9.5, 100), 'placement.outside-trim', null];
    }

    #[DataProvider('bleeding')]
    public function testWhatBleedsReachesTheBleedEdge(Rectangle $frame, ?string $code, ?string $edges): void
    {
        self::assertSame($this->expected($code, $edges), $this->check(new Box('background', $frame, bleeds: true)));
    }

    public function testWithoutBleedNothingFallsShort(): void
    {
        $page = new Page(Size::pt(500, 700), images: [new PlacedImage('back', Rectangle::pt(0, 0, 500, 700), 10, 10, bleeds: true)]);

        self::assertSame([], new Placement()->check(new Document([$page]), new PrintProfile(bleed: Length::pt(0))));
    }

    public function testMessagesNameTheElementPageEdgesAndLengths(): void
    {
        $page = new Page(Size::pt(500, 700), images: [
            new PlacedImage('photo', Rectangle::pt(-20, -20, 10, 10), 10, 10),
            new PlacedImage('back', Rectangle::pt(-5, 100, 100, 100), 10, 10, bleeds: true),
        ], boxes: [
            new Box('title', Rectangle::pt(-5, 100, 100, 100)),
            new Box('folio', Rectangle::pt(5, 100, 100, 100)),
            new Box('body', Rectangle::pt(100, 100, 100, 100)),
        ]);
        $profile = new PrintProfile(bleed: Length::mm(3), safetyMargin: Length::mm(5));
        $findings = new Placement()->check(new Document([new Page(Size::pt(500, 700)), $page]), $profile);

        self::assertSame([
            ['warning', 'placement.outside-trim', '"photo" on page 2 lies entirely outside the trim box and will not show.', 2],
            ['error', 'placement.short-of-bleed', '"back" on page 2 stops short of the 3 mm bleed at the left edge; a white line may show after trimming.', 2],
            ['error', 'placement.crosses-trim', '"title" on page 2 crosses the trim edge at the left and will be cut.', 2],
            ['warning', 'placement.in-safety-margin', '"folio" on page 2 is inside the 5 mm safety margin at the left edge.', 2],
        ], PageChecksTest::describe($findings));
        self::assertSame(['photo', 'back', 'title', 'folio'], array_map(static fn (Finding $finding): ?string => $finding->subject, $findings));
        self::assertSame([[], ['edges' => 'left', 'bleed' => '3 mm'], ['edges' => 'left'], ['edges' => 'left', 'margin' => '5 mm']], array_map(static fn (Finding $finding): array => $finding->parameters, $findings));
    }

    /**
     * @return list<array{string, float|int|string|null}>
     */
    private function check(Box $box): array
    {
        $page = new Page(Size::pt(500, 700), Length::pt(9), boxes: [$box]);
        $profile = new PrintProfile(bleed: Length::pt(9), safetyMargin: Length::pt(18));

        return array_map(static fn (Finding $finding): array => [$finding->code, $finding->parameters['edges'] ?? null], new Placement()->check(new Document([$page]), $profile));
    }

    /**
     * @return list<array{?string, ?string}>
     */
    private function expected(?string $code, ?string $edges): array
    {
        return null === $code ? [] : [[$code, $edges]];
    }
}
