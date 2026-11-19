<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\InvalidOption;
use Phil\ShortLinkBundle\Application\ShortLinkQrCodes;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use Phil\ShortLinkBundle\Tests\Unit\Fixture\Links;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShortLinkQrCodes::class)]
final class ShortLinkQrCodesTest extends TestCase
{
    public function testTheCodeHoldsTheAddressOfTheLinkOnTheDomainOfItsLocale(): void
    {
        $codes = new ShortLinkQrCodes(new Links()->links(), ErrorCorrection::High, 2, '#112233', '#fefefe', 300, 3);
        $link = new ShortLink('abc123', 'https://example.com/', new DateTimeImmutable(), new LinkTerms(locale: 'fr'));
        $svg = $codes->svg($link);

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('https://lien.example.fr/abc123', $svg);
        self::assertStringContainsString('#112233', $svg);
        self::assertStringContainsString('#fefefe', $svg);
        self::assertStringContainsString('width="300"', $svg);

        $png = $codes->png($link);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $size = getimagesizefromstring($png);
        self::assertIsArray($size);
        self::assertSame($size[0], $size[1]);
        self::assertSame(0, $size[0] % 3, 'three pixels a module');
    }

    public function testTheDefaults(): void
    {
        $codes = new ShortLinkQrCodes(new Links()->links());
        $link = new ShortLink('abc123', 'https://example.com/', new DateTimeImmutable());

        self::assertStringContainsString('width="256"', $codes->svg($link));
        self::assertStringContainsString('#000000', $codes->svg($link));
        self::assertSame(0, (getimagesizefromstring($codes->png($link))[0] ?? 1) % 8);
        self::assertSame(new ShortLinkQrCodes(new Links()->links(), quietZone: 4)->svg($link), $codes->svg($link), 'a quiet zone of four modules');
        self::assertSame(new ShortLinkQrCodes(new Links()->links(), quietZone: 4)->png($link), $codes->png($link));
    }

    public function testAnOptionOutOfItsRangeFailsWhenTheServiceIsBuilt(): void
    {
        $this->expectException(InvalidOption::class);

        new ShortLinkQrCodes(new Links()->links(), scale: 0);
    }

    public function testAColourOutOfItsFormFailsWhenTheServiceIsBuilt(): void
    {
        $this->expectException(InvalidOption::class);

        new ShortLinkQrCodes(new Links()->links(), dark: 'black');
    }
}
