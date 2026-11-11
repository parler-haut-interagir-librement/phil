# Reference

| Type | Kind | Role |
|---|---|---|
| `Address` | `final readonly class` | A country and its fields |
| `AddressField` | string-backed enum | The parts of an address |
| `AddressFormat`, `AddressFormats` | `final readonly class`, `final class` | Each country's format |
| `Subdivision`, `Subdivisions` | `final readonly class`, `final class` | States, provinces, cities, districts |
| `Countries` | `final class` | Country codes and names, from symfony/intl |
| `AddressValidator`, `AddressViolation`, `Violation` | `final class`, `final readonly class`, enum | What is wrong with an address |
| `AddressFormatter` | `final class` | An address written as its country writes it |
| `AddressArray` | `final class` | An address to and from an array, for storage |
| `SchemaOrg` | `final class` | An address as a schema.org `PostalAddress`, for JSON-LD |
| `Data\DataImporter` | `final class` | commerceguys/addressing's data to PHP data |
| `Exception\AddressException` | interface | Every exception of the library |
| `Exception\InvalidAddress`, `InvalidData`, `UnreadableAddress` | `final class` | A bad address, bad data to import, a stored address unreadable |

