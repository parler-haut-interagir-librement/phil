# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilPagedMediaBundle` | `final class` | The bundle: its configuration, and the attribute of the templates |
| `Application\PagedMedia` | `final readonly class` | The native writer and Gotenberg behind one service |
| `Application\Output` | `final readonly class` | A rendered file, its name, its media type and its remediation counts |
| `Application\DocumentTemplate` | `interface` | A document of the native writer drawn from parameters |
| `Application\AsDocumentTemplate` | `attribute` | Registers a template under a name |
| `Application\DocumentTemplates` | `final readonly class` | The templates, by name |
| `Application\SignatureDefaults` | `final readonly class` | What every signature says unless a call says otherwise |
| `Application\PagedMediaQueue` | `final readonly class` | Keeps a job and dispatches its message |
| `Application\Job\RenderJob` | `final readonly class` | What a worker needs to render a template |
| `Application\Job\PipelineJob` | `final readonly class` | What a worker needs to run a list of stages |
| `Application\Job\ConversionJob` | `final readonly class` | What a worker needs to convert office documents |
| `Application\Job\StoredStage` | `final readonly class` | A stage read back from a kept job |
| `Application\Message\RenderPdf` | `final readonly class` | Renders the template of a job in a worker; carries the job's identifier only |
| `Application\Message\RunPipeline` | `final readonly class` | Runs the stages of a job in a worker; carries the job's identifier only |
| `Application\Message\ConvertDocuments` | `final readonly class` | Converts the documents of a job in a worker; carries the job's identifier only |
| `Application\Port\JobStore` | `interface` | Where the jobs wait for a worker: the claim check |
| `Application\Port\PdfStorage` | `interface` | Where the files of the messages are kept |
| `Application\Event\PdfStored` | `final readonly class` | Dispatched once a message's file is kept |
| `Application\Event\PrintStarting` | `final readonly class` | Dispatched before a print that names its work |
| `Application\Event\UnnamedPrintStarting` | `final readonly class` | Dispatched before a print that names no work |
| `Application\PrintSubject` | `final readonly class` | The work a print is made of, by type and identifier |
| `Application\PrintableHtml` | `final readonly class` | A page for the print engine |
| `Application\Contract\PrintEngine` | `interface` | The paged-media engine of the application |
| `Infrastructure\Notes\PrintedNotes`, `NotesPage` | `final readonly class` | A UJO (Universal JSON Output) document with its notes, for the engine |
| `Application\Exception\NotConfigured` | `final class` | A feature the configuration does not enable |
| `Application\Exception\UnknownTemplate` | `final class` | A template name no service is registered under |
| `Application\Exception\InvalidTemplateParameters` | `final class` | A printed template's parameter missing or of the wrong kind, by its path |
| `Application\Exception\StorageFailed` | `final class` | A file the storage could not keep, a job the store could not write or remove |
| `Application\Exception\JobUnavailable` | `final class` | A job the store cannot give |
| `Infrastructure\DependencyInjection\PagedMediaServices` | `final class` | The services of the configuration |
| `Infrastructure\Messenger\PagedMediaHandler` | `final readonly class` | Handles the three messages |
| `Infrastructure\Messenger\FailedJobPurger` | `final readonly class` | Forgets the job of a message that failed for good |
| `Infrastructure\Storage\DirectoryStorage` | `final readonly class` | Keeps the files in a directory |
| `Infrastructure\Storage\DirectoryJobStore` | `final readonly class` | Keeps the jobs in a directory, one JSON file each |
| `Infrastructure\Storage\CacheJobStore` | `final readonly class` | Keeps the jobs in a PSR-6 cache pool shared by the workers |
| `Infrastructure\Template\NameBadgesTemplate`, `AddressLabelsTemplate`, `ProductLabelsTemplate`, `ShelfLabelsTemplate`, `GiftCardsTemplate`, `DownloadCardsTemplate`, `CalendarTemplate`, `LargePrintTemplate` | `final readonly class` | The library's printed templates as document templates, drawn from parameters |
| `Infrastructure\Template\Parameters`, `PrintedTemplates` | `final readonly class`, `final class`, `@internal` | The parameters read by path; the templates by name and their fonts |
| `Infrastructure\Storage\JobCodec` | `final readonly class` | Writes a job as JSON and reads it back |
