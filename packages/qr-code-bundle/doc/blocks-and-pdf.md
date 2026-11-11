# UJO blocks and PDF documents

Both integrations are optional. phil/svg-blocks, phil/svg-blocks-bundle and phil/paged-media are suggested by the
bundle, not required: `QrCodeBlockRenderer` is registered only when phil/svg-blocks is installed, the `qrCode`
renderer of phil/svg-blocks-bundle is replaced only when that bundle is enabled, and `PdfQrCodes` is registered only
when phil/paged-media is installed.

## The `qrCode` blocks

phil/svg-blocks-bundle draws the `qrCode` blocks of UJO (Universal JSON Output) documents when they are saved, keeps
the SVG with the block and signs it. With this bundle installed, its renderer of `qrCode` blocks is replaced by
`QrCodeBlockRenderer`, which draws the block's `data` with the defaults of this configuration — level, quiet zone,
colours, size, ECI, `max_bytes` —, so blocks look like the other codes of the application. A block without text, or
one the code cannot hold, is reported to its author by phil/svg-blocks-bundle.

## PDF documents

With phil/paged-media, `PdfQrCodes` draws a code on the current page of a `Document`, in vectors:

```php
$document = new Document();
$document->addPage();
$qrCodes->draw($document, 'https://example.com/tickets/42', x: 450, y: 60, side: 108, options: ['logo' => 'brand']);
```

`(x, y)` is the bottom left corner of the code, quiet zone included, and `side` its width in points. The code is a
light square and one rectangle a run of dark modules, sharp at any print size. It takes the options of `QrCodes`; a
PNG or JPEG logo is fitted in its zone, its proportions kept, and an SVG logo is refused.
