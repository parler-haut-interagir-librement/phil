# Printing

With `phil/paged-media-bundle` installed, the bundle guards the prints of `PagedMedia` and `PagedMediaQueue`, now or
before they are queued: what it refuses reaches the caller, and nothing is rendered, sent or kept.

## The export guard before a print

`GuardPrintedWork` runs the [export guard](compliance.md#the-export-guard) before each print that names its work with
a `PrintSubject`: `ExportBlocked` stops a work without a declaration, or whose declaration misses what a license asks.

```php
$pagedMedia->printHtml($page, 'book.pdf', subject: new PrintSubject('book', (string) $book->getId()));
```

The type and the identifier are those of the declaration's `SubjectReference`.

## Documents without a license

An invoice, a ticket or a receipt is no work: its subject type goes in `unlicensed_subjects`, and its prints are made
unchecked.

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        unlicensed_subjects: [invoice, ticket]
```

```php
$pagedMedia->render($invoice, 'invoice.pdf', subject: new PrintSubject('invoice', $number));
```

## The strict printing

By default, a print that names no work is not checked: the bundle cannot tell what it is made of. An application that
publishes works only — where every print must carry its license — turns the strict printing on:

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        strict: true
        unlicensed_subjects: [invoice]
```

`RequirePrintSubject` then listens to `UnnamedPrintStarting`, which `phil/paged-media-bundle` dispatches before a
print without a `PrintSubject`, and throws `PrintSubjectRequired`. A document without a license still prints when it
names a subject type of `unlicensed_subjects`: being declared unlicensed is the one way out of the check.

`PrintSubjectRequired` is a `LicensingException` and a `TranslatableInterface`: its message for the reader is
`print.subject_required` of the domain `phil_licensing`, in English and French.

```php
try {
    $pagedMedia->render($document, 'book.pdf');
} catch (PrintSubjectRequired $refused) {
    $this->addFlash('error', $refused->trans($translator));
}
```

The worker that makes a queued print announces nothing: the print was checked in the request that queued it.
