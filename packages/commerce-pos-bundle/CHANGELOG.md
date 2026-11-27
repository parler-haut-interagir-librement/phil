# Changelog

All notable changes to `phil/commerce-pos-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-26

### Changed

- Accepts `phil/ddd-bundle` 0.2 as well as 0.1 (`^0.1@dev || ^0.2@dev`), so that it installs beside the typed identifiers in
  controllers and the Serializer.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `symfony/polyfill-php86` 1.43), and of each
  development requirement (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2,
  `symfony/password-hasher` 8.2, …).
- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Security

- The fake terminal signed its notifications with the HMAC under `%kernel.secret%` itself, which other bundles keyed
  their own hashes with. `FakePaymentTerminal` derives its key by HKDF-SHA256 under `phil_commerce_pos.fake_terminal`
  (`FakePaymentTerminal::USAGE`); a test that signs a notification by hand derives the same key.

## 0.1.0-alpha - 2026-11-20

### Added

- `Register` (with its `RegisterRole`, main or mobile, column `role`), `CashSession`, `CashMovement`, `RegisterSale`
  (with `tip()`, the `tip` and `currency` columns of `phil_commerce_pos_sale`, and the `preset` it was sold under),
  `SessionPayment`, `HeldSale`, `Layaway`, `ImportedSale`, `Terminal`, `TerminalPayment`, `SalePreset`, `CashierPin`;
  `SessionState`, `CashMovementKind`, `LayawayState`; `CashRounding`; `PosRefused`, `CashierPinRefused`. Every moment
  is a `datetime_utc_immutable` column, kept in UTC.
- `Registers`, with one main register per location and terminals that take PIN payments only at a main register;
  `CashSessions`, `SessionReport`, `RegisterCheckout`, `RegisterPayments`, `CashTender`, `Layaways`, `OfflineSale`,
  `OfflineSales`; `RegisterGateway`, the core's `PaymentGateway` for `cash` and the recorded methods.
  `RegisterCheckout::resume()` picks a held sale up at the register that held it or at the main register of its
  location, and `ring()` takes a `SalePreset`, refusing a cart without a customer under a preset that identifies one.
  `CashSessions::report()` reads the tips from the sales. `Layaways::open()` refuses an order that is not placed, or
  one already put aside, with `PosRefused`.
- Sessions at prices that include the taxes: `CashSessions::open(..., taxIncluded: true)`, column `tax_included`;
  `RegisterCheckout::start()` prices a cart as its session sells, `ring()` refuses a cart priced otherwise, offline
  sales follow their session, and a tip share leaves the included taxes out.
- Offline sales: `OfflineSale` bounds `total`, `tip` and the payments to 0 to 10^12 and a line to 100000 units, and
  reads `rung_at` as RFC 3339 with an optional fraction — `toISOString()` included —, refusing a date or time that
  does not exist. The port `Application\Contract\OfflineSaleGate` binds an offline sale's cashier and session to whoever
  sends it, and `OfflineSaleForbidden`.
- Connected terminals driven by the server: the port `Application\Contract\PaymentTerminal`, one adapter per
  processor, tagged `phil_commerce_pos.payment_terminal`; `TerminalPayments` (charge, cancel, notify), `TerminalGateway`,
  the core's `PaymentGateway` for the terminals' method, `TerminalAdapters`, `TerminalCharge`;
  `TerminalNotificationController`, which answers `200`, `400` or `413`; and `FakePaymentTerminal`, the `fake` adapter,
  reset by the kernel between requests.
- `SalePresets`; `CustomerDisplay` and `DisplayedCart`, which show nothing about the person; `TillDraws`, `PendingDraw`
  and the port `Application\Contract\TillDrawForm`, tagged `phil_commerce_pos.till_draw_form`, for draws entered at the
  counter once the order is paid; `CashDifferences` and `CashDifferenceReport`; `CashierSwitch`, the cashier switch by
  PIN — off by default, PINs hashed by Symfony PasswordHasher, weak PINs refused, attempts bounded by a rate limiter
  keyed by register and user.
- `RegisterRepository`, `CashSessionRepository`, `RegisterSaleRepository`, and `DoctrinePosRepository`, whose queries
  sort with PHP's `SortDirection`.
- `PhilCommercePosBundle`, extension alias `phil_commerce_pos` (`recorded_methods`, `tip_label`, `layaway_deposit`,
  `cash_difference_threshold`, `terminals.method`, `terminals.fake`, `cashier_pin.enabled`, `cashier_pin.limiter`),
  which refuses to load without `PhilCommerceBundle` and adds `assets/src` to AssetMapper's paths under
  `@phil/commerce-pos-bundle`.
- `OfflineSaleController` reads only a body sent as `application/json` (`415` otherwise), answers `403` for
  `OfflineSaleForbidden`, and answers `422` for a malformed sale, one whose amounts overflow once re-priced or one
  paid by a method the register does not take, without blocking the queue behind it.
- The Stimulus controller `offline-queue`, fetched lazily, with its queue and `IndexedDbStore`, in `assets/`: the main
  register's sales wait in an IndexedDB database. The queue never follows a redirect, so an expired session keeps the
  sale; it waits on no answer, 401, 408, 429 and 5xx, counts a 2xx as done only when its body names the sale, and
  removes a sale by its id, so two tabs flushing the same database drop nothing unsent. `OfflineSales` imports only
  into a main register's session, and never over an existing order.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README; the installation guide lists
  `ext-mbstring` among the requirements.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- Refunds under an idempotency key, the one of the core's `PaymentGateway::refund()`: `RegisterGateway` records a
  refund under its key, so that one asked again is recorded once; `TerminalGateway` hands the key to the adapter, and
  `PaymentTerminal::refund()` takes it, for the processor; `FakePaymentTerminal::refunded()` lists it.
  `RegisterPayments::refundCash()` and `refundRecorded()` take the key: a cash refund asked again under its key gives
  nothing more and moves no cash.
