<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit\Style;

use Phil\Avatar\Canvas;
use Phil\Avatar\Initials;
use Phil\Avatar\Palette;
use Phil\Avatar\Random;
use Phil\Avatar\Style\InitialsStyle;
use Phil\Avatar\Style\PixelsStyle;
use Phil\Avatar\Style\ShapesStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InitialsStyle::class)]
#[CoversClass(ShapesStyle::class)]
#[CoversClass(PixelsStyle::class)]
final class StylesTest extends TestCase
{
    public function testTheNames(): void
    {
        self::assertSame('initials', new InitialsStyle()->name());
        self::assertSame('shapes', new ShapesStyle()->name());
        self::assertSame('pixels', new PixelsStyle()->name());
    }

    public function testInitialsAreWrittenInTheInkOfTheBackground(): void
    {
        $canvas = $this->canvas(['#ffffff'], 'Ada Lovelace');
        new InitialsStyle()->draw($canvas, new Random('x'));

        self::assertStringStartsWith('<rect width="100" height="100" fill="#ffffff"/><text ', $canvas->body());
        self::assertStringContainsString('font-size="40" font-weight="600" fill="#111111">AL</text>', $canvas->body());
        self::assertStringNotContainsString('direction', $canvas->body());
    }

    public function testTheFontShrinksWithTheLetters(): void
    {
        foreach ([1 => '50', 2 => '40', 3 => '32'] as $max => $size) {
            $canvas = $this->canvas(['#000000'], 'Ada King Lovelace', $max);
            new InitialsStyle()->draw($canvas, new Random('x'));

            self::assertStringContainsString(\sprintf('font-size="%s" font-weight="600" fill="#ffffff">', $size), $canvas->body());
        }

        // A letter with its accent is one letter.
        $canvas = $this->canvas(['#000000'], "e\u{0301}mile");
        new InitialsStyle()->draw($canvas, new Random('x'));
        self::assertStringContainsString('font-size="50"', $canvas->body());
    }

    public function testRightToLeftInitialsAreLaidOutThatWay(): void
    {
        $canvas = $this->canvas(['#000000'], 'محمد علي');
        new InitialsStyle()->draw($canvas, new Random('x'));

        self::assertStringContainsString('fill="#ffffff" direction="rtl">مع</text>', $canvas->body());
    }

    public function testANameWithoutInitialsDrawsShapes(): void
    {
        $palette = ['#1e5aa8', '#a3322c', '#2e7d4f'];
        $initials = $this->canvas($palette, '🎉');
        $shapes = $this->canvas($palette, '');
        new InitialsStyle()->draw($initials, new Random('x'));
        new ShapesStyle()->draw($shapes, new Random('x'));

        self::assertSame($shapes->body(), $initials->body());
        self::assertStringNotContainsString('<text', $initials->body());
    }

    public function testInitialsPickTheirBackgroundFromTheSeed(): void
    {
        $seen = [];

        foreach (range(1, 40) as $seed) {
            $canvas = $this->canvas(['#000000', '#ffffff'], 'Ada');
            new InitialsStyle()->draw($canvas, new Random((string) $seed));
            $seen[(string) $canvas->backgroundColor()?->toHex()] = true;
        }

        self::assertCount(2, $seen);
    }

    public function testShapesAreThreeOfTheOtherColors(): void
    {
        $kinds = [];

        foreach (range(1, 30) as $seed) {
            $canvas = $this->canvas(['#000000', '#ffffff', '#1e5aa8'], '');
            new ShapesStyle()->draw($canvas, new Random((string) $seed));
            $body = $canvas->body();
            $background = $canvas->backgroundColor()?->toHex();

            self::assertSame(3, preg_match_all('/<(circle|rect x|polygon)[ =]/', $body, $matches));

            foreach ($matches[1] as $kind) {
                $kinds[$kind] = true;
            }

            preg_match_all('/fill="(#[0-9a-f]{6})"/', $body, $fills);
            self::assertSame($background, $fills[1][0]);
            self::assertNotContains($background, \array_slice($fills[1], 1));
        }

        self::assertCount(3, $kinds);
        self::assertArrayHasKey('circle', $kinds);
        self::assertArrayHasKey('polygon', $kinds);
        self::assertArrayHasKey('rect x', $kinds);
    }

