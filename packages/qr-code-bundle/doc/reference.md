# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `PhilQrCodeBundle` | The bundle and its `phil_qr_code` configuration. |
| `Application\QrCodes` | Codes encoded and drawn with the defaults: SVG, PNG, `data:` URI, label. |
| `Application\QrCodeDefaults` | The defaults of the configuration. |
| `Application\Logos` | The configured logos, by name. |
| `Application\ContentFactory` | Typed contents from arrays. |
| `Application\Fields` | The values of such an array read by their type. |
| `Infrastructure\Twig\QrCodeExtension` | `qr_code()`, `qr_code_label()` and `qr_code_url()`. |
| `Infrastructure\Twig\Components\QrCodeComponent` | `<twig:PhilQrCode:QrCode>`. |
| `Infrastructure\Http\QrCodeController` | The route that serves codes at signed addresses. |
| `Infrastructure\Http\QrCodeUrls` | The signed addresses of the route. |
| `Infrastructure\Ujo\QrCodeBlockRenderer` | The `qrCode` blocks of UJO documents, for phil/svg-blocks-bundle. |
| `Infrastructure\PagedMedia\PdfQrCodes` | Codes drawn in PDF documents of phil/paged-media. |
| `Infrastructure\DependencyInjection\QrCodeServices` | The services of the configuration. |
| `Infrastructure\DependencyInjection\SvgBlocksPass` | The `qrCode` renderer of phil/svg-blocks-bundle replaced. |
