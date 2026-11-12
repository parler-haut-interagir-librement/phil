<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Exception\InvalidCardTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Color::class)]
final class ColorTest extends TestCase
{
    public function testTheSpellings(): void
    {
        self::assertEquals(new Color(0x11, 0x22, 0x33, 255), Color::fromHex('#123'));
        self::assertEquals(new Color(0x11, 0x22, 0x33, 0x44), Color::fromHex('#1234'));
        self::assertEquals(new Color(0xAB, 0xCD, 0xEF, 255), Color::fromHex('#ABCDEF'));
        self::assertEquals(new Color(0xAB, 0xCD, 0xEF, 0x01), Color::fromHex('#abcdef01'));
        self::assertSame('#abcdef01', Color::fromHex('#ABCDEF01')->hex());
        self::assertSame('#000000ff', new Color(0, 0, 0)->hex());
    }

    public function testTheAlphaOfGd(): void
    {
        self::assertSame([0, 127, 64, 63, 0], [new Color(0, 0, 0, 255)->gdAlpha(), new Color(0, 0, 0, 0)->gdAlpha(), new Color(0, 0, 0, 127)->gdAlpha(), new Color(0, 0, 0, 128)->gdAlpha(), new Color(0, 0, 0, 254)->gdAlpha()]);
        self::assertSame(127, new Color(0, 0, 0, 1)->gdAlpha(), 'An alpha of 1 rounds to fully transparent.');
    }

    public function testWhatIsNoColour(): void
    {
        foreach (['123', '#12', '#12345', '#1234567', '#123456789', 'x#123', '#ggg', "#123\n", 'red'] as $hex) {
            try {
                Color::fromHex($hex);
                self::fail(\sprintf('"%s" is refused.', $hex));
            } catch (InvalidCardTemplate $invalidCardTemplate) {
                self::assertStringContainsString('is not a colour: #rgb, #rgba, #rrggbb or #rrggbbaa.', $invalidCardTemplate->getMessage());
            }
        }
    }
}
