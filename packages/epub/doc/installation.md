# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_any()`, `new` without parentheses, `Dom\HTMLDocument` |
| `ext-dom` | The HTML5 parser that reads each chapter before it is written as XHTML |
| `ext-zip` | The ZIP container |
| `phil/ujo` (optional) | UJO documents turned into chapters, through `Ujo\UjoChapters` |

The library has no other dependency: no binary, no service. EPUBCheck, which validates the output, is only needed to
run the validation tests.

## Installing

```sh
composer require phil/epub
composer require phil/ujo   # only to write UJO documents
```

It is a library, not a bundle: there is nothing to register. Build a `Book` where a book is exported, and write it:

```php
use Phil\Epub\EpubWriter;

new EpubWriter()->write($book, '/path/to/book.epub');
$bytes = new EpubWriter()->toString($book); // for a download response
```

An `EpubWriter` holds no state: one instance can write every book. `toString()` writes through a temporary file, in
the directory given to the constructor or the system's temporary directory, and removes it.
