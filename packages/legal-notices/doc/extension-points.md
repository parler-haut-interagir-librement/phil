# Extension points

The library is plain PHP and declares no service. What an application gives it are four ports, in
`Phil\LegalNotices\Contract`.

## `DocumentSource`

Where the versions are kept: the configuration, a database, files.

| Method | Contract |
|---|---|
| `history(DocumentType $type): DocumentHistory` | Every version of the document, drafts included; an empty history when it has none |

`LegalNotices` asks it on each call and keeps nothing: a source that reads a database reads it again; one that reads
the configuration builds its histories once.

## `BodyRenderer`

| Method | Contract |
|---|---|
| `toHtml(string $format, string $body): string` | HTML that is safe to show; `InvalidLegalNotice` for an unknown format or a body that cannot be read |

`PlainTextBody` renders the `text` format: escaped, a `<p>` per paragraph, `<br>` for the other line breaks.
phil/legal-notices-bundle renders every format of phil/markup. A renderer keeps `${…}` as written, for the
placeholders.

## `RetentionDurations`

| Method | Contract |
|---|---|
| `durations(): list<RetentionDuration>` | The retention periods the privacy policy lists, in its order |

## `ConsentCategories`

| Method | Contract |
|---|---|
| `categories(): list<ConsentCategory>` | The categories of cookies the privacy policy lists, in its order |

## The event

`Event\DocumentPublished` carries the type and the number of a version, identifiers only. The library dispatches
nothing; phil/legal-notices-bundle dispatches it for the versions published in a span of time.
