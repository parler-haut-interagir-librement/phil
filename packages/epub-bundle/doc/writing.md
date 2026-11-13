# Writing books

## `Epubs`

| Method | Returns |
|---|---|
| `write(Book $book)` | The bytes of the EPUB file |
| `store(Book $book, string $path)` | Where the storage keeps it, after writing it under a relative path such as `stories/42.epub` |
| `build(string $source, string $id)` | The book a source builds from an identifier |
| `sources()` | The `BookSources`, to list their names |

A controller that offers a download writes the bytes into a response with the `application/epub+zip` media type; the
library gives the same bytes for the same book, so the response can be cached on the book's modification time.

## Book sources

A class implementing `BookSource`, marked `#[AsBookSource('name')]` and autoconfigured, builds a book of the application
from an identifier:

```php
#[AsBookSource('rulebook')]
final readonly class RulebookBooks implements BookSource
{
    public function build(string $id): Book { /* read the records, return the Book */ }
}
```

The services are tagged `phil_epub.source` with their name; `BookSources::get()` throws `UnknownBookSource` for a name
none is registered under, the names it knows in the message.

## The storage

`DirectoryStorage` keeps books under their relative path in `storage.directory` and returns the full path; a path that
is empty, absolute, holds `..` or a null byte throws `StorageFailed`, as does a file that cannot be written.

`MediaLibraryStorage` uploads each book to the media library of `phil/media-bundle` as a new medium named after the last
segment of the path, in the collection `storage.media_collection` when one is set, and returns the medium's identifier.
A collection the library does not have, or a medium the library refuses, throws `StorageFailed`.

## The command

```sh
bin/console phil:epub:write <source> <id> [--output=FILE]
```

Without `--output`, the book is kept in the storage under `<source>/<id>.epub` and the command prints where; with it,
the file is written there. An unknown source, a book the library refuses, a storage or a file that cannot be written
print the reason and exit with 1.
