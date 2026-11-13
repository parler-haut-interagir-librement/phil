# The catalogue

## Reading a file

`FontFiles` reads font files with the Brotli decompressor PHP has. The service is built by
`FontFiles::withAvailableBrotli()`, which passes `brotli_uncompress()` when `ext-brotli` is loaded; `readsWoff2()` says
whether it is.

| Method | Returns |
|---|---|
| `read(string $path)` | The `FontFile` of a path |
| `readBytes(string $bytes, string $name)` | The `FontFile` of bytes, named for the message of a file that cannot be read |
| `readsWoff2()` | Whether WOFF2 files are read |

A file that cannot be read throws `FontUnreadable`, whose message names the file and carries the library's reason, and
whose previous exception is the library's: a WOFF2 file without `ext-brotli` says
`The font file "Sans.woff2" cannot be read: A WOFF2 file compresses its tables with Brotli, which PHP does not decode
without ext-brotli: install ext-brotli and pass Brotli::decompressor(), or read the WOFF, TrueType or OpenType file of
the font.`

## The files of the directory

`FontCatalogue` reads every `.ttf`, `.otf`, `.ttc`, `.otc`, `.woff` and `.woff2` file of the configured directory and its
folders, extension case aside, the first time it is asked, and keeps them for the rest of the request or command. A
directory that does not exist holds no font.

`entries()` gives one `CatalogueEntry` per file, sorted by path:

| Property or method | What it holds |
|---|---|
| `path` | The path relative to the directory, with `/` between folders |
| `absolutePath` | The path on disk |
| `format` | The `Format` of the file once read; the one its extension announces when it could not be read |
| `file` | The `FontFile`, or null |
| `error` | Why the file could not be read, or null |
| `isReadable()` | Whether `file` is there |
| `stem()` | The path without its extension: the files of one face in several formats share it |

`unreadable()` lists the entries that could not be read, `get($path)` gives one entry or throws `FontNotFound`.

## Finding a face

```php
$entry = $catalogue->find('Example Sans', 700, FontStyle::Italic, Format::Woff2, Format::TrueType);
```

`find()` gives the first file, by path, with a face of the family — case aside —, the weight — anywhere in the range of
a variable font's `wght` axis — and the style. With formats, it looks for each in their order and gives the first that
has the face; null when none has. `require()` takes the same arguments and throws `FontNotFound` in place of null;
`face()` gives the `Font` itself, the face of a collection among the others.
Files that could not be read are never found.

## Licence records

`licences(LicenceRegister $kept)` gives a register of the files that were read. A file that could not be read keeps
the record of `$kept`, when it has one: a WOFF2 file recorded where `ext-brotli` is loaded is not reported as removed
where it is not. [Licences](licences.md) uses it.
