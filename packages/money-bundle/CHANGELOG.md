# Changelog

All notable changes to `phil/money-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
