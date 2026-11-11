# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments, and the data are imported rather than configured.

| Point | How |
|---|---|
| The language of an address | The `$locale` of `AddressFormatter::format()`, `lines()`, `postalLabel()` and `Countries::name()`: the country's own language chooses its local format and subdivision names |
| The country mail is sent from | The `$origin` of `AddressFormatter::postalLabel()`: the country line is added from abroad only |
| Stricter rules than the country's | `AddressValidator::violations()` gives what the country's format says; rules of the application's own — a delivery zone, a field it always wants — are checked beside it |
| Where an address is kept | `AddressArray::from()` gives an array for one JSON column, `AddressArray::to()` reads it back |
| Structured data | `SchemaOrg::of()` gives a schema.org `PostalAddress` to embed in the application's JSON-LD |
| The formats and subdivisions | The PHP data in `data/`, imported from a release of commerceguys/addressing by `Data\DataImporter` through `bin/import-data.php`; a newer release is imported, never edited by hand — see [Data](data.md) |
| Catching the library's errors | Every exception implements `Phil\Address\Exception\AddressException` |

Country names come from symfony/intl and follow its releases: an application that names a country otherwise keeps its
own labels.
