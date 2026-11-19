# Layout

`BrailleExporter::layout()` translates the texts of a document in one batch, then cuts them into lines and pages:

```php
$book = new BrailleExporter($translator, new PageFormat(cellsPerLine: 32, linesPerPage: 26))
    ->layout($document, Contraction::Contracted);
```

The `Book` it gives holds volumes, each of sections, each of pages, each of rows of Unicode braille, page numbers
included, no row ending with a blank cell. The writers of [Output formats](output-formats.md) read it.

## The page format

| `PageFormat` argument | Default | Meaning |
|---|---|---|
| `cellsPerLine` | 40 | The cells of a line, from 12 to 200. |
| `linesPerPage` | 25 | The lines of a page, from 3 to 200. |
| `braillePageNumbers` | `true` | Whether the braille page number ends the last line of each page. |
| `printPageNumbers` | `true` | Whether the print page ends the first line of each page, and a line shows where one starts. |
| `pagesPerVolume` | `null` | The pages of a volume; one volume without it. |
| `duplex` | `false` | Whether the pages are embossed on both sides, as a PEF file says. |
| `maxPages` | 10,000 | The most pages a document may fill. |

40 cells by 25 lines is the page of an embosser on 11 by 11.5 inch paper; the format of the book is the one its
embosser is set to.

## Blocks

Cells are counted from 1, at the left margin.

| Block | First line | Next lines | Around it |
|---|---|---|---|
| Paragraph | cell 3 | cell 1 | — |
| A line break within a paragraph | cell 1 | cell 1 | — |
| Heading, level 1 | centred, at least 3 blank cells on each side | centred | a blank line before, unless it starts a page, and after |
| Heading, level 2 | cell 5 | cell 5 | a blank line before, unless it starts a page |
| Heading, levels 3 to 6 | cell 7 | cell 7 | a blank line before, unless it starts a page |
| List item, level 1 | cell 1 | cell 3 | — |
| List item, each level further | 2 cells further in | 2 cells further in | — |
| Table caption | cell 3 | cell 1 | — |
| Table row | cell 1 | cell 3 | — |

A heading goes to the next page when the page cannot also hold the line that follows it. An ordered list writes the
number of each item, followed by a point, before its text. Nesting moves a list in down to the ninth level; deeper
ones stay there, and a line always keeps two cells for its text.

A table cannot keep its columns on a braille line, so each row becomes one entry: its cells separated by
semicolons, each preceded by the heading of its column and a colon when the first row holds the headings — `Name: Ann;
Age: 12` in English, `Nom : Ann; Âge : 12` in French. An empty cell is left out.

## Cutting lines

A word that does not fit goes whole to the next line. A word longer than a whole line is cut:

- after a hyphen it holds, the last one that fits, when a cell comes before it and one after it: the line ends with
  the word's own hyphen and no second one is added;
- otherwise before the last cell of the line, with the hyphen dots 3-6 added, never right after a cell that announces
  the next one — a capital sign, a number sign, the grade 1 indicator or the first cell of a composed sign.

A word that holds a hyphen is also cut after it when its first part fits the end of a line.

## Page numbers

The braille page number, as the translator writes the number, ends the last line of each page with at least three
blank cells before it; the line leaves it that room. The numbers are asked of the translator 64 pages at a time.

A `PrintPage` marker sets the print page. Its number ends the first line of each braille page while it lasts, with the
same room. When a print page starts within a braille page, a line of dots 3-6 shows where, ending with a blank cell and
the new number; when that line would be the last of its page, the next braille page starts instead, with the new
number at the end of its first line. A print page number keeps at most a third of a line.

## Pages, sections and volumes

`PageBreak` starts a new page and `SectionBreak` a new section, never an empty page. A blank line never starts a page.
An empty document is one empty page. With `pagesPerVolume`, the pages are bound in volumes of that many, a section
going on in the next volume when it does not end with its volume. Pages are numbered from 1 through the whole book.
