# Documents

A `Document` holds its blocks in reading order, the BCP 47 tag of its language, which chooses the braille code, and a
title, written into the metadata of a PEF file.

```php
new Document($blocks, 'fr-CA', 'The title');
```

## The blocks

| Block | What it is |
|---|---|
| `Heading($text, $level)` | A heading of level 1 to 6. |
| `Paragraph($text)` | A paragraph; a line break of its text, `\n`, starts a new line. |
| `ListBlock($items, $ordered, $start)` | A list of `ListItem($text, $children)`, each with the list nested under it; an ordered list numbers its items from `$start`. |
| `Table($rows, $headerRow, $caption)` | A table: its rows, each a list of the texts of its cells, whether the first row holds the headings, and a caption. |
| `PageBreak` | The next block starts a new braille page. |
| `SectionBreak` | The next block starts a new section, on a new page: a `section` of a PEF file. |
| `PrintPage($number)` | A page of the print edition starts here, its number as printed, such as `12` or `iv`. |

Every block implements `Contract\Block`. The layout knows the blocks above; a block of another class is left out.

The texts are plain text. White space and control characters fold to single spaces, and a text that holds nothing but
white space gives nothing.

## A UJO document

`UjoReader` reads a UJO (Universal JSON Output) document of `phil/ujo` into a document:

```php
use Phil\BrailleExport\Ujo\UjoReader;
use Phil\Ujo\Ujo;

$document = new UjoReader()->read(Ujo::decode($json), 'fr', 'The title');
```

| UJO block | Block |
|---|---|
| `header` | `Heading`, its level kept between 1 and 6 |
| `paragraph` | `Paragraph`, its HTML read as text, a `<br>` a line break |
| `list` | `ListBlock`, nested items and the start of an ordered list kept |
| `table` | `Table`, `withHeadings` as its header row, its caption kept |
| `quote` | A paragraph of the quote, then one of its caption |
| `pageBreak` | `PageBreak` |
| `delimiter` | Nothing: a braille reader does not need it |
| Any other | The paragraphs of its plain text, as `phil/ujo`'s `PlainText` writes it; nothing when it has none |

A `PlainText` given to the constructor changes or adds the text of a type, as `PlainText::with()` does.
