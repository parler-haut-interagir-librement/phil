# Changelog

All notable changes to `phil/commerce-stored-value-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

## 0.1.0-alpha - 2026-11-20

### Added

- `Instrument`, `Movement`, `InstrumentKind`, `MovementKind`, `GiftCardCode`, `StoredValueRefused`.
  `Instrument::assertRedeemable()` and `canTakeValue()`: an instrument checks that it is active and unexpired before
  it checks the amount.
- `GiftCards`, which takes the core's `Payments`: `issueFor()` refuses an order with anything left to pay
  (`StoredValueRefused::notPaid()`), and `voidFor()` voids the cards of a cancelled order. `IssuedGiftCard`,
  `StoreCredits`.
- `GiftCardOrigin` (`Given`, `Paid`), `Instrument::$origin` and `Instrument::$specificService`, columns `origin` and
  `specific_service`: a given card may expire, a paid card only when it is for a specific service, and
  `Instrument::giftCard()` refuses any other expiry; `GiftCards::give()`, and the `origin` and `specificService`
  arguments of `GiftCards::issue()`; the cards `issueFor()` issues are paid, without expiry. The documentation says
  that each shop checks the law that applies.
- `Infrastructure\Pdf\GiftCardPdf`: a card just issued printed on one page the size of a payment card, with the native
  PDF writer of `phil/paged-media`, which the bundle requires; labels and fonts given by the application.
- `StoredValuePayments`, which refuses a voided, expired or empty instrument before the core's payment is created.
- `GiftCardSellables`, the core's `SellableResolver` for the type `gift_card`; `StoredValueGateway`, the core's
  `PaymentGateway` for the methods `gift_card` and `store_credit`. A refund to a gift card voided or expired since it
  paid becomes the store credit of the order's customer, or is refused with a clear message; the port
  `Application\Port\StoreCreditHolders` names that customer for a refund a gift card cannot take.
- `InstrumentRepository`, with `cardsIssuedFor()`, and `DoctrineStoredValueRepository`.
- `PhilCommerceStoredValueBundle`, extension alias `phil_commerce_stored_value` (`gift_card.label`,
  `gift_card.tax_category`, `gift_card.max_value`), which refuses to load without `PhilCommerceBundle`.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README; the installation guide lists
  `ext-mbstring` among the requirements.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- Refunds under an idempotency key, the one of the core's `PaymentGateway::refund()`: `StoredValueGateway` records
  the refund on the payment under its key before it gives the value back, so that a refund asked again gives nothing
  more.
