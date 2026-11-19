# The report

`Imported::$report` says what the document had that its UJO form does not keep.

```php
foreach ($imported->report->omissions() as $omission) {
    printf("%s (%s): %d\n", $omission->what->description(), $omission->detail, $omission->count);
}

$imported->report->isEmpty();
$imported->report->count();                    // everything left out
$imported->report->count(Omitted::Comment);    // one kind
$imported->report->toArray();                  // rows for JSON: what, detail, count, description
$imported->report->summary();                  // one sentence per kind, its details counted
$imported->report->toBlock('Not imported');    // a phil/ujo warning block of those sentences
$imported->documentWithReport();               // the document with that block at its end
```

Each `Omission` is one kind and one detail, with the number of times it was met. The kinds come in the order they
were first met, and the details of a kind after it.

| `Omitted` | Value | Details |
|---|---|---|
| `Comment` | `comment` | |
| `TrackedChange` | `tracked-change` | `deletion` |
| `HeaderFooter` | `header-footer` | `header`, `footer` |
| `TextBox` | `text-box` | |
| `Drawing` | `drawing` | `shape`, `chart` |
| `Equation` | `equation` | |
| `EmbeddedObject` | `embedded-object` | |
| `Image` | `image` | `missing`, `linked file`, `same name`, the media type of an image a browser does not show, `application/octet-stream` for a `data:` address that is not an image; for RTF the kind of picture (`emfblip`, `wmetafile`…); for PDF `pdf colour space`, `pdf JPXDecode` and the other image filters, `inline`; for an export `not fetched` |
| `SvgContent` | `svg-content` | The value of the `RemovalReason` of `phil/svg-sanitizer`: `denied_element`, `event_handler`, `external_reference`… |
| `NestedTable` | `nested-table` | |
| `MergedCell` | `merged-cell` | |
| `InternalLink` | `internal-link` | |
| `HiddenText` | `hidden-text` | |
| `Formatting` | `formatting` | `colour`, `font`, `size`, `symbol` |
| `Macro` | `macro` | |
| `Element` | `element` | The name of the HTML element |
| `Style` | `style` | The name of the paragraph style the [style map](styles.md) leaves out |
| `Block` | `block` | For an export: the type of the block a word processing document has no place for, `link` |
| `Annotation` | `annotation` | For a PDF: `form`, and the subtype of the annotation — `widget`, `stamp`, `fileattachment`… |

`Omitted::description()` gives a sentence in English for each kind; an application that shows the report in another
language translates by the value.

## Rendering the report with the document

A person who reads an imported text should know what it lost. `Report::summary()` gives one sentence per kind, its
details with their counts — `Colours, fonts and sizes are not imported: colour (2), font (1).` —, and
`Report::toBlock()` puts those sentences, one per line and escaped, in a `warning` block whose title is given.
`Imported::documentWithReport()` gives the document with that block at its end, under an identifier no other block
uses, and the document as it is when nothing was left out:

```php
$json = new Ujo()->encode($imported->documentWithReport('Not imported from the manuscript'));
```

A report is a measure of fidelity, not an error: an import with omissions has succeeded.
