# phil/svg-blocks

**Formulas, diagrams, charts and QR codes of block documents drawn once, on the server, and kept with the block.**

phil/svg-blocks draws the `math`, `mermaid`, `chart` and `qrCode` blocks of UJO (Universal JSON Output) documents
when they are saved, and keeps each drawing with its block under `rendered`: the MathML of a formula, the SVG of a
diagram, a chart or a QR code. Each drawing is signed with an HMAC of the block's type, source and markup, so a
reader's page writes it only when this server made it for the source the block holds now; a drawing a client sent,
or one left from an older source, is dropped at the next save and never shown. Readers, EPUB readers and print
engines get the drawing without a script.

QR codes, charts and formulas are drawn in PHP by `phil/qr-code`, `phil/svg-chart` and `phil/latex-mathml`. Mermaid's
layout needs a browser engine: diagrams are drawn by a small Node.js service, called over HTTP through a port and an
adapter like a Gotenberg client, and its answer is rewritten by `phil/svg-sanitizer` before it is kept. Diagrams can
wait for a background pass, so a save never waits on the service.

It is a plain PHP library; `phil/svg-blocks-bundle` wires it into Symfony forms, the UJO
renderer, Messenger and a preview endpoint.

## Status

| Part | State |
|---|---|
| `BlockMarkup`: drawings made, kept, signed, redrawn when stale, nested blocks included | Implemented and tested |
| `Signer`: HMAC-SHA256 keys, versioned, compared in constant time | Implemented and tested |
| Renderers of QR codes, charts and formulas | Implemented and tested |
| `MermaidService` port, `HttpMermaidService` adapter, the answer sanitized | Implemented and tested; checked against the service |
| Deferred types listed as pending for a background pass | Implemented and tested |
| Previews of one block, without keeping it | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Drawing blocks](doc/drawing.md) | `BlockMarkup`, what each type is drawn from, deferred types, failures. |
| [Signing](doc/signing.md) | Why drawings are signed, the key, the secret, and rendering with `phil/ujo`. |
| [Mermaid service](doc/mermaid.md) | The port, the HTTP adapter, the service's protocol and its sanitized answer. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, the integration test against the service, and its bench. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/svg-blocks
```

```php
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Renderer\{ChartRenderer, MathRenderer, QrCodeRenderer};
use Phil\SvgBlocks\Signer;
use Phil\Ujo\Html\HtmlRenderer;

$signer = new Signer($secret);                              // 32 bytes or more
$markup = new BlockMarkup($signer, ['qrCode' => new QrCodeRenderer(), 'chart' => new ChartRenderer(), 'math' => new MathRenderer()]);

$document = $markup->render($submitted)->document;          // when saving
$html = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...))->render($document);   // when reading
```

## Requirements

PHP 8.5 or later, `phil/ujo`, `phil/qr-code`, `phil/svg-chart`, `phil/latex-mathml` and `phil/svg-sanitizer`.
Symfony HttpClient 8.2, for `HttpMermaidService`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
