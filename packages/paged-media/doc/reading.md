# Reading a PDF

The read routes answer JSON, one entry per PDF under its file name; the client reads it into typed values. Each takes
the password of an encrypted PDF.

| Method | Answers, by file name |
|---|---|
| `info($files, $password)` | `PdfInfo`: `pages`, `version`, `encrypted`, `tagged`, `linearized`, `formFields` |
| `metadata($files, $password)` | `DocumentInformation`: the title, author, subject, keywords, creator, producer and dates, each null when unset |
| `bookmarks($files, $password)` | `list<Bookmark>`, nested by their `children`, in the shape `SetBookmarks` writes |
| `attachments($files, $password)` | `list<Attachment>`: `name`, and `mimeType` and `relationship` when the PDF states them |
| `form($files, $password, $pages)` | `list<FormField>`: the fully qualified `name`, the `type` (`FormFieldType`), the `value`, the `pages`, the `states` of a checkbox or a radio group, the `options` of a choice, and `readOnly`, `required`, `multiSelect`, `editable`, `maxLength` |
| `validate($files, $profile, $password)` | `ValidationReport` ([Conformance](conformance.md)) |

```php
$info = $gotenberg->info(Files::of(InputFile::fromPath('report.pdf')))['report.pdf'];
$info->pages;       // 3

$fields = $gotenberg->form(Files::of($pdf), pages: '2')['form.pdf'];
$gotenberg->run(Files::of($pdf), new SetForm([$fields[0]->name => $fields[0]->options[0]]));
```

A date comes back as the PDF stores it: a PDF date, `D:20260101120000Z`, or an XMP date when only the XMP metadata
holds it. `SetMetadata::from()` writes what `metadata()` read. JSON of another shape than the route answers throws
`UnexpectedResponse`.
