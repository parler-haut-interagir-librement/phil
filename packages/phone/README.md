# phil/phone

**Phone numbers read as people type them, checked against their country's plan, and formatted.**

`phil/phone` reads a number into a `PhoneNumber`, with or without its country code, spaced, dotted or with an
extension. It checks the number against its country's numbering plan and tells its type: mobile, landline,
toll-free and others. It writes the number in E.164, international, national and RFC 3966 `tel:` forms. **It does
not** guess a carrier, a time zone or a place, read numbers out of free text, or format as people type.

It is for PHP applications that store contact numbers and must show and dial them correctly across countries. The
numbering plans are libphonenumber's, 254 territories imported as PHP data by the library's own `MetadataImporter`;
the parsing and formatting are the library's own code. The library depends on no other package, only on `ext-ctype`,
`ext-dom` and `ext-mbstring`, and its plans follow libphonenumber's releases by running the importer again. The
imported data stays under the Apache License 2.0, so the package is under MIT and Apache-2.0.

In a Symfony application, reach for `phil/phone-bundle`: a form field, a validation constraint, a Doctrine column type
and Twig filters. `phil/instant-messenger` uses `phil/phone` to read the phone numbers that identify WhatsApp, Signal
or Viber handles. The design is a value type over plain data: a `PhoneNumber` keeps a country code, a national number
and an extension, and the library reads only the parts of the plans it uses.

## Status

| Part | State |
|---|---|
| `PhoneNumber::parse()`: country codes, international and national prefixes, extensions, `tel:` | Implemented and tested |
| `region()`, `type()`, `isValid()` against the numbering plans | Implemented and tested |
| `format()`: E.164, international, national, RFC 3966 | Implemented and tested |
| `MetadataImporter`: libphonenumber's XML to PHP data, 254 territories | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Parsing](doc/parsing.md) | What `parse()` reads, regions, extensions, refusals. |
| [Validation and types](doc/validation.md) | `isValid()`, `region()`, `type()`, and what each type means. |
| [Formatting](doc/formatting.md) | The four formats, extensions, and storing a number. |
| [Metadata](doc/metadata.md) | Where the numbering plans come from, their licence, and updating them. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/phone
```

```php
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;

$number = PhoneNumber::parse('06 12 34 56 78', 'FR');
$number->isValid();                          // true
$number->type();                             // PhoneNumberType::Mobile
$number->format(PhoneFormat::International); // "+33 6 12 34 56 78"
(string) $number;                            // "+33612345678"
```

## Requirements

PHP 8.5 or later with `ext-ctype`, `ext-dom` and `ext-mbstring`. `parse()` measures text with `ext-mbstring`;
`ext-dom` and `ext-ctype` serve only the importer, but Composer requires them with the library.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

The code is MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project.
See [LICENSE](LICENSE). The numbering plans in `data/` come from libphonenumber, under the Apache License 2.0: see
[NOTICE](NOTICE) and [data/LICENSE](data/LICENSE).
