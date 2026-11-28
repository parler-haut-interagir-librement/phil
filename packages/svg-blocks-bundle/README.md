# phil/svg-blocks-bundle

**Formulas, diagrams, charts and QR codes in Symfony block documents, drawn once on the server and shown everywhere.**

phil/svg-blocks-bundle integrates `phil/svg-blocks` into Symfony. Every UJO (Universal JSON Output) document a form
field receives — `phil/ujo-bundle`'s `UjoType`, or the block editor's field — has its `math`, `mermaid`, `chart` and
`qrCode` blocks drawn after it is checked, and each drawing is kept, signed, with its block. The `HtmlRenderer` of
`phil/ujo-bundle` is decorated to write those drawings, so pages, EPUB files and PDFs show MathML and SVG without a
script. A preview endpoint and its Stimulus controller show authors the drawing as they type.

Diagrams are drawn by a Mermaid service in a container, shipped in `docker/mermaid` — Node.js, Chromium and Mermaid
behind a small HTTP server, as Gotenberg runs beside an application —, confined as its written security review
requires. By default they are drawn in the background: the
save lists them as pending, and a Messenger message that carries only the document's subject asks a handler to draw
them through the application's `MarkupDocuments` port.

It is a Symfony bundle on `phil/svg-blocks`. Every service is the library's own class, declared under its class name.

## Status

| Part | State |
|---|---|
| Configuration: secret, QR code level, chart size, formula limits and macro budget, Mermaid service, preview | Implemented and tested |
| Formulas: every construction of `phil/latex-mathml`, macros, colours, links, numbers and chemistry among them | Implemented and tested |
| `StoredMarkupTypeExtension`: the blocks of `UjoType` and the block editor's field drawn when submitted | Implemented and tested |
| `HtmlRenderer` of `phil/ujo-bundle` decorated to write trusted drawings | Implemented and tested |
| Preview endpoint, stateless CSRF, and its Stimulus controller, fetched lazily and mapped in AssetMapperBundle | Implemented and tested; pure modules under Node |
| Background pass: `RenderDeferredMarkup`, its handler, `MarkupScheduler`, the `MarkupDocuments` port | Implemented and tested |
| Mermaid service: security review, confined Dockerfile and compose file, HTTP server with queue, deadlines and size limits | Implemented and tested under Node; image not built |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the route, the controller, the service. |
| [Configuration](doc/configuration.md) | Every option, and the services it declares. |
| [Forms and rendering](doc/forms-and-rendering.md) | What a field does to a submitted document, and how pages, EPUB and PDF show it. |
| [Preview](doc/preview.md) | The endpoint, its checks, and the Stimulus controller. |
| [Diagrams](doc/diagrams.md) | The Mermaid service, its image, and the background pass. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The PHP and Node tests, and testing an application that uses the bundle. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/svg-blocks-bundle
```

```yaml
# config/packages/phil_svg_blocks.yaml
phil_svg_blocks:
    mermaid:
        url: '%env(MERMAID_URL)%'
        documents: App\Content\ArticleDocuments
```

```php
$builder->add('body', UjoType::class);     // math, chart and qrCode blocks drawn when submitted
// after the flush:
$scheduler->schedule('article:'.$article->getId());   // diagrams drawn in the background
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `phil/svg-blocks`. `phil/ujo-bundle` for the UJO fields and the renderer; Symfony
HttpClient for the Mermaid service; Symfony Messenger for the background pass; Symfony's CSRF component for the
preview; AssetMapper and the Stimulus bundle for the preview controller. Docker, or any container runtime, for the
Mermaid service.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
