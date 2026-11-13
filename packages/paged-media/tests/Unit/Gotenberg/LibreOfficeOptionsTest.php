<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\LibreOffice\InitialView;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\Magnification;
use Phil\PagedMedia\Gotenberg\LibreOffice\PageLayout;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;
use Phil\PagedMedia\Gotenberg\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LibreOfficeOptions::class)]
final class LibreOfficeOptionsTest extends TestCase
{
    public function testOnlyTheOptionsSetAreSent(): void
    {
        self::assertSame([], new LibreOfficeOptions()->fields());
    }

    public function testEveryOptionIsWrittenAsGotenbergReadsIt(): void
    {
        $options = new LibreOfficeOptions(
            password: 'p',
            paperSize: PaperSize::Legal,
            paperWidth: 8.5,
            paperHeight: '210mm',
            landscape: true,
            nativePages: '1-4',
            singlePage: false,
            skipEmptyPages: true,
            updateIndexes: false,
            exportFormFields: false,
            allowDuplicateFieldNames: true,
            nativeBookmarks: false,
            exportBookmarksToPdfDestination: true,
            exportPlaceholders: true,
            exportNotes: true,
            exportNotesInMargin: true,
            exportNotesPages: true,
            exportOnlyNotesPages: true,
            exportHiddenSlides: true,
            useTransitionEffects: false,
            convertOooTargetToPdfTarget: true,
            exportLinksRelativeFsys: true,
            addOriginalDocumentAsStream: true,
            nativeQuality: 100,
            nativeLosslessImageCompression: true,
            nativeReduceImageResolution: true,
            nativeMaxImageResolution: 150,
            nativeWatermarkText: 'DRAFT',
            nativeWatermarkColor: '#7FFE7F',
            nativeWatermarkFontSize: 1,
            nativeWatermarkRotation: 45.5,
            nativeWatermarkFont: 'Liberation Sans',
            nativePdfa: Profile::PdfA3b,
            nativePdfua: Profile::PdfUa1,
            nativeTaggedPdf: true,
            initialView: InitialView::Outline,
            initialPage: 1,
            magnification: Magnification::Zoom,
            zoom: 1,
            pageLayout: PageLayout::TwoColumnRight,
            openBookmarkLevels: -1,
            resizeWindowToInitialPage: true,
            centerWindow: true,
            openInFullScreenMode: true,
            displayPDFDocumentTitle: false,
            hideViewerMenubar: true,
            hideViewerToolbar: true,
            hideViewerWindowControls: true,
        );

        self::assertSame([
            'password' => 'p', 'paperSize' => 'Legal', 'paperWidth' => '8.5', 'paperHeight' => '210mm', 'landscape' => 'true',
            'nativePages' => '1-4', 'singlePage' => 'false', 'skipEmptyPages' => 'true', 'updateIndexes' => 'false',
            'exportFormFields' => 'false', 'allowDuplicateFieldNames' => 'true', 'nativeBookmarks' => 'false',
            'exportBookmarksToPdfDestination' => 'true', 'exportPlaceholders' => 'true', 'exportNotes' => 'true',
            'exportNotesInMargin' => 'true', 'exportNotesPages' => 'true', 'exportOnlyNotesPages' => 'true',
            'exportHiddenSlides' => 'true', 'useTransitionEffects' => 'false', 'convertOooTargetToPdfTarget' => 'true',
            'exportLinksRelativeFsys' => 'true', 'addOriginalDocumentAsStream' => 'true', 'nativeQuality' => '100',
            'nativeLosslessImageCompression' => 'true', 'nativeReduceImageResolution' => 'true', 'nativeMaxImageResolution' => '150',
            'nativeWatermarkText' => 'DRAFT', 'nativeWatermarkColor' => '#7FFE7F', 'nativeWatermarkFontSize' => '1',
            'nativeWatermarkRotation' => '45.5', 'nativeWatermarkFont' => 'Liberation Sans', 'nativePdfa' => 'PDF/A-3b',
            'nativePdfua' => 'PDF/UA-1', 'nativeTaggedPdf' => 'true', 'initialView' => 'outline', 'initialPage' => '1',
            'magnification' => 'zoom', 'zoom' => '1', 'pageLayout' => 'twoColumnRight', 'openBookmarkLevels' => '-1',
            'resizeWindowToInitialPage' => 'true', 'centerWindow' => 'true', 'openInFullScreenMode' => 'true',
            'displayPDFDocumentTitle' => 'false', 'hideViewerMenubar' => 'true', 'hideViewerToolbar' => 'true',
            'hideViewerWindowControls' => 'true',
        ], $options->fields());
    }

