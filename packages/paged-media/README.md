# phil/paged-media

**PDF documents from PHP, with real fonts, and nothing to install beside it.**

phil/paged-media writes PDF files and works on them. Its native writer places text, lines, rectangles, images and
fillable form fields on pages of any size, in the standard Helvetica fonts or in a TrueType font it embeds, reduced to
the glyphs the document prints, so a receipt, a ticket or a printable sheet can be written in any script; its printed
templates draw name badges, labels, gift and download cards, calendars and large-print editions from data. Its
Gotenberg client sends PDFs to a
[Gotenberg](https://gotenberg.dev) 9 service to merge, split, stamp, fill, redact, tag, encrypt, sign and timestamp
them, to make them PDF/A, PDF/UA or Factur-X and check that they are, to read their information, and to convert office
documents with LibreOffice. It does not lay out HTML or CSS: that is the job of a paged-media engine, for which its
`PageRule` writes the `@page` rule of a printed page, with its bleed and printer's marks.

It is for any PHP code that must produce a PDF: invoices and receipts, tickets, labels, certificates and reports with
the native writer alone, with no browser, binary or service; archived, accessible, signed or e-invoicing documents, and
documents written in an office suite, through Gotenberg. `phil/commerce-bundle` prints its receipts with it.

It is a plain PHP library. The native writer requires only `ext-ctype`, `ext-mbstring` and
`ext-zlib`, and its output is deterministic: the same calls give the same bytes, which makes documents comparable and
testable. The Gotenberg client speaks through Symfony HttpClient, a suggested dependency, and checks every request
before it is sent — a stage's options, the order of a list of stages, the addresses Gotenberg fetches, read by
`phil/url` — so a request Gotenberg would refuse is never sent.

## Status

| Part | State |
|---|---|
| `Document`: pages, text, right-aligned text, lines, rectangles, images, compressed streams, information dictionary | Implemented and tested |
| `StandardFont`: Helvetica and Helvetica Bold in Windows-1252, with their widths | Implemented and tested |
| Form fields: text boxes, check boxes, radio groups, combo and list boxes, with their appearances; kept fillable or flattened | Implemented and tested |
| Printed templates: name badges, address, product and shelf labels, gift cards, download cards, calendars, large print, on label sheets | Implemented and tested |
| `TrueTypeFont`: reading, measuring, embedding reduced to the glyphs used, the font's embedding licence respected | Implemented and tested |
| `Image`: JPEG kept as it is; PNG kept compressed, or split into colour and alpha | Implemented and tested |
| PDF/A-2u for archiving: XMP metadata, sRGB output intent, file identifier, and refusal of what the standard forbids | Implemented and tested |
| Gotenberg 9 client: the 28 PDF stages in a list or alone, typed and checked, and Gotenberg's order rules | Implemented and tested, and run against Gotenberg 9.1.0 |
| Gotenberg: PDF/A, PDF/UA and Factur-X remediation with its residual counts, and validation | Implemented and tested, and run against Gotenberg 9.1.0 |
| Gotenberg: signatures from certificate sources (files, text, HashiCorp Vault or OpenBao) and RFC 3161 timestamps | Implemented and tested, and run against Gotenberg 9.1.0 |
| Gotenberg: the LibreOffice conversion with its native options, the read routes, webhooks, downloads, basic authentication, failures by status | Implemented and tested, and run against Gotenberg 9.1.0 |
| `PageRule`: the `@page` rule of an HTML and CSS engine, with size, margins, bleed and printer's marks | Implemented and tested |
| HTML and CSS engines: WeasyPrint, in `phil/paged-media-weasyprint`, behind the `PrintEngine` port of `phil/paged-media-bundle` | Implemented and tested in its package |
| Other engines behind the same port, a page limit for an engine, formulas drawn as SVG for an engine without MathML | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Documents](doc/documents.md) | `Document`, `PageSize`, `Color` and `Metadata`: pages, text, shapes, determinism. |
| [Archiving](doc/archiving.md) | PDF/A-2u: what the document adds, and what it refuses. |
| [Forms](doc/forms.md) | Fillable fields, their appearances and fonts, PDF/A and flattening. |
| [Printed templates](doc/templates.md) | Badges, labels, gift and download cards, calendars and large print, from data. |
| [HTML and CSS engines](doc/html-and-css.md) | `PageRule` for a paged-media engine, and what the engines add. |
| [Fonts](doc/fonts.md) | `StandardFont` and `TrueTypeFont`: measuring, wrapping, embedding and licences. |
| [Images](doc/images.md) | `Image`: the JPEG and PNG files it reads, and how they are written. |
| [Gotenberg](doc/gotenberg.md) | The client: files, results, webhooks, downloads, credentials and failures. |
| [Stages](doc/stages.md) | Every stage, page selections, and the order rules of a list. |
| [Conformance](doc/conformance.md) | PDF/A, PDF/UA and Factur-X: remediation and validation. |
| [Signing](doc/signing.md) | Certificates and their sources, signatures, timestamps. |
| [LibreOffice](doc/libreoffice.md) | Converting office documents, and their native options. |
| [Reading a PDF](doc/reading.md) | Information, metadata, bookmarks, attachments and form fields. |
| [Exceptions](doc/exceptions.md) | `PagedMediaException`, the writer's exceptions and the client's. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that writes PDFs. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/paged-media
```

```php
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

$font = TrueTypeFont::fromFile('/path/to/NotoSans-Regular.ttf');

$pdf = new Document(PageSize::a5(), new Metadata(title: 'Ticket'));
$pdf->addPage();
$pdf->text(36, 540, 'Admit one', 18, StandardFont::HelveticaBold);
$pdf->text(36, 515, 'Élise Tremblay — 東京', 12, $font);
$pdf->line(36, 505, 384, 505, 0.5, Color::gray(128));
$pdf->textRight(384, 480, '12,50 $', 12, $font);

file_put_contents('ticket.pdf', $pdf->render());
```

Through Gotenberg, the same ticket archived and signed:

```php
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Signing\FileCertificateSource;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Symfony\Component\HttpClient\HttpClient;

$gotenberg = new Gotenberg(HttpClient::create(), 'http://gotenberg:3000');

$result = $gotenberg->pipe(
    Files::of(InputFile::fromDocument('ticket.pdf', $pdf)),
    new Pipeline(new Remediate(Profile::PdfA3b), Sign::with(new FileCertificateSource('/run/secrets/signer.p12', null, $password))),
);
$result->saveTo('ticket.pdf');   // $result->residual === 0: it conforms
```

## Requirements

PHP 8.5 or later with `ext-ctype`, `ext-mbstring`, `ext-uri` and `ext-zlib`, and `phil/url`; Symfony HttpClient and a
Gotenberg 9 service for the Gotenberg client. See [Installation](doc/installation.md).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
