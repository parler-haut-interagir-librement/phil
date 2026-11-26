# Changelog

All notable changes to `phil/commerce-wholesale-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Added

- `CreditShipmentAuthorization`: with `phil/commerce-logistics-bundle`, an order whose buyer — its customer — has an
  active credit account in its currency, whose available credit covers what is left to pay, may leave before it is paid;
  registered as an authorization of the logistics, and only an answer: the purchase is owed once `CreditSales::charge()`
  puts it on the account.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8), and of each development requirement
  (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Changed

- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.

## 0.1.0-alpha - 2026-11-20

### Added

- `CustomerGroup` and `Membership`, `WholesaleRefused`; `CustomerGroups`; `GroupPrices`, the core's
  `UnitPriceResolver`, asked after the catalogue's price books.
- Quantity rules: `QuantityRule`, a minimum, a maximum and an increment per SKU for every group or one, `QuantityBreach`,
  `QuantityRuleRepository` and `QuantityRules`; the Validator's constraint `AllowedQuantity` on a `RequestedQuantity`,
  with `AllowedQuantityValidator` and its messages in English and French in `translations/`; `QuantityGuard`, the
  core's `UnitPriceResolver` asked before every price, which refuses with `QuantityRefused` a line its rule does not
  allow; the bundle requires `symfony/validator`.
- Sales on credit: `CreditAccount`, `Receivable`, `CreditRepository`, `CreditSales`, and `AccountGateway`, the
  core's `PaymentGateway` for `on_account`, whose `refund()` keeps on the receivable what the buyer already paid of a
  refunded amount; the bundle requires `symfony/uid`. A refund of a purchase on account beyond what is outstanding is
  owed back to the buyer: `Receivable::refundDue()`, `refund()` and `settleRefund()`, and its `refund_due` column;
  `CreditSales::refundsDue()` lists it and `CreditSales::settleRefund()` records it given back.
- `CustomerGroupRepository` and `DoctrineWholesaleRepository`, which also stores the quantity rules.
- `PhilCommerceWholesaleBundle`, extension alias `phil_commerce_wholesale`, which refuses to load without
  `PhilCommerceBundle`.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README; the installation guide lists
  `ext-mbstring` among the requirements.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- Refunds under an idempotency key, the one of the core's `PaymentGateway::refund()`: `AccountGateway` records the
  refund on the payment under its key before it credits the receivable, so that a refund asked again credits nothing
  more.
