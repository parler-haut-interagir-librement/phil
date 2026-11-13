# Rendering

`PagedMedia` is the native writer and Gotenberg behind one service. Every method answers an `Output`: the
`contents`, the `filename`, the `contentType`, and the `residual` and `rolledBack` counts when a `remediate` stage ran.

## The native writer

```php
public function __construct(private PagedMedia $pagedMedia) {}

$output = $this->pagedMedia->render($document, 'ticket.pdf');                       // the native writer alone
$output = $this->pagedMedia->render($document, 'ticket.pdf', new Pipeline(
    new Remediate(Profile::PdfA3b),
    $this->pagedMedia->signature(),
));                                                                                    // then through Gotenberg
```

A document is rendered by the library's `Document`; when stages are given, it is sent to Gotenberg under its file name
and the stages run on it.

## Templates

A template draws a document of the native writer from parameters. It is known by the name of its attribute:

```php
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

#[AsDocumentTemplate('ticket')]
final readonly class TicketTemplate implements DocumentTemplate
{
    public function build(array $parameters): Document
    {
        $document = new Document(PageSize::a5());
        $document->addPage();
        $document->text(36, 540, 'Seat '.$parameters['seat'], 18, StandardFont::HelveticaBold);

        return $document;
    }
}

$output = $this->pagedMedia->renderTemplate('ticket', ['seat' => 'A12'], 'A12.pdf');
```

The parameters are scalars and arrays, as the job store writes them as JSON. An unknown name throws `UnknownTemplate`, which lists
the names known.

## Gotenberg

```php
$output = $this->pagedMedia->process(Files::of(InputFile::fromPath($pdf)), new Pipeline(new Merge(), new Optimize()));
$output = $this->pagedMedia->convert(Files::of(InputFile::fromPath($docx)), new LibreOfficeOptions(nativePdfa: Profile::PdfA2b));
$gotenberg = $this->pagedMedia->gotenberg();   // the client itself: the read routes, webhooks, saveTo()
```

The output takes the name Gotenberg gives, or the first file's name. Without `gotenberg.url`, these throw
`NotConfigured`. The library's documentation describes the stages, the order rules and the LibreOffice options.

## HTML and CSS

The layout of HTML and CSS is left to a paged-media engine of the application's choice, a service registered as
`Phil\PagedMediaBundle\Application\Contract\PrintEngine`:

```php
$output = $this->pagedMedia->printHtml(new PrintableHtml($html, [$css], 'fr', 'The book'), 'book.pdf', new Pipeline(new Merge()));
```

`PrintableHtml::document()` gives the engine the whole document. With stages, the PDF goes on to Gotenberg. Without an
engine, `printHtml()` throws `NotConfigured`.

`phil/paged-media-weasyprint` is an engine ready to use, on the `weasyprint` command, and the bundle asks nothing of
the machine for it: the package is installed and registered under the interface when an application wants it. Its
documentation covers the wiring, the version of WeasyPrint and what a document may fetch.

## Notes at the foot of the page

With `phil/footnotes-sidenotes` installed, `PrintedNotes` turns a UJO (Universal JSON Output) document into a page for the
engine:

```php
$printed = $this->printedNotes->page($document, 'fr', title: 'The book');

if ([] !== $printed->resolution->problems) {
    // a reference without a note, a note never referenced: refuse, warn or carry on
}

$output = $this->pagedMedia->printHtml($printed->page, 'book.pdf');
```

The notes are resolved by the `Phil\FootnotesSidenotes\NoteResolver` service, which an application redefines for its
numbering, and placed at the foot of the page, or as `$placement` says, with the labels of the page's language and the
screen and print sheets of the package.

`countPerPage: true` adds the sheet that gives the calls and the markers the engine's footnote counter, for the numbers
only the engine can know. An engine that follows CSS GCPM starts it again on each page. WeasyPrint 70 counts the notes
before it lays out the pages and ignores that reset: its numbers go on from page to page, and the labels of the resolver
are then the better choice, with a restart per chapter or per page marker.

## Who may print a work

Every method that prints takes a `PrintSubject`, the type and the identifier of the work. `PagedMedia` and
`PagedMediaQueue` then dispatch `PrintStarting` first; a listener that throws stops the print before anything is
rendered, sent or queued. `phil/licensing-bundle` listens to it with its export guard.

```php
$output = $this->pagedMedia->render($document, 'book.pdf', subject: new PrintSubject('book', '42'));
```

A print that names no work dispatches `UnnamedPrintStarting` instead, which carries nothing: a listener that requires
every print to name its work throws, and the print stops the same way.

## Signatures and timestamps

```php
$sign = $this->pagedMedia->signature();                              // what the configuration says
$sign = $this->pagedMedia->signature(reason: 'Contract', pades: true); // an argument wins over the configuration
$stamp = $this->pagedMedia->timestamp();

$pipeline = $this->pagedMedia->finish($pipeline, sign: true, timestamp: true);
```

`signature()` takes the certificate the configured source gives now, and the configured timestamp authority vouches
for its signing time. `finish()` ends a list with the signature and a document timestamp when asked, or makes a list of
them when there is none. Without a certificate or an authority, they throw `NotConfigured`.
