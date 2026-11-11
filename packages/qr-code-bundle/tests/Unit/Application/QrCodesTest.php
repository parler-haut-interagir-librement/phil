<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Application;

use Phil\QrCode\Content\Url;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;
use Phil\QrCodeBundle\Application\Logos;
use Phil\QrCodeBundle\Application\QrCodeDefaults;
use Phil\QrCodeBundle\Application\QrCodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrCodes::class)]
#[CoversClass(QrCodeDefaults::class)]
#[CoversClass(Logos::class)]
final class QrCodesTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures';

    public function testDrawsWithTheDefaults(): void
    {
        $codes = new QrCodes();

        self::assertSame(new SvgWriter()->write(QrCode::encode('HELLO'), 'HELLO'), $codes->svg('HELLO'));
        self::assertSame(new PngWriter()->write(QrCode::encode('HELLO')), $codes->png('HELLO'));
        self::assertEquals(QrCode::encode('HELLO'), $codes->encode('HELLO'));
    }

    public function testKeepsTheDefaultsOfTheConfiguration(): void
    {
        $defaults = new QrCodeDefaults();

        self::assertSame([ErrorCorrection::Medium, 4, '#000000', '#ffffff', 0, 8, true, true, 2048], [$defaults->level, $defaults->quietZone, $defaults->dark, $defaults->light, $defaults->size, $defaults->scale, $defaults->eci, $defaults->kanji, $defaults->maxBytes]);
    }

    public function testDrawsWithTheDefaultsOfTheConfiguration(): void
    {
        $codes = new QrCodes(new QrCodeDefaults(ErrorCorrection::High, 1, '#112233', '#fefefe', 100, 3, false, false, 64));

        self::assertSame(new SvgWriter(1, '#112233', '#fefefe', 100)->write(QrCode::encode('Café 点', ErrorCorrection::High, eci: false, kanji: false), 'Café 点'), $codes->svg('Café 点'));
        self::assertSame(new PngWriter(3, 1, '#112233', '#fefefe')->write(QrCode::encode('HELLO', ErrorCorrection::High)), $codes->png('HELLO'));
        self::assertSame(['quiet_zone' => 1, 'dark' => '#112233', 'light' => '#fefefe', 'logo' => null], $codes->drawing([]));
        self::assertSame(64, \strlen($codes->encode(str_repeat('a', 64))->data));
    }

    public function testOverridesTheDefaults(): void
    {
        $codes = new QrCodes();
        $options = ['level' => 'L', 'quiet_zone' => 0, 'dark' => '#123456', 'light' => '#abcdef', 'size' => 50, 'title' => 'T'];

        self::assertSame(new SvgWriter(0, '#123456', '#abcdef', 50)->write(QrCode::encode('HELLO', ErrorCorrection::Low), 'T'), $codes->svg('HELLO', $options));
        self::assertSame(new PngWriter(2, 0, '#123456', '#abcdef')->write(QrCode::encode('HELLO', ErrorCorrection::Low)), $codes->png('HELLO', ['scale' => 2] + $options));
        self::assertSame(['quiet_zone' => 0, 'dark' => '#123456', 'light' => '#abcdef', 'logo' => null], $codes->drawing(['quiet_zone' => 0, 'dark' => '#123456', 'light' => '#abcdef']));
    }

    public function testDrawsATypedContentNamedByItsLabel(): void
    {
        $codes = new QrCodes();
        $wifi = ['type' => 'wifi', 'ssid' => 'Net', 'password' => 'password'];

        self::assertStringContainsString('<title>Net</title>', $codes->svg($wifi));
        self::assertSame('WIFI:T:WPA;S:Net;P:password;;', $codes->encode($wifi)->data);
        self::assertSame('Net', $codes->label($wifi));
        self::assertSame('HELLO', $codes->label('HELLO'));
        self::assertSame('https://example.com/a b', $codes->label(new Url('https://example.com/a b')));
        self::assertEquals(new Url('https://example.com'), $codes->content(['type' => 'url', 'url' => 'https://example.com']));
        self::assertSame('HELLO', $codes->content('HELLO'));
    }

    public function testWritesDataUris(): void
    {
        $codes = new QrCodes();

        self::assertSame('data:image/svg+xml;base64,'.base64_encode($codes->svg('HELLO')), $codes->dataUri('HELLO'));
        self::assertSame('data:image/png;base64,'.base64_encode($codes->png('HELLO')), $codes->dataUri('HELLO', [], true));
    }

    public function testDrawsAConfiguredLogo(): void
    {
        $codes = new QrCodes(logos: new Logos(['brand' => ['path' => self::FIXTURES.'/logo.svg', 'zone' => 0.25], 'photo' => ['path' => self::FIXTURES.'/logo.png', 'zone' => 0.2]]));
        $svg = $codes->svg('https://example.com', ['logo' => 'brand']);
        $code = QrCode::encode('https://example.com', logoZone: 0.25);

        self::assertSame(new SvgWriter()->write($code, 'https://example.com', Logo::fromFile(self::FIXTURES.'/logo.svg')), $svg);
        self::assertSame(ErrorCorrection::High, $codes->encode('A', ['logo' => 'photo'])->level);
        self::assertSame(new PngWriter()->write(QrCode::encode('A', logoZone: 0.2), Logo::fromFile(self::FIXTURES.'/logo.png')), $codes->png('A', ['logo' => 'photo']));
        self::assertEquals(Logo::fromFile(self::FIXTURES.'/logo.png'), $codes->drawing(['logo' => 'photo'])['logo']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'an unknown option' => [['colour' => 'red', 'output' => 'x'], 'The options of a QR code must be among level, quiet_zone, dark, light, size, scale, logo, title; got colour, output.'];
        yield 'an unknown level' => [['level' => 'X'], 'The level must be L, M, Q or H; got "X".'];
        yield 'a level not a text' => [['level' => 1], 'The level must be a text; got int.'];
        yield 'a size not an integer' => [['size' => '100'], 'The size must be an integer; got string.'];
        yield 'an unknown logo' => [['logo' => 'nope'], 'The logo must be one of the configured logos (); got "nope".'];
        yield 'a size out of range' => [['size' => 5000], 'The size must be from 0 to 4096 pixels; got 5000.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refused')]
    public function testRefusesAnOptionOutOfItsRange(array $options, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new QrCodes()->svg('A', $options);
    }

    public function testRefusesAPayloadLongerThanTheConfigurationAllows(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The content of a QR code must be at most 8 bytes.');

        new QrCodes(new QrCodeDefaults(maxBytes: 8))->encode(new Url('https://example.com'));
    }

    public function testNamesTheConfiguredLogosOfAnUnknownOne(): void
    {
        $this->expectExceptionMessageIsOrContains('one of the configured logos (a, b)');

        new Logos(['a' => ['path' => 'x', 'zone' => 0.2], 'b' => ['path' => 'y', 'zone' => 0.2]])->get('c');
    }

    public function testReadsALogoOnce(): void
    {
        $path = sys_get_temp_dir().'/phil-qr-code-bundle-logo-'.bin2hex(random_bytes(4)).'.svg';
        copy(self::FIXTURES.'/logo.svg', $path);
        $logos = new Logos(['brand' => ['path' => $path, 'zone' => 0.3]]);
        [$logo, $zone] = $logos->get('brand');
        unlink($path);

        self::assertSame(0.3, $zone);
        self::assertSame($logo, $logos->get('brand')[0]);
    }
}
