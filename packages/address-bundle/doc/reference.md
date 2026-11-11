# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilAddressBundle` | `final class`, `AbstractBundle` | Alias `phil_address`, keys `default_country` and `format_url`; the services and the column type |
| `Application\CountryFields` | `final class` | A country's fields and subdivisions as a form shows them |
| `Application\KnownCountries` | `final class` | Whether a value is a country's code |
| `Infrastructure\Form\AddressType`, `AddressTransformer` | form type, data transformer | The address form |
| `Infrastructure\Validator\ValidAddress`, `ValidAddressValidator` | constraint and validator | A valid address |
| `Infrastructure\Doctrine\AddressColumnType` | DBAL type | `phil_address` |
| `Infrastructure\Http\AddressFormatController` | controller | The format endpoint |
| `Infrastructure\Twig\AddressExtension` | Twig extension | `address`, `address_lines`, `address_label`, `country_name`, `address_schema` |
| `assets/src/address_controller.js`, `address.js` | Stimulus controller | The form following the country |
| `config/routes.php` | routes | `phil_address_format` |
