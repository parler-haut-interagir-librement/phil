# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_find()`, `new` without parentheses |
| `ext-mbstring` | The UTF-16 strings of the `name` table converted to UTF-8 |
| `ext-zlib` | The tables and extended metadata of WOFF files inflated |
| A Brotli decoder, for WOFF2 only | WOFF2 compresses its tables with Brotli, which PHP does not decode on its own |

The library has no other dependency: no binary, no service, no font file of its own.

## Installing

```sh
composer require phil/fonts
```

It is a library, not a bundle: there is nothing to register. Read a file where its metadata is needed:

```php
use Phil\Fonts\FontFile;

$file = FontFile::fromFile($path);
```

## Brotli for WOFF2

Every reading method takes a decompressor as its last argument: a closure that receives the compressed bytes and the
length they must inflate to, and returns the bytes, or `false` when it cannot. The `brotli` PECL extension provides
one, `brotli_uncompress()`, which Composer suggests:

```sh
pecl install brotli
```

`Brotli::decompressor()` gives it when the extension is loaded, and null otherwise, so the same call reads WOFF2 where
the extension is installed and every other format everywhere:

```php
use Phil\Fonts\Brotli;
use Phil\Fonts\FontFile;

$file = FontFile::fromFile($path, Brotli::decompressor());
```

`Brotli::decompressor('my_brotli_decode')` names another function with the same arguments. The library checks the
length of what the decompressor returns, and passes the expected length so that a decoder can stop past it. Without a
decompressor, a WOFF2 file throws `UnsupportedFont` with a message that says what to do — install `ext-brotli`, or
read the WOFF, TrueType or OpenType file of the font —; the other formats never call it.

A Brotli decoder in plain PHP would need the 122 KB static dictionary of RFC 7932 shipped with the library, for one
format that a server can also convert ahead of time: the library leaves it to the extension, or to a decoder the
application already has.
