# phil/qr-code-bundle

**QR codes in a Symfony application: in Twig, in an accessible component, at a cached address, in UJO documents and
in PDF documents.**

phil/qr-code-bundle brings [phil/qr-code](../qr-code/README.md) into Symfony. The configuration sets the defaults of
every code — level of error correction, quiet zone, colours, size, ECI header, kanji, the longest payload — and the
logos a template may name. `qr_code()` draws a text or a typed content in Twig as inline SVG or a `data:` URI; the
component `<twig:PhilQrCode:QrCode>` puts the code in a figure with what it holds written beside it; a signed route
serves codes as SVG or PNG with a long HTTP cache. The `qrCode` blocks of UJO (Universal JSON Output) documents are
drawn with the same defaults for phil/svg-blocks-bundle, and `PdfQrCodes` draws codes in PDF documents of
phil/paged-media, in vectors.

It is for any Symfony application that shows or prints QR codes: tickets, gift cards, posters, contact cards, Wi-Fi
signs, pages and books. Every code is drawn on the server; nothing is sent to a service.

## Status

| Part | State |
|---|---|
| Configuration of the defaults and of the logos, checked when the container is built | Implemented and tested |
| `qr_code()` function and filter: inline SVG, SVG or PNG `data:` URI; `qr_code_label()` | Implemented and tested |
| Typed contents from arrays: URL, e-mail, telephone, SMS, place, Wi-Fi, MeCard, vCard, event | Implemented and tested |
| `<twig:PhilQrCode:QrCode>`: a figure, a caption that says what the code holds, a link for an address | Implemented and tested |
| The signed route `/qr-code.svg` and `/qr-code.png`, public and immutable, `ETag` and `304`; `qr_code_url()` | Implemented and tested |
| The `qrCode` blocks of UJO documents drawn for phil/svg-blocks-bundle | Implemented and tested |
| `PdfQrCodes`: codes drawn in PDF documents of phil/paged-media, logo included | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, importing the route. |
| [Configuration](doc/configuration.md) | Every key of `phil_qr_code`, and the logos. |
| [Twig](doc/twig.md) | `qr_code()`, its options and outputs, `qr_code_label()`, typed contents as arrays. |
| [The component](doc/component.md) | `<twig:PhilQrCode:QrCode>`, its props and its accessibility. |
| [The route](doc/route.md) | `qr_code_url()`, the signed addresses and their cache. |
| [UJO blocks and PDF documents](doc/blocks-and-pdf.md) | The `qrCode` blocks and `PdfQrCodes`. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The bundle's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/qr-code-bundle
```

```yaml
# config/packages/phil_qr_code.yaml
phil_qr_code:
    error_correction: Q
    logos:
        brand: { path: '%kernel.project_dir%/assets/logo.svg', zone: 0.2 }
```

```twig
{{ qr_code('https://example.com/tickets/42', {title: 'Ticket 42'}) }}
<twig:PhilQrCode:QrCode :content="{type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}" logo="brand" />
```

## Requirements

PHP 8.5 or later with `ext-sodium`, Symfony 8.2 or later, Twig 3.21 or later, and `phil/qr-code`. Three integrations
are optional, suggested rather than required, and registered only when their package is installed: Symfony UX Twig
Component for the component, phil/svg-blocks (or phil/svg-blocks-bundle) for the `qrCode` blocks of UJO documents,
and phil/paged-media for QR codes in PDF documents. The bundle installs and passes its tests without them.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
