<!-- phil-dev source-sha256=695a9308f57b2828245361821306ab64fae00797fbb99150dfdf1abfedb545be -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Address` | `final readonly class` | Un pays et ses champs |
| `AddressField` | enum adossée à des chaînes | Les parties d'une adresse |
| `AddressFormat`, `AddressFormats` | `final readonly class`, `final class` | Le format de chaque pays |
| `Subdivision`, `Subdivisions` | `final readonly class`, `final class` | États, provinces, villes, quartiers |
| `Countries` | `final class` | Codes et noms de pays, tirés de symfony/intl |
| `AddressValidator`, `AddressViolation`, `Violation` | `final class`, `final readonly class`, enum | Ce qui ne va pas dans une adresse |
| `AddressFormatter` | `final class` | Une adresse écrite comme l'écrit son pays |
| `AddressArray` | `final class` | Une adresse vers et depuis un tableau, pour le stockage |
| `SchemaOrg` | `final class` | Une adresse sous forme de `PostalAddress` de schema.org, pour le JSON-LD |
| `Data\DataImporter` | `final class` | Les données de commerceguys/addressing transformées en données PHP |
| `Exception\AddressException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidAddress`, `InvalidData`, `UnreadableAddress` | `final class` | Une mauvaise adresse, de mauvaises données à importer, une adresse stockée illisible |
