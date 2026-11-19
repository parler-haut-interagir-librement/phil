# Importing

## In a controller

```php
use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\Ujo\Ujo;

#[Route('/manuscripts/import', methods: ['POST'])]
public function import(Request $request, DocumentImports $imports): Response
{
    $file = $request->files->get('manuscript');

    try {
        $imported = $imports->importFile($file->getPathname(), filename: $file->getClientOriginalName());
    } catch (DocumentImportException $exception) {
        return $this->render('manuscripts/refused.html.twig', ['reason' => $exception->getMessage()], new Response(status: 422));
    }

    $manuscript->replaceText(new Ujo()->encode($imported->document));

    return $this->render('manuscripts/imported.html.twig', ['report' => $imported->report->summary()]);
}
```

The temporary path of an upload has no extension: `importFile()` takes the client's file name apart, to detect the
format from it and from the bytes. That name is the client's: a format outside `formats.import` is refused whatever
the name says, and a compound file is refused whatever its name. A file larger than `limits.total_bytes` is refused
before it is read in memory, and bytes larger than it are refused whatever their format, Markdown and HTML included.

`DocumentImports` holds no state; one service imports every file. `DocumentImporter` stays available for code that
needs every format the library reads.

## Scanned before it is parsed

When `phil/upload-scan-bundle` is registered, `DocumentImports` hands each file to its scanner once the size and the
format are accepted and before any reader opens it, under the context `document_import`: the antivirus looks inside
the archive of a DOCX or an ODT. An infected file throws `InvalidDocument` — "The document contains a threat and was
refused." — and is quarantined by that bundle; a file the scanner could not check throws `InvalidDocument` unless
the scanner's policy accepts it. `scan` turns it `on` — required, refused at boot without the bundle — or `off`.

The screening is the `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` service: an application with
another check aliases the interface to its own service, and sets `scan: off` so that the bundle registers none.

## The report

`Imported::$report` lists what the document had that a UJO document does not keep — comments, tracked deletions,
colours, embedded objects, annotations of a PDF page, paragraphs of the styles `styles` leaves out — and the parts of
SVG images the sanitizer removed, under `svg-content` with the reason of each removal as detail. An import with
omissions has succeeded. The application shows the report in one of three ways:

- `Report::summary()`, one sentence per kind, in English; `Report::toArray()`, rows of `what`, `detail`, `count` and
  `description`, to translate by the value of each row;
- `DocumentImports::documentWithReport($imported)`, the document with a last `warning` block titled by
  `report.title`, so that an editor shows the report with the text; the document alone when nothing was left out;
- `@PhilDocumentImport/report.html.twig`, an HTML fragment, when Twig is installed;
- a listener of `Phil\DocumentImportBundle\Domain\Event\DocumentImported`, dispatched after every import of
  `DocumentImports` when the application has an event dispatcher, with the result, the file name and, for an import
  on a worker, its claim.

```php
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ImportReports
{
    public function __invoke(DocumentImported $event): void
    {
        foreach ($event->imported->report->toArray() as $row) {
            // count the omissions, keep them with the manuscript, tell the author
        }
    }
}
```

### The report in Twig

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report} %}
```

The fragment renders a `section` with the role `status`, a heading — `report.title`, through the Twig function
`document_import_report_title()`, unless `title` is given — and one list item per sentence of `Report::summary()`,
escaped. It renders nothing when nothing was left out. `level` sets the level of the heading, from 1 to 6, 2 by
default; `id` the identifier of the heading, which the section is labelled by, `document-import-report-title` by
default. The theme of the application styles the class `document-import-report`.

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report, title: 'Not kept'|trans, level: 3, id: 'chapter-' ~ chapter.id ~ '-report'} %}
```

## The style map

`styles` decides what a paragraph style becomes before the readers' own rules: a house style `Chapter Title` read as
a level 1 heading, `Rules Box` as a quotation, `Author Note` left out. A style based on a named one becomes what its
parent becomes, unless it is named too. DOCX, ODT, flat ODT and RTF have paragraph styles; Markdown, HTML and PDF
have none.

## Exporting

```php
$exported = $imports->export($manuscript->document(), Format::Docx, ['title' => $manuscript->title()]);

return new Response($exported->bytes, headers: [
    'Content-Type' => $exported->mediaType(),
    'Content-Disposition' => HeaderUtils::makeDisposition('attachment', 'manuscript.'.$exported->extension()),
]);
```

The exporter writes DOCX or ODT, among `formats.export`, and reads the images from `export.media_source`; it never
fetches an address itself. An SVG image goes into ODT once sanitized by the `SvgSanitizer` service. `Exported::$report`
lists the blocks and images the file has no place for.

## Long documents

A large archive or PDF is read in memory and its images stored one by one: importing a book is work for a Messenger
worker rather than a request. [Imports on a worker](worker.md) queues the file with a message that carries its claim
alone.
