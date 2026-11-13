# phil/paged-media-bundle

**PDFs in a Symfony application: drawn by a native writer or worked on by Gotenberg, through one service, now or in a
Messenger worker.**

phil/paged-media-bundle puts `phil/paged-media` in a Symfony application. One service, `PagedMedia`, renders the
documents of the library's native writer and, when stages are given, sends them on to Gotenberg — to make them PDF/A,
stamp, sign or encrypt them —; PDFs and office documents go to Gotenberg directly. The configuration gives Gotenberg's
URL and credentials, the certificate PDFs are signed with — a file, a secret held as text, or HashiCorp Vault — and the
timestamp authority. Document templates are registered by an attribute, and `PagedMediaQueue` renders a template, runs
stages or converts office documents in a Messenger worker, keeps the file, and announces it; its messages carry only
the identifier of a job kept aside, never a document, a parameter or personal data.

It is for any Symfony application that prints: receipts and tickets drawn by the native writer, archived invoices,
signed certificates, office documents turned into PDF, a book whose pages come back stamped and conformant.

It is a bundle built on `symfony/config` and `symfony/dependency-injection`. Symfony HttpClient,
Messenger and an event dispatcher are optional: without Gotenberg's URL only the native writer is there; the handler of
the messages and the queue are registered when Messenger is installed, and the event dispatched when a dispatcher is.

The design is small: `PhilPagedMediaBundle` reads the configuration, `PagedMediaServices` registers the services,
`PagedMedia` is the one service, `PagedMediaQueue` keeps jobs and dispatches their messages, `PagedMediaHandler`
handles them, `JobStore` is the port where the jobs wait and `PdfStorage` the port where their files are kept, each a
directory by default.

## Status

| Part | State |
|---|---|
| Configuration: Gotenberg's URL, credentials, timeout and HTTP client | Implemented and tested |
| `PagedMedia`: the native writer and Gotenberg behind one service; templates by name | Implemented and tested |
| HTML and CSS through the print engine of the application; `PrintSubject`, `PrintStarting` and `UnnamedPrintStarting` before a print | Implemented and tested |
| `PrintedNotes`: the notes of a UJO (Universal JSON Output) document at the foot of the page, with `phil/footnotes-sidenotes` | Implemented and tested |
| Signing: the certificate source (file, inline, vault), the timestamp authority, and what signatures say | Implemented and tested |
| Messenger: `PagedMediaQueue`, and `RenderPdf`, `RunPipeline` and `ConvertDocuments` carrying a job's identifier only, retried when the failure may pass | Implemented and tested |
| `JobStore`, its directory and `JobCodec`: the jobs as JSON, out of the messages | Implemented and tested |
| `CacheJobStore`: the jobs in a PSR-6 cache pool shared by every worker | Implemented and tested |
| Printed templates of the library as document templates — badges, labels, gift and download cards, calendars, large print — drawn from parameters | Implemented and tested |
| `FailedJobPurger`: the job of a message failed for good forgotten, unless `jobs.remove_failed` is false | Implemented and tested |
| `PdfStorage` and its directory, and the `PdfStored` event | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle, and running Gotenberg. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [Rendering](doc/rendering.md) | `PagedMedia`, document templates, HTML and notes, who may print, signatures and timestamps. |
| [Printed templates](doc/templates.md) | The library's badges, labels, cards, calendars and large print, by name and parameters. |
| [Messenger](doc/messenger.md) | The queue, the three messages, the job store, the storage, the event, and what is retried. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, beside the library's exceptions. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/paged-media-bundle symfony/http-client
```

```yaml
# config/packages/phil_paged_media.yaml
phil_paged_media:
    gotenberg:
        url: '%env(GOTENBERG_URL)%'
    signing:
        certificate: { type: file, path: '%kernel.project_dir%/config/signing/signer.p12', password: '%env(SIGNING_PASSWORD)%' }
        reason: Receipt
```

```php
#[AsDocumentTemplate('receipt')]
final readonly class ReceiptTemplate implements DocumentTemplate
{
    public function build(array $parameters): Document { /* draw with the native writer */ }
}

$output = $pagedMedia->renderTemplate('receipt', ['order' => 'R-42'], 'R-42.pdf', new Pipeline(new Remediate(Profile::PdfA3b)));
$job = $queue->render('receipt', ['order' => 'R-42'], 'receipts/R-42.pdf', sign: true);
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/paged-media`. Symfony HttpClient and a Gotenberg 9, for the stages and the
conversions; Symfony Messenger, for the queue. `ext-sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
