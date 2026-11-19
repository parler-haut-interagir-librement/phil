# Imports on a worker

A large archive or PDF is read in memory and its images stored one by one: importing a book is work for a Messenger
worker rather than a request. With `async` enabled, `DocumentImportQueue` takes the file in the request and the
worker imports it.

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    async:
        transport: async                                  # ImportDocument routed to this transport
        directory: '%kernel.project_dir%/var/document-imports'
```

```php
use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImportBundle\Application\DocumentImportQueue;

#[Route('/manuscripts/import', methods: ['POST'])]
public function import(Request $request, DocumentImportQueue $queue): Response
{
    $file = $request->files->get('manuscript');

    try {
        $claim = $queue->submit((string) file_get_contents($file->getPathname()), filename: $file->getClientOriginalName());
    } catch (DocumentImportException $exception) {
        return $this->render('manuscripts/refused.html.twig', ['reason' => $exception->getMessage()], new Response(status: 422));
    }

    $manuscript->awaitImport($claim);

    return $this->redirectToRoute('manuscript_show', ['id' => $manuscript->id()]);
}
```

## The path of a file

1. `submit()` refuses at once what `DocumentImports` would refuse without reading the file: bytes larger than
   `limits.total_bytes`, a format outside `formats.import`, a format it cannot detect. It throws `LimitExceeded` or
   `UnsupportedFormat`.
2. The file, its name and its format go to `PendingImports`, which gives back a claim.
3. `ImportDocument` is dispatched with the claim and nothing else: no byte of the file, no file name, nothing about
   the person who sent it. A transport, its logs and its failure queue see an identifier only.
4. On the worker, `ImportDocumentHandler` takes the file back by its claim and imports it with `DocumentImports`: the
   screening first — the antivirus, when `phil/upload-scan-bundle` is registered —, then the reader.
5. A document imported dispatches `DocumentImported` with the claim in `$claim`. A file the library refuses — not
   what its format says, larger than the limits once decoded, refused by the antivirus — dispatches
   `DocumentImportFailed` with the claim and the exception in `$reason`. In both cases the claim is released.
6. Any other error — the media store unreachable, a listener that fails — leaves the claim in place, and Messenger
   tries the message again.

A claim already released, by a message handled twice, is left alone.

```php
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ManuscriptImports
{
    #[AsEventListener]
    public function imported(DocumentImported $event): void
    {
        if (null === $event->claim) {
            return; // an import of the request, not of the worker
        }

        $manuscript = $this->manuscripts->awaitingImport($event->claim);
        $manuscript->replaceText(new Ujo()->encode($event->imported->document), $event->imported->report->toArray());
    }

    #[AsEventListener]
    public function failed(DocumentImportFailed $event): void
    {
        $this->manuscripts->awaitingImport($event->claim)->importRefused($event->reason->getMessage());
    }
}
```

## Where the files wait

The default store, `FilesystemPendingImports`, writes each file in `async.directory`: its bytes in
`<claim>.document`, its name and format in `<claim>.json`, readable by their owner only, in a directory it creates
with the same permissions. A claim is 32 random hexadecimal digits; any other claim names no file, so a claim read
from a message never reaches outside the directory. Choose a directory the web server does not serve and the worker
can read: on several machines, a shared volume, or a store of the application's own.

A store of the application's own implements `Phil\DocumentImportBundle\Application\Contract\PendingImports` —
`keep()`, `take()`, `release()` — and is named in `async.store`: object storage, a database table. Its claims are 1
to 255 letters, digits, `.`, `_` or `-`, the shape `ImportDocument` accepts.

A file whose message is lost stays in the store: a scheduled task of the application removes the files older than the
longest import it waits for.

## Routing

`async.transport` routes `ImportDocument` to a Messenger transport, before the application's own routing, which wins
when it names the message too. Without it, the application routes the message, or Messenger handles it at once in
the request.
