# Changelog

All notable changes to `phil/commerce-stored-value-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.0-alpha: the movements table gains two columns and two unique constraints, and
`GiftCards::issueFor()` returns a `GiftCardIssuance`. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- The optional `idempotencyKey` argument of `GiftCards::load()` and `StoreCredits::grant()`, last of each: asked again
  with the same key on the same instrument, the operation adds nothing and returns the card, or the movement first
  recorded; a key replayed with another amount is refused with `InvalidValue`. `Movement::$idempotencyKey`, column
  `idempotency_key`, unique with `instrument`; `Instrument::credit()` takes the key, and
  `Instrument::checkIdempotencyKey()` refuses one that is empty, padded or longer than 128 characters.
- `Movement::$issuedUnit`, column `issued_unit`: the rank of a card among the cards its order issued, unique with
  `order_number` and `kind`; the `issuedUnit` argument of `Instrument::giftCard()`.
- `GiftCardIssuance`, which `issueFor()` returns: iterable over the cards issued now, with their codes, and every card
  of the order in `cards`.
- `InstrumentRepository::addIssuedCards()`, which stores an order's cards at once, all or none, and returns `false`
  when another request stored them first, and `InstrumentRepository::movementUnderKey()`.
- Integration tests on SQLite, MySQL and PostgreSQL that issue the cards of one order from six processes at once.
- The documentation of value created once, of replaying a Messenger message, and of the migration from 0.1.

### Changed

- `GiftCards::issueFor()` stores the cards it issues at once, through `addIssuedCards()`, and returns a
  `GiftCardIssuance` instead of a list: a `foreach` over it goes through the same cards as before.
- `DoctrineStoredValueRepository` stores an order's cards with an entity manager of their own on the same connection,
  so that a refusal leaves the application's entity manager open, and the application's listeners see them stored;
  `doctrine/dbal` and `doctrine/event-manager` are requirements.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6), and of each development
  requirement (`doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Security

- Two requests issuing the gift cards of the same order at the same moment each counted the cards already issued before
  the other stored its own, and together issued them twice: each card is now issued under its rank among the order's
  cards, which a unique constraint keeps once, and the request that comes second issues none and returns the cards of
  the first.
- A load or a grant replayed — a Messenger message retried or delivered twice — added its value again: under an
  idempotency key, it is now recorded once per instrument, by a unique constraint.

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
