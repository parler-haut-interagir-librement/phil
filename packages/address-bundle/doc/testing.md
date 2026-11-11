# Testing

| Area | What is covered |
|---|---|
| `CountryFields`, `KnownCountries` | Fields, labels, recipients, subdivisions in languages |
| `AddressType`, `AddressTransformer` | Default and submitted countries, hidden fields, choices, errors per field, offered countries |
| `ValidAddress`, `AddressColumnType`, `AddressExtension`, `AddressFormatController`, `PhilAddressBundle` | Violations by path, JSON, filters in languages, the endpoint, the configuration |
| The Stimulus controller | Rows shown, labelled, required, choices and text fields, fetching, under Node's test runner |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle in
Canada: `AddressBundleTest` stores and validates an address, renders the form in `fr_CA` with its controller, and
reads the endpoint. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/address-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/address-bundle
```
