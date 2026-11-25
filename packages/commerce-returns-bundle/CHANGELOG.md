# Changelog

All notable changes to `phil/commerce-returns-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Added

- `LogisticsRestocking`, the `Restocking` of `phil/commerce-logistics-bundle` 0.2 or later, enabled by the
  configuration `logistics_restocking` — off by default — and its `location`, null for where each SKU left from: the
  resellable units of a product line go back in its stock under the return's order, at the cost they left at when its
  costing is on. The package conflicts with the logistics module below 0.1.1.

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Changed

- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `symfony/event-dispatcher-contracts` 3.8, `symfony/translation-contracts` 3.8), and of each development requirement
  (`doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

## 0.1.0-alpha - 2026-11-20

### Added

- `ReturnRequest`, `ReturnLine`; `ReturnState` — with `AwaitingInformation` and `Expired` —, `ReturnReason`,
  `ItemCondition`, `Resolution`, `DeliveryRefund`; `ReturnRefused`. A return keeps the discounts of the specials it
  breaks (`specialCharges()`), the delivery it gives back (`deliveryRefund()`), its `ReturnLabel` and its slip
  number, and erases its free text on `anonymize()`.
  `ReturnLine` keeps the units of its order line earlier returns hold (`returnedBefore`, `returned_before` in the
  stored lines): returns of one line in several parts add up to what the line came to, to the minor unit.
- `Returns`, `Settlement`, `ReturnPolicy`; `ReturnsGateway`, the core's `PaymentGateway` for the method `exchange`.
  `ReturnValuation`: a set comes back whole, a special's discount disappears when one of its products comes back —
  taxes it saved included, pair by pair, never below zero —, and the delivery charge comes back once per order as
  `delivery_refund` says. `Returns::askForInformation()`, `provideInformation()`, `expire()`, `label()` and
  `anonymize()`; the window from the last shipment (`window_from`); a rate limit per customer (`rate_limiter`).
  `Returns::refund()` and `exchange()` skip the order's payments of method `exchange`, whose refund moves no value;
  their part is in the shortfall. A refund a gateway fails goes to `Settlement::$failures`, so the refunds already
  sent stay recorded with the return's resolution. `Returns::request()` reads each holding return's lines, the
  order's lines and its adjustments once per request: its cost is linear in lines and returns.
- The ports `Restocking`, tagged `phil_commerce_returns.restocking` by autoconfiguration and given the order line's
  reference read back by `SellableReference::fromValue()`, a configured line's fingerprint included, and
  `StoreCreditGranter`, with `StoredValueStoreCredit` on `phil/commerce-stored-value-bundle`.
- `ReturnCreditNotes` and `ReturnCreditNote`: the credit note of a return, part of a line included, numbered in the
  core's series of credit notes. `ReturnSlips`, `ReturnSlip` and `ReturnDocumentTypes`: the slip of an authorized
  return, numbered in the series `return_slip`, drawn by `PagedMediaReturnSlips` on `phil/paged-media`, with the
  translations of the domain `phil_commerce_returns` in English and French.
- The events `ReturnStateChanged`, `ReturnSettled` and `ReturnCreditNoteIssued`, identifiers and amounts only.
- The ports `SetParts`, `SpecialDiscounts`, `ShipmentDates`, `ReturnLabels` and `ReturnSlipRenderer`, with
  `CatalogSetParts`, `PromotionSpecials` and `LogisticsShipmentDates`, registered beside their modules.
- `ReturnRepository` and `DoctrineReturnRepository`, with the authorized returns waiting since a moment and the
  credit notes.
- `PhilCommerceReturnsBundle`, extension alias `phil_commerce_returns` (`window_days`, `window_from`,
  `restocking_fee`, `delivery_refund`, `expiry_days`, `rate_limiter`, `slips`), which refuses to load without
  `PhilCommerceBundle`.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README; the installation guide lists
  `ext-mbstring` among the requirements.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- Refunds under an idempotency key, the one of the core's `PaymentGateway::refund()`: `Returns::refund()` and
  `exchange()` refund each payment under the return's id and the payment's, the same on every try, and
  `ReturnsGateway` records a refund under its key, so that one asked again is recorded once.