    public function testShapesOfAOneColorPaletteUseItsInk(): void
    {
        $canvas = $this->canvas(['#000000'], '');
        new ShapesStyle()->draw($canvas, new Random('x'));

        preg_match_all('/fill="(#[0-9a-f]{6})"/', $canvas->body(), $fills);
        self::assertSame(['#000000', '#ffffff', '#ffffff', '#ffffff'], $fills[1]);
    }

    public function testShapesStayNearTheCanvas(): void
    {
        foreach (range(1, 30) as $seed) {
            $canvas = $this->canvas(['#000000', '#ffffff'], '');
            new ShapesStyle()->draw($canvas, new Random('s'.$seed));
            preg_match_all('/(?:cx|cy|r|x|y|width|height)="(-?[\d.]+)"/', $canvas->body(), $numbers);

            foreach ($numbers[1] as $number) {
                self::assertGreaterThanOrEqual(-13.0, (float) $number);
                self::assertLessThanOrEqual(113.0, (float) $number);
            }
        }
    }

    public function testATriangleIsEquilateral(): void
    {
        foreach (range(1, 40) as $seed) {
            $canvas = $this->canvas(['#000000', '#ffffff'], '');
            new ShapesStyle()->draw($canvas, new Random('t'.$seed));

            if (1 !== preg_match('/<polygon points="([^"]+)"/', $canvas->body(), $match)) {
                continue;
            }

            $points = array_map(static fn (string $pair): array => array_map(floatval(...), explode(',', $pair)), explode(' ', $match[1]));
            self::assertCount(3, $points);
            $sides = [];

            foreach ([[0, 1], [1, 2], [2, 0]] as [$a, $b]) {
                $sides[] = hypot($points[$a][0] - $points[$b][0], $points[$a][1] - $points[$b][1]);
            }

            self::assertEqualsWithDelta($sides[0], $sides[1], 0.05);
            self::assertEqualsWithDelta($sides[1], $sides[2], 0.05);
            self::assertGreaterThan(14.0, $sides[0]);
            self::assertLessThan(40.0, $sides[0]);

            return;
        }

        self::fail('No seed drew a triangle.');
    }

    public function testPixelsAreMirrored(): void
    {
        foreach (range(1, 20) as $seed) {
            $canvas = $this->canvas(['#000000'], '');
            new PixelsStyle()->draw($canvas, new Random('p'.$seed));
            preg_match_all('/<rect x="([\d.]+)" y="([\d.]+)" width="15" height="15" fill="#ffffff"\/>/', $canvas->body(), $cells, \PREG_SET_ORDER);
            $grid = [];

            foreach ($cells as [, $x, $y]) {
                $grid[] = (int) round(((float) $x - 12.5) / 15).','.(int) round(((float) $y - 12.5) / 15);
            }

            self::assertNotSame([], $grid);
            self::assertCount(\count($grid), array_unique($grid));

            foreach ($grid as $cell) {
                [$column, $row] = explode(',', $cell);
                self::assertContains((4 - (int) $column).','.$row, $grid);
            }
        }
    }

    public function testABlankGridDrawsTheCentreSquare(): void
    {
        // The seed "blank74637" draws its background, then fifteen chances that all fail.
        $canvas = $this->canvas(['#000000'], '');
        new PixelsStyle()->draw($canvas, new Random('blank74637'));

        self::assertSame('<rect width="100" height="100" fill="#000000"/><rect x="42.5" y="42.5" width="15" height="15" fill="#ffffff"/>', $canvas->body());
    }

    /**
     * @param list<string> $backgrounds
     */
    private function canvas(array $backgrounds, string $name, int $max = 2): Canvas
    {
        return new Canvas(Palette::fromHex($backgrounds), Initials::of($name, $max));
    }
}
