# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Draw codes in PHP with the defaults | Inject `QrCodes`: `svg()`, `png()`, `dataUri()`, `encode()`, `label()` |
| Draw a typed content the bundle has no array for | Pass a `Phil\QrCode\Contract\Content` of your own to `QrCodes` or `qr_code()` |
| Change the look of the component | Style `.phil-qr-code` and `.phil-qr-code__caption`, or override `@PhilQrCode/components/QrCode.html.twig` |
| Serve codes elsewhere | Call `QrCodeUrls::url()`, or write a controller of your own over `QrCodes` |
| Draw codes in another document format | Read `QrCodes::encode()` and `drawing()`, as `PdfQrCodes` does |
