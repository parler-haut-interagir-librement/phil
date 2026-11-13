# Extension points

What an application implements, replaces, listens to or configures. Documents, stages and signing are
`phil/paged-media`'s.

## Ports

### `DocumentTemplate`

`Phil\PagedMediaBundle\Application\DocumentTemplate` — draws a document of the native writer from parameters. A class
implementing it with `#[AsDocumentTemplate('name')]` is tagged `phil_paged_media.template` by autoconfiguration;
without it, tag it by hand with a `name`. See [Rendering](rendering.md#templates).

| Method | Contract |
|---|---|
| `build(array $parameters): Document` | The document for these parameters — scalars and arrays, since a queued job keeps them as JSON |

### `PrintEngine`

`Phil\PagedMediaBundle\Application\Contract\PrintEngine` — the paged-media engine of the application. See
[Rendering](rendering.md#html-and-css). `phil/paged-media-weasyprint` implements it with the `weasyprint` command, and
the application registers it under this interface; another engine implements the same method.

| Method | Contract |
|---|---|
| `pdf(PrintableHtml $page): string` | The bytes of the PDF the engine lays out from the page |

### `JobStore`

`Phil\PagedMediaBundle\Application\Port\JobStore` — the claim check of queued work: Messenger messages carry the
job's identifier only. `DirectoryJobStore` writes each job as a JSON file under `jobs.directory`, and
`CacheJobStore`, chosen by `jobs.cache_pool`, keeps it in a PSR-6 cache pool; an application aliases the port to a
store of its own — a table, a Flysystem storage —, `JobCodec` giving it the text to keep. See
[Messenger](messenger.md#the-job-store).

| Method | Contract |
|---|---|
| `keep(RenderJob\|PipelineJob\|ConversionJob $job): string` | Stores the job under an opaque identifier, readable by the application alone, since it holds documents and parameters |
| `find(string $id): RenderJob\|PipelineJob\|ConversionJob` | Gives it back; `JobUnavailable` when it is gone |
| `remove(string $id): void` | Forgets it once its file is kept, or its message failed for good |

### `PdfStorage`

`Phil\PagedMediaBundle\Application\Port\PdfStorage` — where a queued PDF is kept. `DirectoryStorage` writes under
`storage.directory` and refuses a path that leaves it; an application aliases the port to its own — a media library.

| Method | Contract |
|---|---|
| `store(string $path, Output $output): void` | Keeps the file under the job's path; `StorageFailed` when it cannot |

## Events

| Event | When | What a listener does |
|---|---|---|
| `Phil\PagedMediaBundle\Application\Event\PrintStarting` | Before a print that names its work starts or is queued | Reads the `PrintSubject`; throws to stop the print |
| `Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting` | Before a print that names no work starts or is queued | Throws to stop the print |
| `Phil\PagedMediaBundle\Application\Event\PdfStored` | Once a queued PDF is kept | Reads the job's identifier, path, file name, media type and remediation counts: notifies, links, checks conformance |

## Configuration

| Option | What it extends |
|---|---|
| `gotenberg` | Where Gotenberg is, its credentials, timeout and HTTP client |
| `signing.certificate` | Where the signing certificate comes from: a file, inline, or a vault |
| `signing.timestamp_url`, the PAdES options | The timestamp authority and what a signature says |
| `storage.directory`, `jobs.directory`, `jobs.remove_failed` | Where files and jobs are kept, and whether failed jobs are forgotten |

See [Configuration](configuration.md). A `CertificateSource` or `TimestampAuthority` of the application's own,
registered under the interface, replaces the configured one.

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\PagedMediaBundle\Application\PagedMedia` | Renders, runs stages and converts now; public |
| `Phil\PagedMediaBundle\Application\PagedMediaQueue` | The same, queued through Messenger; public |
| `Phil\PagedMediaBundle\Infrastructure\Notes\PrintedNotes` | A UJO (Universal JSON Output) document with its notes, for the print engine; public, with `phil/footnotes-sidenotes` |
| `Phil\FootnotesSidenotes\NoteResolver` | The numbering of the notes; redefined by an application with its own `NoteOptions` |
