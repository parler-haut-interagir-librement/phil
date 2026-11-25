# Changelog

All notable changes to `phil/address-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- Requires the latest minor of each dependency (`symfony/translation-contracts` 3.8), and of each development
  requirement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.0-alpha - 2026-11-11

### Added

- `CountryFields` and `KnownCountries`.
- `ValidAddress` and its validator.
- `AddressColumnType`.
- `PhilAddressBundle`, extension alias `phil_address`, with the keys `default_country` and `format_url`.
- `AddressType` and `AddressTransformer`: the form keeps only the fields the submitted country writes, so changing
  the country of an address being edited drops the hidden fields of the other country, and a violation on a field
  the form does not show goes to the form. The format URL the form gives the controller asks for the recipient's
  fields with `with_recipient`.
- `AddressFormatController` and `config/routes.php`: the format endpoint takes its language from the `locale` query
  parameter that the form writes in the URL, and is public only then; without it, the answer follows the request's
  language and is private, so a shared cache never serves one language's labels to a person reading another.
- `AddressExtension`, with the Twig function `address_schema`: schema.org data for JSON-LD. The `twig/twig`
  suggestion names the filters and functions; the bundle has no form theme.
- The Stimulus controller `address`, in `assets/`, which applies only the answer for the country still chosen, and
  dispatches `failed` when the request fails.
- The labels and messages in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
