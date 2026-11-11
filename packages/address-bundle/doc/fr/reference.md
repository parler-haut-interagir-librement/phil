<!-- phil-dev source-sha256=6a8ae45e1704d200e747bb5cd0f5cac02e1b04ab91a029792a092e8aed63f139 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilAddressBundle` | `final class`, `AbstractBundle` | Alias `phil_address`, clés `default_country` et `format_url` ; les services et le type de colonne |
| `Application\CountryFields` | `final class` | Les champs et les subdivisions d'un pays tels que les affiche un formulaire |
| `Application\KnownCountries` | `final class` | Si une valeur est le code d'un pays |
| `Infrastructure\Form\AddressType`, `AddressTransformer` | type de formulaire, transformateur de données | Le formulaire d'adresse |
| `Infrastructure\Validator\ValidAddress`, `ValidAddressValidator` | contrainte et validateur | Une adresse valide |
| `Infrastructure\Doctrine\AddressColumnType` | type DBAL | `phil_address` |
| `Infrastructure\Http\AddressFormatController` | contrôleur | Le point de terminaison des formats |
| `Infrastructure\Twig\AddressExtension` | extension Twig | `address`, `address_lines`, `address_label`, `country_name`, `address_schema` |
| `assets/src/address_controller.js`, `address.js` | contrôleur Stimulus | Le formulaire qui suit le pays |
| `config/routes.php` | routes | `phil_address_format` |
