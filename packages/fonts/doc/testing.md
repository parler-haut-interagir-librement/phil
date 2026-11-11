# Testing

## The library's tests

The suite `tests/Unit` writes its fonts byte by byte — `FontBuilder` in `tests/Unit/Fixture` builds an `sfnt` from its
tables, collections, WOFF files compressed with zlib and WOFF2 files read back with an identity decompressor, and the
`name`, `OS/2`, `head`, `post`, `fvar` and `cmap` tables — so it depends on no font file. It covers each container and
each way a file can be broken, the choice of names and the Mac Roman decoding, the defaults and ranges of every value,
the embedding bits one by one and together, the character runs and their merging, the CSS written exactly, its
escaping, and the register through a temporary directory and its JSON.

From the monorepo root:

```sh
vendor/bin/phpunit packages/fonts/tests
```

The library is held to 100 % covered mutation score by Infection.

The containers were also checked against real files: system fonts, collections included, and WOFF2 files made by
Google's `woff2_compress` and read through the `brotli` command line tool as decompressor.

## Testing code that reads fonts

Build a `Font` directly rather than a file when the code only reads its metadata; every constructor argument has a
default:

```php
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\Font;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;

$font = new Font(
    names: new Names([new NameRecord(3, 1, 0x0409, 1, 'Example Sans')]),
    weight: 700,
    embedding: new EmbeddingPermissions(0x0002),
);
```

The `@font-face` rules and the register's JSON are deterministic: compare them exactly. A test that needs a WOFF2 file
can pass `static fn (string $data): string => $data` as the decompressor and store its tables uncompressed.
