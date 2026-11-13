<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Integration;

use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\PagedMediaWeasyprint\Internal\AssetInlining;
use Phil\PagedMediaWeasyprint\LocalAssets;
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;

/**
 * The real command, on a machine that has it; skipped elsewhere.
 */
#[CoversClass(WeasyPrintEngine::class)]
#[CoversClass(LocalAssets::class)]
#[CoversClass(AssetInlining::class)]
#[CoversClass(PdfOptions::class)]
final class WeasyPrintCommandTest extends TestCase
{
    private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private string $binary;

    private string $directory;

    protected function setUp(): void
    {
        $binary = new ExecutableFinder()->find('weasyprint');

        if (null === $binary) {
            self::markTestSkipped('The weasyprint command is not installed.');
        }

        $this->binary = $binary;
        $this->directory = sys_get_temp_dir().'/phil-weasyprint-real-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/assets', 0o700, true);
        file_put_contents($this->directory.'/assets/pixel.png', base64_decode(self::PIXEL, true));
        file_put_contents($this->directory.'/outside.png', base64_decode(self::PIXEL, true));
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            new Filesystem()->remove($this->directory);
        }
    }

    public function testTheInstalledVersionIsSupported(): void
    {
        $this->expectNotToPerformAssertions();

        new WeasyPrintEngine($this->binary)->assertSupported();
    }

    public function testAPageBecomesAPdf(): void
    {
        $pdf = new WeasyPrintEngine($this->binary)->pdf(new PrintableHtml('<h1>Été</h1>', ['@page { size: A5 }'], 'fr', 'Book'));

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testAFileOutsideTheDirectoryIsNotPrinted(): void
    {
        $engine = new WeasyPrintEngine($this->binary, assets: new LocalAssets($this->directory.'/assets'));

        $inside = $engine->pdf(new PrintableHtml('<img src="pixel.png">'));
        $absolute = $engine->pdf(new PrintableHtml('<img src="file://'.$this->directory.'/outside.png">'));
        $relative = $engine->pdf(new PrintableHtml('<img src="../outside.png">'));
        $attachment = $engine->pdf(new PrintableHtml('<link rel="attachment" href="file://'.$this->directory.'/outside.png">x'));

        self::assertStringContainsString('/Image', $inside);
        self::assertStringNotContainsString('/Image', $absolute);
        self::assertStringNotContainsString('/Image', $relative);
        self::assertStringNotContainsString('/EmbeddedFile', $attachment);
    }

    public function testARemoteImageIsNotFetched(): void
    {
        $pdf = new WeasyPrintEngine($this->binary)->pdf(new PrintableHtml('<img src="https://example.com/logo.png">'));

        self::assertStringNotContainsString('/Image', $pdf);
    }

    public function testAPrintPdfHasItsBleedAndItsOutputIntent(): void
    {
        $profile = $this->colourProfile();
        copy($profile, $this->directory.'/assets/press.icc');
        $engine = new WeasyPrintEngine(
            $this->binary,
            assets: new LocalAssets($this->directory.'/assets'),
            pdf: new PdfOptions(PdfVariant::PdfX4, '--press'),
        );

        $pdf = $engine->pdf(new PrintableHtml('<p>Proof</p>', [
            '@color-profile --press { src: url("press.icc"); components: c, m, y, k }',
            '@page { size: 148mm 210mm; bleed: 3mm; marks: crop cross }',
        ]));

        $objects = $this->objects($pdf);
        self::assertStringContainsString('/GTS_PDFX', $objects);
        self::assertStringContainsString('/DestOutputProfile', $objects);
        self::assertStringContainsString('/TrimBox', $objects);
        self::assertStringContainsString('/BleedBox', $objects);
    }

    /**
     * The PDF with its compressed streams written out, as WeasyPrint keeps its dictionaries in object streams.
     */
    private function objects(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $streams);

        foreach ($streams[1] as $stream) {
            $inflated = @gzuncompress($stream);

            if (false !== $inflated) {
                $pdf .= $inflated;
            }
        }

        return $pdf;
    }

    private function colourProfile(): string
    {
        foreach (['/System/Library/ColorSync/Profiles/Generic CMYK Profile.icc', '/usr/share/color/icc/ghostscript/default_cmyk.icc', '/usr/share/color/icc/colord/FOGRA39L_coated.icc'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        self::markTestSkipped('No CMYK colour profile is installed.');
    }
}
