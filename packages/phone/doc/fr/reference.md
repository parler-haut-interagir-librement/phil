<!-- phil-dev source-sha256=13e93fd2442a738e6c401183275f53d5130661d075ae143de4cbc2f915f2309f -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhoneNumber` | `final readonly class` | Un numéro : analyse, région, type, validité, formats |
| `PhoneNumberType` | enum adossée à une chaîne | Ce qu'est un numéro |
| `PhoneFormat` | enum adossée à une chaîne | Les façons d'écrire un numéro |
| `Metadata\Territories` | `final class` | Les plans de numérotation, lus dans `data/` à mesure qu'on les demande |
| `Metadata\Territory` | `final readonly class` | Le plan d'un pays : préfixes, types, formats |
| `Metadata\NumberDesc` | `final readonly class` | Les numéros d'un type : motif et longueurs |
| `Metadata\NumberFormat` | `final readonly class` | La façon de grouper les numéros |
| `Metadata\MetadataImporter` | `final class` | Le XML de libphonenumber vers des données PHP |
| `Exception\PhoneException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidPhoneNumber` | `final class`, `InvalidArgumentException` | Une entrée qui n'est pas un numéro, ou une région sans plan |
| `Exception\InvalidMetadata` | `final class`, `RuntimeException` | Des métadonnées qui ne sont pas celles de libphonenumber |
