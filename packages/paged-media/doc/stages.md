# Stages

A stage is one operation on a PDF. It runs in a `Pipeline` — on `/forms/pdf/pipe`, or after a LibreOffice conversion —
or alone on its own route with `Gotenberg::run()`. Each stage checks its options when it is built and throws
`InvalidStage` for what Gotenberg would refuse; a `Pipeline` checks the order and throws `InvalidPipeline`.

Every stage implements `Stage\Stage`: `operation()`, an `Operation`; `options()`, the options as Gotenberg names them,
the ones left unset omitted; `files()`, the files the options name, uploaded beside the PDFs.

## Page selections

An option that takes pages takes a `Pages` or its text: a page (`3`), a range (`2-5`), a list of both (`1,3,5-7`), or
`all`, `odd`, `even` or `last` on their own. Pages count from 1, from the PDF the stage receives, and a range ascends.
`Pages::page(3)`, `Pages::range(2, 5)`, `Pages::all()`, `odd()`, `even()` and `last()` build them.

## Every stage

| Stage | Operation | Options |
|---|---|---|
| `Merge` | Appends the PDFs, in the alphanumeric order of their names | — |
| `Split::every(n)`, `Split::ranges(...)` | Cuts each PDF every n pages, or one part per selection; the parts come back in a ZIP and the stages after run on each | `every` or `ranges` |
| `Select` | Keeps the pages listed, in that order | `pages` |
| `Delete` | Removes the pages listed | `pages` |
| `Insert` | Inserts the pages of another PDF after a page, `0` before the first | `source` (a `.pdf`), `at`, `sourcePages` |
| `Rotate` | Sets the rotation of pages, a multiple of 90 | `angle`, `pages` |
| `StampText` | Draws a line of text: a watermark, a status | `text`, `x`, `y`, `fontSize`, `font` (`StampFont`, the 14 standard fonts), `color` (`#rrggbb`), `opacity` (0 to 1), `rotation`, `pages` |
| `StampImage` | Draws a PNG or JPEG image | `image`, `x`, `y`, `width`, `height`, `opacity`, `rotation`, `pages` |
| `StampPdf` | Draws a page of another PDF: a letterhead, a seal, a watermark | `source`, `sourcePage`, `x`, `y`, `scale`, `opacity`, `rotation`, `pages` |
| `SetMetadata` | Writes the document information; a field left unset keeps its value | `title`, `author`, `subject`, `keywords`, `creator`, `producer`, `creationDate`, `modDate` |
| `SetBookmarks` | Replaces the outline with `Bookmark`s, nested by their `children` | `bookmarks` |
| `SetForm` | Fills fields by their fully qualified names | `fields` |
| `FlattenForm` | Burns the form fields into the pages | — |
| `FlattenTransparency` | Replaces transparent content with opaque content | — |
| `ConvertColors` | Converts every colour to one space | `target` (`ColorTarget`: `cmyk`, `rgb`, `gray`, `output-intent`) |
| `Redact` | Removes the content under each `Region` from the file itself | `regions` |
| `Autotag` | Infers a structure tree, so screen readers can follow the PDF | — |
| `Attach` | Embeds a file | `file`, `name`, `mimeType`, `relationship` (`Relationship`, which makes it a PDF/A-3 associated file) |
| `Detach` | Removes an embedded file by name | `name` |
| `Optimize` | Rewrites the PDF smaller without losing quality; each pass runs unless turned off | `objectStreams`, `dedup`, `recompressImages`, `subsetFonts` |
| `Encrypt` | Protects the PDF with a user password, an owner password, or both | `userPassword`, `ownerPassword`, `algorithm` (`EncryptionAlgorithm`, AES-256 by default), `permissions` (`Permissions`) |
| `Decrypt` | Opens encrypted PDFs and removes their encryption | `password` |
| `Remediate` | Repairs the PDF until it conforms to PDF/A, PDF/UA or Factur-X ([Conformance](conformance.md)) | `profile`, `language`, `aggressive`, `figureAltText`, `xml`, `level`, `documentType` |
| `Sign` | Signs the PDF with a certificate ([Signing](signing.md)) | `certificate`, `key`, `certificatePassword`, `reason`, `location`, `contactInfo`, `name`, `pades`, `timestampUrl` |
| `Timestamp` | Adds an RFC 3161 document timestamp ([Signing](signing.md)) | `timestampUrl` |
| `Render` | Draws pages as images, which come back next to the PDF | `pages`, `dpi`, `format` (`ImageFormat`), `quality` (JPEG only) |
| `ExtractText` | Returns the text the pages show, the pages separated by a form feed | `pages`, `invisible`, `artifacts`, `annotations` |
| `ExtractAttachments` | Returns the embedded files, or the one named | `name` |

A date given to `SetMetadata` as a `DateTimeInterface` is written as a PDF date in UTC (`D:20260101120000Z`); a text
is passed as it is, so the information `Gotenberg::metadata()` read goes back with `SetMetadata::from()`.

## Order rules

Gotenberg checks the order of a list before it runs any stage; `Pipeline` holds the same rules and refuses the list
when it is built:

- `decrypt` comes first, and `render`, `extract-text` or `extract-attachments` last;
- a list holds at most one `remediate`, one `encrypt`, one `sign` and one `timestamp`; one `remediate` pairs a PDF/A
  profile and a PDF/UA one;
- after `remediate`, only `encrypt`, `optimize`, `sign`, `timestamp` and a terminal stage may follow; after `encrypt`,
  only `optimize` and a terminal stage; after `sign` or `timestamp`, only `sign`, `timestamp` and a terminal stage;
- `encrypt` does not combine with `sign`, `timestamp`, or a PDF/A or Factur-X `remediate`;
- `decrypt` does not combine with `sign` or `timestamp`: sign the decrypted PDF in a request of its own.

```php
new Pipeline(new Remediate(Profile::PdfA2b), new Rotate(90));
// InvalidPipeline: "rotate" cannot follow "remediate": only encrypt, optimize, sign, timestamp, render, extract-text and extract-attachments may.
```

`Pipeline::then()` adds stages under the same rules, `contains()` tells whether an operation is in the list, and
`toJson()` gives the `stages` field.

## What a stage does to the file

A stage rewrites the PDF whole or appends an update to it. A rewrite drops signatures, so Gotenberg refuses those stages
on a signed PDF: process, then sign. `Operation::rewrites()` tells them apart:

| The file | Stages |
|---|---|
| Rewritten | `select`, `delete`, `insert`, `merge`, `split`, `encrypt`, `decrypt`, `flatten-form`, `redact`, `remediate`, `convert-colors`, `flatten-transparency`, `optimize` |
| Updated, signatures kept | `rotate`, the stamps, `set-form`, `set-metadata`, `set-bookmarks`, `attach`, `detach`, `autotag`, `sign`, `timestamp` |
| Unchanged | `render`, `extract-text`, `extract-attachments` |
