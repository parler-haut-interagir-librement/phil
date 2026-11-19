# Output formats

| Writer | Format | For |
|---|---|---|
| `BrfWriter` | BRF: North American ASCII braille | Embossers and braille notetakers |
| `PefWriter` | PEF 2008-1: the Portable Embosser Format of the DAISY Consortium | Embossing software, archives, exchange |
| `UnicodeWriter` | Unicode braille text | A preview on a screen |

## BRF

```php
$brf = new BrfWriter()->write($book);
```

Each cell is the printable ASCII character North American ASCII braille gives its dots — `A` for dot 1, `,` for dot
6, `#` for dots 3-4-5-6 —, each row ends with a carriage return and a line feed, each page with a form feed. The file
is ASCII, sent to an embosser as it is; the page format of the book is the one the embosser must be set to.

BRF has six dots: a cell with dot 7 or 8 throws `UnrepresentableCell`, naming the page and the row.

## PEF

```php
use Phil\BrailleExport\Output\PefMetadata;

$pef = new PefWriter()->write($book, new PefMetadata(
    identifier: 'urn:isbn:9782070612758',
    creator: 'Antoine de Saint-Exupéry',
    publisher: 'The publisher',
    date: new DateTimeImmutable('2026-11-13'),
));
```

The file keeps the structure of the book: a `volume` element per volume, with the cells per line, the lines per page
and whether it is duplex, a `section` per section, a `page` per page, a `row` per row in Unicode braille. Its Dublin
Core metadata holds the format, `application/x-pef+xml`, the identifier, the title and the language of the book, and
the creator, the publisher and the date when they are given.

Without an identifier, the file takes `urn:sha256:` and the SHA-256 of its body, so that the same book always gets the
same one. The texts of the metadata are escaped, and the characters XML 1.0 cannot hold are left out.

## Unicode braille

```php
$pages = new UnicodeWriter()->pages($book);        // each page, its rows separated by line feeds
$text = new UnicodeWriter()->write($book, "\f");   // the whole book, pages separated as asked
```

The rows are the characters U+2800 to U+28FF; a font with braille patterns shows them as dots. Pages are separated by
a blank line unless another separator is given.
