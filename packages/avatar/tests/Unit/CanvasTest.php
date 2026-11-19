<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use Phil\Avatar\Canvas;
use Phil\Avatar\Color;
use Phil\Avatar\Initials;
use Phil\Avatar\Palette;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Canvas::class)]
final class CanvasTest extends TestCase
{
    public function testAnEmptyCanvasDrawsNothing(): void
    {
        $canvas = $this->canvas();

        self::assertSame('', $canvas->body());
        self::assertNull($canvas->backgroundColor());
        self::assertSame('AL', $canvas->initials->letters);
        self::assertCount(1, $canvas->palette->backgrounds);
    }

    public function testShapesAreWrittenInOrderOverTheBackground(): void
    {
        $canvas = $this->canvas();
        $red = Color::fromHex('#ff0000');
        $canvas->circle(50.0, 25.5, -10.0, $red);
        $canvas->rectangle(10.0, 20.0, 30.0, -40.0, $red);
        $canvas->rectangle(10.0, 20.0, 30.0, 40.0, $red, 45.0);
        $canvas->rectangle(0.0, 0.0, 10.0, 10.0, $red, 360.0);
        $canvas->rectangle(0.0, 0.0, 10.0, 10.0, $red, 405.0);
        $canvas->polygon([[0.0, 0.0], [10.0, 0.126], [5.0, 8.66666]], $red);
        $canvas->background(Color::fromHex('#00F'));

        self::assertSame('#0000ff', $canvas->backgroundColor()?->toHex());
        self::assertSame(
            '<rect width="100" height="100" fill="#0000ff"/>'
            .'<circle cx="50" cy="25.5" r="10" fill="#ff0000"/>'
            .'<rect x="10" y="20" width="30" height="40" fill="#ff0000"/>'
            .'<rect x="10" y="20" width="30" height="40" fill="#ff0000" transform="rotate(45 25 40)"/>'
            .'<rect x="0" y="0" width="10" height="10" fill="#ff0000"/>'
            .'<rect x="0" y="0" width="10" height="10" fill="#ff0000" transform="rotate(45 5 5)"/>'
            .'<polygon points="0,0 10,0.13 5,8.67" fill="#ff0000"/>',
            $canvas->body(),
        );
    }

    public function testTextIsEscapedAndCentred(): void
    {
        $canvas = $this->canvas();
        $canvas->centredText('<A&"\'>', Color::fromHex('#111111'), 40.0);
        $canvas->centredText('مع', Color::fromHex('#ffffff'), 32.5, true);

        self::assertSame(
            '<text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="system-ui, -apple-system, &apos;Segoe UI&apos;, Roboto, &apos;Noto Sans&apos;, &apos;Noto Sans CJK&apos;, &apos;Noto Sans Arabic&apos;, sans-serif" font-size="40" font-weight="600" fill="#111111">&lt;A&amp;&quot;&apos;&gt;</text>'
            .'<text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="system-ui, -apple-system, &apos;Segoe UI&apos;, Roboto, &apos;Noto Sans&apos;, &apos;Noto Sans CJK&apos;, &apos;Noto Sans Arabic&apos;, sans-serif" font-size="32.5" font-weight="600" fill="#ffffff" direction="rtl">مع</text>',
            $canvas->body(),
        );
    }

    public function testInvalidUtf8IsReplaced(): void
    {
        self::assertSame("a\u{FFFD}b", Canvas::escape("a\xC3b"));
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function numbers(): iterable
    {
        yield 'whole' => [12.0, '12'];
        yield 'one decimal' => [12.5, '12.5'];
        yield 'rounded' => [1.006, '1.01'];
        yield 'zero' => [0.0, '0'];
        yield 'ten' => [10.0, '10'];
        yield 'negative' => [-3.25, '-3.25'];
        yield 'negative zero' => [-0.001, '0'];
        yield 'infinite' => [\INF, '0'];
        yield 'not a number' => [\NAN, '0'];
    }

    #[DataProvider('numbers')]
    public function testNumbersAreWrittenShort(float $value, string $text): void
    {
        self::assertSame($text, Canvas::number($value));
    }

    private function canvas(): Canvas
    {
        return new Canvas(Palette::fromHex(['#1e5aa8']), Initials::of('Ada Lovelace'));
    }
}
