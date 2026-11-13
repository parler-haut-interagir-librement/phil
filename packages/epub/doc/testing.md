# Testing

## The library's tests

The suite `tests/Unit` writes books in memory and reads the archive back: the order and compression of the entries,
their dates and reproducible bytes, the exact package document, navigation document and content documents for small
books, every metadata, the accessibility metadata and what the writer adds, links and resources in every case, the
table of contents, landmarks and page list, each validation and its message, the XHTML conversion of every construct,
font obfuscation against the key bytes, the splitting of fragments and UJO documents.

From the monorepo root:

```sh
vendor/bin/phpunit packages/epub/tests
```

The library is held to 100 % covered mutation score by Infection.

## EPUBCheck

EPUBCheck, the validator of the W3C, checks four books: one with every text feature of the library, one with audio,
video, a text track, a fallback, a media overlay and the EPUB 2 table of contents, one in fixed layout, and one made
from a UJO document. It takes about twenty seconds a book, so the test runs only when `EPUBCHECK` names the executable:

```sh
brew install epubcheck          # or download it from the W3C and run it with Java
EPUBCHECK=$(command -v epubcheck) vendor/bin/phpunit packages/epub/tests --group epubcheck
```

The test runs EPUBCheck with `--failonwarnings`: a warning fails it. To check a book of an application:

```sh
epubcheck book.epub
```

## Testing code that writes books

Write the book in memory and open the archive with `ZipArchive`, or compare files of the package:

```php
use Phil\Epub\EpubWriter;

$bytes = new EpubWriter()->toString($book);
$path = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($path, $bytes);
$zip = new ZipArchive();
$zip->open($path);

self::assertStringContainsString('<dc:title>The Lighthouse Keeper</dc:title>', $zip->getFromName('EPUB/package.opf'));
```

The output is deterministic: the same book, with the same modification time, gives the same bytes.
