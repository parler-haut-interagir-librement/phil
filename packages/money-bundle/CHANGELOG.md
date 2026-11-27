# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/money-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.3-alpha.

### Changed

- The ORM mapping of `Money` is written in Doctrine attributes, on `Infrastructure\Doctrine\MoneyEmbeddable`, instead
  of `config/doctrine/Money.orm.xml`, which is removed: `Infrastructure\Doctrine\MoneyMappingDriver` reads them for
  `Money`, and a compiler pass of the bundle adds it to the default entity manager when the ORM is configured and
  `doctrine_mapping` is on (`PhilMoneyBundle::DOCTRINE_MAPPING`). The columns are unchanged. Nothing is prepended to
  `doctrine.orm.mappings` any more; an application that declared the `PhilMoney` mapping on another entity manager
  adds the driver with `DoctrineOrmMappingsPass` instead.
- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

## 0.1.2-alpha - 2026-11-20

Swiss amounts are read whichever apostrophe groups them.

### Fixed

- `LocalizedDecimal::parse()` reads the typographic apostrophe `’` as the plain one `'`, in the input and in the
  locale's grouping separator: `1'234'567.5` and `1’234’567.5` are both read in `de_CH`, whether the ICU of the
  server groups that locale by one or the other.

## 0.1.1-alpha - 2026-11-17

Documentation only.

### Changed

- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.

## 0.1.0-alpha - 2026-11-11

### Added

- The `phil_money` configuration — `currencies`, `default_currency`, `doctrine_mapping` — checked against the
  currency data, and `AcceptedCurrencies`. An open `currencies` list offers, through `AcceptedCurrencies::all()` and
  `MoneyType`, only the currencies in current use (legal tender in at least one country today), computed once, and
  accepts every known currency.
- The `phil_currency` Doctrine column type and the mapping of `Phil\Money\Money` as an ORM embeddable.
- `MoneyType`: an amount read and written in the request's locale without a float, and a currency choice when more
  than one is offered; a `MoneyType` fixed to one currency saves the submitted amount in that currency, even when
  the form was filled from an amount in another one. Its model transformer `MoneyTransformer` and
  `LocalizedDecimal`, whose `parse()` accepts grouping only in groups of the locale's sizes before the decimal
  point: `12,50` in `en`, `12.50` in `de` and `1,2,3` are refused.
- The `AcceptedCurrency` and `MoneyRange` constraints, with English and French messages, and the French message of
  an unreadable amount. `MoneyRange` refuses a bound that is not a decimal when it is built, and rounds a bound finer
  than the money's currency towards the inside of the range, so that no submitted amount makes it throw.
- The `format_money` Twig filter, which with `LocalizedDecimal` builds one ICU formatter per locale and keeps those
  of 64 locales at most, and the Twig functions `money_schema` and `price_schema`: schema.org `MonetaryAmount` and
  `Offer` price for JSON-LD.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
