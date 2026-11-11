<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use GdImage;
use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\QrCode;
use Phil\QrCodeBundle\Application\Logos;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\PagedMedia\PdfQrCodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfQrCodes::class)]
final class PdfQrCodesTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(Document::class)) {
            self::markTestSkipped('phil/paged-media is not installed: QR codes drawn in PDF documents are an optional integration.');
        }
    }

    public function testDrawsEachRunOfDarkModulesAsARectangle(): void
    {
        $document = new Document();
        $document->addPage();
        new PdfQrCodes(new QrCodes())->draw($document, 'HELLO', 100, 200, 58, ['dark' => '#ff0000', 'quiet_zone' => 4]);
        $stream = $this->stream($document);
        $code = QrCode::encode('HELLO');
        $runs = 0;

        foreach ($code->rows() as $row) {
            $runs += (int) preg_match_all('/1+/', $row);
        }

        // The light square, then one rectangle a run, each 2 points a module: 58 = (21 + 8) × 2.
        self::assertStringContainsString('q 1 w 1 1 1 rg 100 200 58 58 re f Q', $stream);
        self::assertSame($runs, substr_count($stream, '1 0 0 rg'));
        // The top left finder: its first row starts at the quiet zone, 7 modules wide, at the top.
        self::assertStringContainsString('q 1 w 1 0 0 rg 108 248 14 2 re f Q', $stream);
    }

    public function testLeavesTheZoneOfALogoAndPlacesItInIt(): void
    {
        $codes = new QrCodes(logos: new Logos(['photo' => ['path' => __DIR__.'/../../Fixtures/logo.jpg', 'zone' => 0.25]]));
        $code = $codes->encode('https://example.com/t', ['logo' => 'photo']);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $document = new Document();
        $document->addPage();
        // Two points a module.
        $side = 2.0 * ($code->size() + 8);
        new PdfQrCodes($codes)->draw($document, 'https://example.com/t', 0, 0, $side, ['logo' => 'photo']);
        $stream = $this->stream($document);

        // One rectangle a run of dark modules outside the zone, none inside, each at its place.
        $expected = [];

        foreach ($code->rows() as $y => $row) {
            if ($y >= $zone->y && $y < $zone->y + $zone->side) {
                $row = substr_replace($row, str_repeat('0', $zone->side), $zone->x, $zone->side);
            }

            preg_match_all('/1+/', $row, $runs, \PREG_OFFSET_CAPTURE);

            foreach ($runs[0] as [$run, $x]) {
                $expected[] = \sprintf('%d %d %d', ($x + 4) * 2, (int) $side - ($y + 5) * 2, \strlen($run) * 2);
            }
        }

        preg_match_all('/0 0 0 rg (\S+ \S+ \S+) 2 re f/', $stream, $drawn);
        self::assertSame($expected, $drawn[1]);

        // The logo, 40 × 20, fitted in the zone less half a module on each side, centred.
        $box = 2 * ($zone->side - 1);
        $centre = 2 * (4 + $zone->x + $zone->side / 2);
        self::assertStringContainsString(\sprintf('q %s 0 0 %s %s %s cm /Im1 Do Q', $this->number($box), $this->number($box / 2), $this->number($centre - $box / 2), $this->number($side - $centre - $box / 4)), $stream);
    }

    public function testFitsATallLogoByItsHeight(): void
    {
        $tall = imagecreatetruecolor(20, 40);
        self::assertInstanceOf(GdImage::class, $tall);
        $path = sys_get_temp_dir().'/phil-qr-code-bundle-tall-'.bin2hex(random_bytes(4)).'.png';
        imagepng($tall, $path);
        $codes = new QrCodes(logos: new Logos(['tall' => ['path' => $path, 'zone' => 0.25]]));
        $code = $codes->encode('A', ['logo' => 'tall']);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $document = new Document();
        $document->addPage();

        $side = 2.0 * ($code->size() + 8);
        new PdfQrCodes($codes)->draw($document, 'A', 0, 0, $side, ['logo' => 'tall']);
        unlink($path);
        $box = 2 * ($zone->side - 1);
        $centre = 2 * (4 + $zone->x + $zone->side / 2);

        self::assertStringContainsString(\sprintf('q %s 0 0 %s %s %s cm /Im1 Do Q', $this->number($box / 2), $this->number($box), $this->number($centre - $box / 4), $this->number($side - $centre - $box / 2)), $this->stream($document));
    }

    public function testRefusesAnSvgLogo(): void
    {
        $codes = new QrCodes(logos: new Logos(['svg' => ['path' => __DIR__.'/../../Fixtures/logo.svg', 'zone' => 0.2]]));
        $document = new Document();
        $document->addPage();

        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The logo of a PDF must be a PNG or JPEG image; got an SVG.');

        new PdfQrCodes($codes)->draw($document, 'A', 0, 0, 100, ['logo' => 'svg']);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.2F', $value), '0'), '.');
    }

    private function stream(Document $document): string
    {
        $pdf = $document->render();
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $text = '';

        foreach ($streams[1] as $stream) {
            $inflated = @gzuncompress($stream);
            $text .= false === $inflated ? '' : $inflated;
        }

        return $text;
    }
}
