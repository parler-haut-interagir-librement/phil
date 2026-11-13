# LibreOffice

`Gotenberg::convert()` converts Word, Excel, PowerPoint, OpenDocument and over a hundred other formats to PDF with
LibreOffice, then runs a list of stages on the PDFs when one is given.

```php
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;

$result = $gotenberg->convert(
    Files::of(InputFile::fromPath('report.docx')),
    new LibreOfficeOptions(paperSize: PaperSize::A4, nativePdfa: Profile::PdfA2b, nativePdfua: Profile::PdfUa1),
    new Pipeline(new SetMetadata(title: 'Annual report')),
);
$result->filename;   // report.docx.pdf
```

One PDF comes out per document, named after it; several come back in a ZIP, unless a `merge` stage joins them, in the
alphanumeric order of their names.

## Options

`LibreOfficeOptions` takes Gotenberg's option names; an option left unset keeps LibreOffice's default and is not sent.

| Group | Options |
|---|---|
| Pages | `password`, `paperSize` (`PaperSize`), `paperWidth`, `paperHeight` (inches, or a number with `mm`, `cm`, `in`, `pt`, `px`, `pc`), `landscape`, `nativePages`, `singlePage`, `skipEmptyPages` |
| Content | `updateIndexes`, `exportFormFields`, `allowDuplicateFieldNames`, `nativeBookmarks`, `exportBookmarksToPdfDestination`, `exportPlaceholders`, `exportNotes`, `exportNotesInMargin`, `exportNotesPages`, `exportOnlyNotesPages`, `exportHiddenSlides`, `useTransitionEffects`, `convertOooTargetToPdfTarget`, `exportLinksRelativeFsys`, `addOriginalDocumentAsStream` |
| Images | `nativeQuality` (1 to 100), `nativeLosslessImageCompression`, `nativeReduceImageResolution`, `nativeMaxImageResolution` (75, 150, 300, 600 or 1200) |
| Watermark | `nativeWatermarkText` or `nativeTiledWatermarkText`, `nativeWatermarkColor` (`#rrggbb`), `nativeWatermarkFontSize`, `nativeWatermarkRotation`, `nativeWatermarkFont` |
| Conformance | `nativePdfa` (PDF/A-1b, PDF/A-2b, PDF/A-3b or PDF/A-4), `nativePdfua` (PDF/UA-1), `nativeTaggedPdf` |
| Viewer | `initialView` (`InitialView`), `initialPage`, `magnification` (`Magnification`), `zoom`, `pageLayout` (`PageLayout`), `openBookmarkLevels` (1 to 10, or -1), `resizeWindowToInitialPage`, `centerWindow`, `openInFullScreenMode`, `displayPDFDocumentTitle`, `hideViewerMenubar`, `hideViewerToolbar`, `hideViewerWindowControls` |

The options refuse what Gotenberg would: a width without a height when no paper size is given, both watermarks, a
PDF/A level LibreOffice does not export, and PDF/A-4 with PDF/UA, which LibreOffice claims without meeting.

## Native options or stages

An option whose name starts with `native` asks LibreOffice itself. A stage does the same job on the PDF afterwards, the
same way for every route, and reaches further: `stamp-text` for `nativeWatermarkText`, `remediate` for `nativePdfa` and
`nativePdfua`, which reaches every PDF/A level and reports the violations left. Nothing checks the native output, and a
stage after it can break its conformance: a `remediate` stage last checks it.
