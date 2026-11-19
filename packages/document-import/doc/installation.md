# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses, `Dom\XMLDocument`, `array_last()` |
| `ext-dom` | The XML of DOCX, ODT and flat ODT documents, and the HTML5 parser for the images of an HTML paste and the inline HTML of an export |
| `ext-libxml` | The parser options: no network access, no error output |
| `ext-mbstring` | The code pages of RTF and PDF, UTF-16 strings, text alternatives cut to their length |
| `ext-sodium` | Constant-time Base64 for images embedded in ODT and in `data:` addresses, and hexadecimal for RTF pictures and PDF strings |
| `ext-zip` | The DOCX and ODT archives, read and written |
| `ext-zlib` | The Flate streams of a PDF, and the PNG files written from its images |
| `phil/ujo` | The UJO document, checked and put in canonical form |
| `phil/markup` | The Markdown and HTML readers |
| `phil/svg-sanitizer` | SVG images sanitized before they are stored |

The library calls no office suite, no binary and no service.

## Installing

```sh
composer require phil/document-import
```

It is a library, not a bundle: there is nothing to register. Create a `DocumentImporter` where files are received:

```php
use Phil\DocumentImport\DocumentImporter;

$imported = new DocumentImporter()->importFile($upload->getPathname());
```

A `DocumentImporter` holds no state: one instance imports every file, and a `DocumentExporter` exports every
document. An archive — DOCX, ODT — is opened and written in memory on a PHP with `ZipArchive::openString()` and
`ZipArchive::closeString()`; on a PHP without them, it goes through a temporary file in the system's temporary
directory while it is read or written, and the file is removed before `import()` or `export()` returns or throws.