    public function testTheBoundsOfEachRangeAreTaken(): void
    {
        self::assertSame(['paperWidth' => '21cm', 'paperHeight' => '11in', 'nativeQuality' => '1', 'openBookmarkLevels' => '10'], new LibreOfficeOptions(paperWidth: '21cm', paperHeight: '11in', nativeQuality: 1, openBookmarkLevels: 10)->fields());
        self::assertSame(['nativeMaxImageResolution' => '1200', 'openBookmarkLevels' => '1'], new LibreOfficeOptions(nativeMaxImageResolution: 1200, openBookmarkLevels: 1)->fields());
        self::assertSame(['paperSize' => 'A5', 'paperWidth' => '595pt'], new LibreOfficeOptions(paperSize: PaperSize::A5, paperWidth: '595pt')->fields());
        self::assertSame(['paperSize' => 'B4', 'paperHeight' => '50pc'], new LibreOfficeOptions(paperSize: PaperSize::B4, paperHeight: '50pc')->fields());
        self::assertSame(['paperWidth' => '794px', 'paperHeight' => '1.5'], new LibreOfficeOptions(paperWidth: '794px', paperHeight: '1.5')->fields());
        self::assertSame(['nativeTiledWatermarkText' => 'COPY', 'nativePdfa' => 'PDF/A-4'], new LibreOfficeOptions(nativeTiledWatermarkText: 'COPY', nativePdfa: Profile::PdfA4)->fields());
        self::assertSame(['nativePdfa' => 'PDF/A-1b'], new LibreOfficeOptions(nativePdfa: Profile::PdfA1b)->fields());
        self::assertSame(['nativePdfa' => 'PDF/A-2b'], new LibreOfficeOptions(nativePdfa: Profile::PdfA2b)->fields());
        self::assertSame(['nativePdfua' => 'PDF/UA-1'], new LibreOfficeOptions(nativePdfua: Profile::PdfUa1)->fields());
    }

    #[DataProvider('invalidOptions')]
    public function testOptionsGotenbergWouldRefuseAreRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{callable, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'a width without height' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperWidth: 8.5), 'Without a paper size, a paper width goes with a paper height.'];
        yield 'a height without width' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperHeight: '11in'), 'Without a paper size, a paper width goes with a paper height.'];
        yield 'a width of zero' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperWidth: 0), 'paperWidth is a number above zero, in inches or with a unit (mm, cm, in, pt, px, pc); "0" is not.'];
        yield 'a width in furlongs' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperWidth: '3fur'), 'paperWidth is a number above zero'];
        yield 'a height in words' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperHeight: 'tall'), 'paperHeight is a number above zero, in inches or with a unit (mm, cm, in, pt, px, pc); "tall" is not.'];
        yield 'a unit alone' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperHeight: 'mm'), 'paperHeight is a number above zero'];
        yield 'a negative height' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperHeight: '-2cm'), 'paperHeight is a number above zero'];
        yield 'a unit inside' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(paperSize: PaperSize::A4, paperHeight: '2mm3'), 'paperHeight is a number above zero'];
        yield 'quality 0' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeQuality: 0), 'nativeQuality is from 1 to 100; 0 is not.'];
        yield 'quality 101' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeQuality: 101), 'nativeQuality is from 1 to 100; 101 is not.'];
        yield 'font size 0' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkFontSize: 0), 'nativeWatermarkFontSize is from 1 to '];
        yield 'initial page 0' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(initialPage: 0), 'initialPage is from 1 to '];
        yield 'zoom 0' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(zoom: 0), 'zoom is from 1 to '];
        yield 'resolution 72' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeMaxImageResolution: 72), 'nativeMaxImageResolution is 75, 150, 300, 600 or 1200; 72 is not.'];
        yield 'bookmark levels 0' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(openBookmarkLevels: 0), 'openBookmarkLevels is from 1 to 10; 0 is not.'];
        yield 'bookmark levels 11' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(openBookmarkLevels: 11), 'openBookmarkLevels is from 1 to 10; 11 is not.'];
        yield 'bookmark levels -2' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(openBookmarkLevels: -2), 'openBookmarkLevels is from 1 to 10; -2 is not.'];
        yield 'two watermarks' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkText: 'A', nativeTiledWatermarkText: 'B'), 'A conversion takes nativeWatermarkText or nativeTiledWatermarkText, not both.'];
        yield 'a colour by name' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkColor: 'green'), 'nativeWatermarkColor is written #rrggbb; "green" is not.'];
        yield 'a colour without hash' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkColor: '07ffe7f'), 'nativeWatermarkColor is written #rrggbb'];
        yield 'a colour not hexadecimal' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkColor: '#7ffe7g'), 'nativeWatermarkColor is written #rrggbb'];
        yield 'a colour with a bad first digit' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativeWatermarkColor: '#g00000'), 'nativeWatermarkColor is written #rrggbb'];
        yield 'pdf/a-2u natively' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativePdfa: Profile::PdfA2u), 'LibreOffice exports PDF/A-1b, PDF/A-2b, PDF/A-3b or PDF/A-4; PDF/A-2u is reached by the remediate stage.'];
        yield 'pdf/ua-2 natively' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativePdfua: Profile::PdfUa2), 'LibreOffice exports PDF/UA-1; PDF/UA-2 is reached by the remediate stage.'];
        yield 'pdf/a-4 and pdf/ua' => [static fn (): LibreOfficeOptions => new LibreOfficeOptions(nativePdfa: Profile::PdfA4, nativePdfua: Profile::PdfUa1), 'LibreOffice does not make PDF/A-4 and PDF/UA together: pair the profiles in the remediate stage instead.'];
    }
}
