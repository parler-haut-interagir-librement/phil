# phil/address

**Postal addresses checked and written the way each country writes them.**

phil/address holds a postal address as a value object and knows the address format of each country. A format gives
the order of the fields, the ones the country requires, the pattern of its postal codes, what it calls its states and
its towns, and its lines in its own script. The library knows subdivisions down to three levels, with their local
names. `AddressValidator` checks an address against its country: missing or unused fields, postal codes, unknown
subdivisions. `AddressFormatter` writes it for a page or for a postal label. `SchemaOrg` gives a `PostalAddress`, and
`AddressArray` an array for one JSON column. It does not geocode, autocomplete, or check that a street exists.

It is for any application that takes addresses from more than one country: a shop that ships abroad, a directory of
places, an event's venue. A single set of fields with a "state" and a "ZIP code" fits one country badly and the
others worse. Here, a Québec address and a Japanese one each follow their own rules.

It is a plain PHP library, usable in any PHP code. In a Symfony application, reach for `phil/address-bundle`, which
adds a form that follows the country chosen, a validation constraint, a Doctrine column type and Twig filters.

The formats and subdivisions are those of commerceguys/addressing, imported as PHP files by the library's own
`DataImporter`. The library reads those files at run time and never depends on commerceguys/addressing. Country names
come from `symfony/intl`, in every language it has.

## Status

| Part | State |
|---|---|
| `Address`, `AddressField`, `AddressFormat`, `AddressFormats` | Implemented and tested |
| `Subdivision`, `Subdivisions`: three levels, local names | Implemented and tested |
| `AddressValidator`: required, unused, postal codes, subdivisions | Implemented and tested |
| `AddressFormatter`: lines, local formats, postal labels | Implemented and tested |
| `SchemaOrg`: a schema.org `PostalAddress` | Implemented and tested |
| `AddressArray`; `DataImporter` and the data of commerceguys/addressing v2.3.2 | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Addresses and formats](doc/formats.md) | `Address`, the fields, each country's format. |
| [Subdivisions](doc/subdivisions.md) | States, provinces, cities and districts, and their names. |
| [Checking and writing](doc/checking-and-writing.md) | `AddressValidator`, `AddressFormatter`, schema.org, storage. |
| [Data](doc/data.md) | Where the data come from, their licence, and updating them. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/address
```

```php
use Phil\Address\Address;
use Phil\Address\AddressFormatter;
use Phil\Address\AddressValidator;

$address = Address::of('CA', [
    'addressLine1' => '1234 rue Saint-Denis',
    'locality' => 'Montréal',
    'administrativeArea' => 'Québec',
    'postalCode' => 'H2X 3K8',
]);

AddressValidator::isValid($address);       // true
AddressFormatter::format($address, 'fr');  // "1234 rue Saint-Denis\nMontréal QC H2X 3K8"
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `symfony/intl` 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). The data in `data/` come from commerceguys/addressing, under the MIT License: see [NOTICE](NOTICE)
and [data/LICENSE](data/LICENSE).
