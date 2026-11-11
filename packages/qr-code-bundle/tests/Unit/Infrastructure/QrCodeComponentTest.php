<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Twig\Components\QrCodeComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrCodeComponent::class)]
final class QrCodeComponentTest extends TestCase
{
    public function testHidesTheImageTheCaptionNames(): void
    {
        $component = new QrCodeComponent(new QrCodes());
        $component->content = 'https://example.com/t';

        self::assertStringStartsWith('<svg aria-hidden="true" xmlns=', $component->getSvg());
        self::assertSame('https://example.com/t', $component->getLabel());
        self::assertSame('https://example.com/t', $component->getHref());
    }

    public function testNamesTheImageByItsAlt(): void
    {
        $component = new QrCodeComponent(new QrCodes());
        $component->content = 'HELLO';
        $component->alt = 'Greeting';
        $component->size = 120;
        $component->level = 'H';

        self::assertSame(new QrCodes()->svg('HELLO', ['title' => 'Greeting', 'size' => 120, 'level' => 'H']), $component->getSvg());
        self::assertNull($component->getHref());
    }

    public function testNamesTheImageByItsLabelWithoutACaption(): void
    {
        $component = new QrCodeComponent(new QrCodes());
        $component->content = ['type' => 'wifi', 'ssid' => 'Net', 'password' => 'password'];
        $component->caption = false;
        $component->logo = '';

        self::assertSame(new QrCodes()->svg($component->content), $component->getSvg());
        self::assertSame('Net', $component->getLabel());
        self::assertNull($component->getHref());
    }

    public function testLinksTheCaptionOfAWebAddressAlone(): void
    {
        $component = new QrCodeComponent(new QrCodes());

        foreach (['https://example.com/a b' => null, 'HTTP://EXAMPLE.COM' => 'HTTP://EXAMPLE.COM', 'ftp://example.com' => null, 'javascript:alert(1)' => null, 'see https://example.com' => null, "https://example.com\n" => null, 'https://example.com/"x' => null] as $content => $href) {
            $component->content = $content;
            self::assertSame($href, $component->getHref(), $content);
        }

        $component->content = ['type' => 'url', 'url' => 'https://example.com/a b'];
        self::assertSame('https://example.com/a%20b', $component->getHref());
        $component->content = ['type' => 'phone', 'number' => '+1555'];
        self::assertNull($component->getHref());
    }
}
