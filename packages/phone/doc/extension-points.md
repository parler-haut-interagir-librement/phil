# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments.

| Point | How |
|---|---|
| The country of a number typed without its prefix | The `$region` argument of `PhoneNumber::parse()`: the visitor's country, the shop's, or none to require a `+` |
| Which numbers are accepted | `isValid()`, `type()` and `region()` give what a number is; the application decides which types and countries it takes |
| How a number is written | `format()` with a `PhoneFormat`: E.164 to store, international or national to show, `tel:` to link |
| The numbering plans | The PHP data in `data/`, imported from a release of libphonenumber by `Metadata\MetadataImporter` through `bin/import-metadata.php`; a newer release is imported, never edited by hand — see [Metadata](metadata.md) |
| Catching the library's errors | Every exception implements `Phil\Phone\Exception\PhoneException`: one `catch` covers `InvalidPhoneNumber` and `InvalidMetadata` |

Carriers, short numbers and example numbers are not read: an application that needs them looks them up elsewhere
from the E.164 form.
