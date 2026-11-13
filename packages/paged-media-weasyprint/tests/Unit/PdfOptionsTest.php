<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Unit;

use InvalidArgumentException;
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfOptions::class)]
#[CoversClass(PdfVariant::class)]
final class PdfOptionsTest extends TestCase
{
    public function testNoOptionGivesNoArgument(): void
    {
        self::assertSame([], new PdfOptions()->arguments());
    }

    public function testEveryOptionIsOneArgumentInAFixedOrder(): void
    {
        $options = new PdfOptions(PdfVariant::PdfX4, '--press', true, true, 300, 85, true);

        self::assertSame([
            '--pdf-variant=pdf/x-4',
            '--output-intent=--press',
            '--pdf-tags',
            '--pdf-forms',
            '--dpi=300',
            '--jpeg-quality=85',
            '--optimize-images',
        ], $options->arguments());
    }

    public function testAnArchivalVariantKeepsWeasyPrintsOutputIntent(): void
    {
        self::assertSame(['--pdf-variant=pdf/a-2u'], new PdfOptions(PdfVariant::PdfA2u)->arguments());
        self::assertSame(['--pdf-variant=pdf/ua-1', '--output-intent=srgb'], new PdfOptions(PdfVariant::PdfUa1, 'srgb')->arguments());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function intents(): iterable
    {
        yield 'srgb' => ['srgb'];
        yield 'device-cmyk' => ['device-cmyk'];
        yield 'a profile' => ['--press'];
        yield 'a profile with digits and dashes' => ['--coated_FOGRA-39'];
    }

    #[DataProvider('intents')]
    public function testAnOutputIntentIsANameOrAProfile(string $intent): void
    {
        self::assertSame(['--output-intent='.$intent], new PdfOptions(outputIntent: $intent)->arguments());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongIntents(): iterable
    {
        yield 'empty' => [''];
        yield 'a word' => ['cmyk'];
        yield 'one dash' => ['-press'];
        yield 'dashes only' => ['--'];
        yield 'a digit first' => ['--9press'];
        yield 'a space' => ['--my press'];
        yield 'another argument' => ['--press --allowed-protocols=file'];
        yield 'a line after' => ["--press\n"];
        yield 'a capital name' => ['SRGB'];
        yield 'something before the dashes' => ['x--press'];
    }

    #[DataProvider('wrongIntents')]
    public function testAnyOtherOutputIntentIsRefused(string $intent): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdfOptions(outputIntent: $intent);
    }

    public function testPdfXNeedsAColourProfile(): void
    {
        foreach ([null, 'srgb', 'device-cmyk'] as $intent) {
            try {
                new PdfOptions(PdfVariant::PdfX1a, $intent);
                self::fail('A PDF/X variant was accepted without a colour profile.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('A PDF/X variant needs the colour profile of its printing condition as its output intent.', $invalidArgumentException->getMessage());
            }
        }
    }

    public function testOnlyPdfXVariantsArePdfX(): void
    {
        $pdfX = array_values(array_filter(PdfVariant::cases(), static fn (PdfVariant $variant): bool => $variant->isPdfX()));

        self::assertSame([PdfVariant::PdfX1a, PdfVariant::PdfX3, PdfVariant::PdfX4, PdfVariant::PdfX5g], $pdfX);
    }

    public function testTheResolutionOfImagesIsBounded(): void
    {
        self::assertSame(['--dpi=1'], new PdfOptions(maxImageDpi: 1)->arguments());
        self::assertSame(['--dpi=2400'], new PdfOptions(maxImageDpi: 2_400)->arguments());

        foreach ([0, 2_401] as $dpi) {
            try {
                new PdfOptions(maxImageDpi: $dpi);
                self::fail(\sprintf('The resolution %d was accepted.', $dpi));
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('The resolution of images is between 1 and 2400 dots per inch.', $invalidArgumentException->getMessage());
            }
        }
    }

    public function testTheQualityOfJpegImagesIsBounded(): void
    {
        self::assertSame(['--jpeg-quality=0'], new PdfOptions(jpegQuality: 0)->arguments());
        self::assertSame(['--jpeg-quality=95'], new PdfOptions(jpegQuality: 95)->arguments());

        foreach ([-1, 96] as $quality) {
            try {
                new PdfOptions(jpegQuality: $quality);
                self::fail(\sprintf('The quality %d was accepted.', $quality));
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('The quality of JPEG images is between 0 and 95.', $invalidArgumentException->getMessage());
            }
        }
    }

    public function testTheMessageOfAWrongIntentNamesIt(): void
    {
        $this->expectExceptionMessageIsOrContains('The output intent "cmyk" is neither srgb, device-cmyk nor the dashed name of a colour profile.');

        new PdfOptions(outputIntent: 'cmyk');
    }
}
