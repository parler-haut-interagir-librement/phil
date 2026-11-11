# Reference

| Type | Kind | Role |
|---|---|---|
| `PhoneNumber` | `final readonly class` | A number: parsing, region, type, validity, formats |
| `PhoneNumberType` | string-backed enum | What a number is |
| `PhoneFormat` | string-backed enum | The ways a number is written |
| `Metadata\Territories` | `final class` | The numbering plans, read from `data/` as they are asked for |
| `Metadata\Territory` | `final readonly class` | A country's plan: prefixes, types, formats |
| `Metadata\NumberDesc` | `final readonly class` | The numbers of one type: pattern and lengths |
| `Metadata\NumberFormat` | `final readonly class` | How numbers are grouped |
| `Metadata\MetadataImporter` | `final class` | libphonenumber's XML to PHP data |
| `Exception\PhoneException` | interface | Every exception of the library |
| `Exception\InvalidPhoneNumber` | `final class`, `InvalidArgumentException` | An input that is no number, or a region with no plan |
| `Exception\InvalidMetadata` | `final class`, `RuntimeException` | Metadata that is not libphonenumber's |
